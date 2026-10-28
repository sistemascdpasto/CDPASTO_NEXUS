<?php

namespace App\Services;

use App\Enums\AlertType;
use App\Models\EmergencyLockdown;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Botón de emergencia: detiene los servicios del proyecto en Railway (ante un ataque) y los
 * vuelve a levantar después.
 *
 * - Nunca se tocan las bases de datos (servicios de imagen, p. ej. mysql:9): los datos quedan
 *   intactos y, como solo son accesibles por la red privada, nadie llega a ellos con las apps
 *   apagadas.
 * - Nunca se apaga Nexus (config nexus.emergency.protected_services): es el que permite reconectar.
 * - Desconectar = deploymentStop del despliegue activo. Reconectar = deploymentRedeploy de ese
 *   mismo despliegue con su imagen ya construida (sin compilar de nuevo, ~1-2 min por servicio).
 */
class EmergencyLockdownService
{
    public function __construct(private RailwayClient $railway, private AlertManager $alerts) {}

    /**
     * Servicios que el botón puede apagar, con el estado de su último despliegue.
     *
     * @return Collection<int, array{id: string, name: string, deployment_id: ?string, status: ?string, deployed_at: ?string}>
     */
    public function targets(): Collection
    {
        $data = $this->railway->query(
            'query ($id: String!) { project(id: $id) { services { edges { node { id name
                serviceInstances { edges { node { environmentId source { image repo } } } }
                deployments(first: 1) { edges { node { id status createdAt environmentId } } } } } } } }',
            ['id' => config('nexus.railway.project_id')],
        );

        $environment = config('nexus.railway.environment_id');
        $protected = array_map('strtolower', config('nexus.emergency.protected_services', []));
        $self = env('RAILWAY_SERVICE_ID');

        return collect($data['project']['services']['edges'] ?? [])
            ->map(fn ($edge) => $edge['node'])
            ->reject(function (array $service) use ($environment, $protected, $self) {
                $instance = collect($service['serviceInstances']['edges'] ?? [])->map(fn ($e) => $e['node'])
                    ->firstWhere('environmentId', $environment);

                return ! $instance
                    // Bases de datos y demás servicios de imagen: no se apagan.
                    || filled($instance['source']['image'] ?? null)
                    || in_array(strtolower($service['name']), $protected, true)
                    || ($self && $service['id'] === $self);
            })
            ->map(function (array $service) {
                $deployment = $service['deployments']['edges'][0]['node'] ?? null;

                return [
                    'id' => $service['id'],
                    'name' => $service['name'],
                    'deployment_id' => $deployment['id'] ?? null,
                    'status' => $deployment['status'] ?? null,
                    'deployed_at' => $deployment['createdAt'] ?? null,
                ];
            })
            ->sortBy('name')
            ->values();
    }

    /**
     * Detiene los servicios indicados (o todos los apagables). Devuelve la emergencia activa.
     *
     * @param  list<string>|null  $serviceIds
     */
    public function disconnect(User $user, string $reason, ?array $serviceIds = null): EmergencyLockdown
    {
        $targets = $this->targets();

        if ($serviceIds !== null) {
            $targets = $targets->whereIn('id', $serviceIds)->values();
        }

        if ($targets->isEmpty()) {
            throw new RuntimeException('No hay servicios para desconectar.');
        }

        $lockdown = DB::transaction(function () use ($user, $reason) {
            return EmergencyLockdown::current() ?? EmergencyLockdown::create([
                'status' => EmergencyLockdown::ACTIVE,
                'reason' => $reason,
                'started_by' => $user->id,
                'started_at' => now(),
                'services' => [],
            ]);
        });

        $services = collect($lockdown->services)->keyBy('service_id');

        foreach ($targets as $target) {
            $previous = $services->get($target['id']);

            if (($previous['state'] ?? null) === 'stopped') {
                continue;
            }

            $entry = [
                'service_id' => $target['id'],
                'name' => $target['name'],
                'deployment_id' => $target['deployment_id'],
                'state' => 'stopped',
                'stopped_at' => now()->toIso8601String(),
                'error' => null,
            ];

            try {
                if (! $target['deployment_id'] || $target['status'] !== 'SUCCESS') {
                    // Sin despliegue activo (ya caído, compilando…): no hay nada que detener.
                    $entry['state'] = 'skipped';
                    $entry['error'] = 'Sin despliegue activo ('.($target['status'] ?? 'sin despliegues').').';
                } else {
                    $this->railway->query('mutation ($id: String!) { deploymentStop(id: $id) }', ['id' => $target['deployment_id']]);
                }
            } catch (Throwable $e) {
                $entry['state'] = 'failed';
                $entry['error'] = mb_substr($e->getMessage(), 0, 300);
            }

            $services->put($target['id'], $entry);
        }

        $lockdown->update(['services' => $services->values()->all()]);

        $stopped = $services->where('state', 'stopped')->pluck('name')->implode(', ');
        $this->alerts->raise(
            null,
            AlertType::Emergency,
            'MODO EMERGENCIA: sistemas desconectados',
            "{$user->name} desconectó los sistemas. Motivo: {$reason}. Servicios detenidos: ".($stopped ?: 'ninguno').'.',
            ['lockdown_id' => $lockdown->id],
            cooldown: false,
        );

        PanelAudit::log('emergency.disconnect', 'Activó el botón de emergencia: desconectó los sistemas', $lockdown, [
            'reason' => $reason,
            'services' => $services->values()->map(fn ($s) => Arr::only($s, ['name', 'state', 'error']))->all(),
        ]);

        return $lockdown->fresh();
    }

    /**
     * Vuelve a levantar los servicios detenidos (todos o los indicados).
     *
     * @param  list<string>|null  $serviceIds
     */
    public function reconnect(User $user, ?array $serviceIds = null): ?EmergencyLockdown
    {
        $lockdown = EmergencyLockdown::current();

        if (! $lockdown) {
            return null;
        }

        $services = collect($lockdown->services)->keyBy('service_id');

        foreach ($services as $id => $entry) {
            if ($entry['state'] !== 'stopped' || ($serviceIds !== null && ! in_array($id, $serviceIds, true))) {
                continue;
            }

            try {
                try {
                    $this->railway->query(
                        'mutation ($id: String!) { deploymentRedeploy(id: $id, usePreviousImageTag: true) { id } }',
                        ['id' => $entry['deployment_id']],
                    );
                } catch (Throwable) {
                    // Si Railway ya no deja reusar ese despliegue, se reconstruye el último commit.
                    $this->railway->redeployService($id);
                }
                $entry['state'] = 'reconnected';
                $entry['reconnected_at'] = now()->toIso8601String();
                $entry['error'] = null;
            } catch (Throwable $e) {
                $entry['error'] = 'No se pudo reconectar: '.mb_substr($e->getMessage(), 0, 280);
            }

            $services->put($id, $entry);
        }

        $pending = $services->where('state', 'stopped')->count();

        $lockdown->update([
            'services' => $services->values()->all(),
            'status' => $pending === 0 ? EmergencyLockdown::RELEASED : EmergencyLockdown::ACTIVE,
            'released_by' => $pending === 0 ? $user->id : null,
            'released_at' => $pending === 0 ? now() : null,
        ]);

        $this->alerts->raise(
            null,
            AlertType::Emergency,
            $pending === 0 ? 'Fin del modo emergencia: sistemas reconectados' : 'Reconexión parcial de sistemas',
            "{$user->name} reconectó los sistemas.".($pending ? " Siguen detenidos: {$pending}." : ''),
            ['lockdown_id' => $lockdown->id],
            cooldown: false,
        );

        PanelAudit::log('emergency.reconnect', 'Reconectó los sistemas tras la emergencia', $lockdown, [
            'services' => $services->values()->map(fn ($s) => Arr::only($s, ['name', 'state', 'error']))->all(),
        ]);

        return $lockdown->fresh();
    }
}
