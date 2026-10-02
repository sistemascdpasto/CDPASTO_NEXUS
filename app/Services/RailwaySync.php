<?php

namespace App\Services;

use App\Enums\AlertType;
use App\Models\Application;
use App\Models\Deployment;
use App\Models\ResourceMetric;
use Illuminate\Support\Carbon;

/**
 * Trae despliegues (HU-07) y consumo de recursos (HU-11) de Railway.
 */
class RailwaySync
{
    private const FAILED_STATUSES = ['FAILED', 'CRASHED'];

    public function __construct(private RailwayClient $railway, private AlertManager $alerts) {}

    public function syncDeployments(Application $app): int
    {
        $new = 0;

        foreach ($this->railway->deployments($app->railway_service_id) as $node) {
            $meta = $node['meta'] ?? [];

            $deployment = Deployment::updateOrCreate(['railway_id' => $node['id']], [
                'application_id' => $app->id,
                'status' => $node['status'],
                'commit_hash' => $meta['commitHash'] ?? null,
                'commit_message' => isset($meta['commitMessage']) ? mb_substr($meta['commitMessage'], 0, 500) : null,
                'commit_author' => $meta['commitAuthor'] ?? null,
                'branch' => $meta['branch'] ?? null,
                'deployed_at' => Carbon::parse($node['createdAt'])->setTimezone(config('app.timezone')),
            ]);

            $becameFailed = in_array($deployment->status, self::FAILED_STATUSES, true)
                && ($deployment->wasRecentlyCreated || $deployment->wasChanged('status'));

            // Solo alertamos fallos recientes y que sigan vigentes: si después hubo un despliegue
            // exitoso, el fallo ya se corrigió y no hay nada que atender.
            $superseded = Deployment::where('application_id', $app->id)
                ->where('deployed_at', '>', $deployment->deployed_at)
                ->where('status', 'SUCCESS')
                ->exists();

            if ($becameFailed && ! $superseded && $deployment->deployed_at->gt(now()->subHours(2))) {
                $this->alerts->raise($app, AlertType::DeployFailed, "Despliegue fallido en {$app->name}",
                    "Estado {$deployment->status}. Commit: ".($deployment->commit_message ?? $deployment->commit_hash ?? 'desconocido'),
                    ['deployment_id' => $deployment->railway_id],
                );
            }

            $new += (int) $deployment->wasRecentlyCreated;
        }

        return $new;
    }

    /**
     * @param  string  $kind  app = servicio de la aplicación; database = su servicio MySQL (para costos).
     */
    public function syncMetrics(Application $app, ?Carbon $from = null, string $kind = ResourceMetric::KIND_APP): int
    {
        $serviceId = $kind === ResourceMetric::KIND_DATABASE ? $app->database_service_id : $app->railway_service_id;

        if (! $serviceId) {
            return 0;
        }

        $series = $this->railway->metrics($serviceId, $from ?? now()->subHour());

        $columns = [
            'CPU_USAGE' => 'cpu',
            'MEMORY_USAGE_GB' => 'memory_gb',
            'NETWORK_RX_GB' => 'network_rx_gb',
            'NETWORK_TX_GB' => 'network_tx_gb',
        ];

        $rows = [];

        foreach ($columns as $measurement => $column) {
            foreach ($series[$measurement] ?? [] as $point) {
                $at = Carbon::createFromTimestamp($point['ts'], config('app.timezone'))->toDateTimeString();
                $rows[$at] ??= ['application_id' => $app->id, 'service_kind' => $kind, 'measured_at' => $at, 'cpu' => null, 'memory_gb' => null, 'network_rx_gb' => null, 'network_tx_gb' => null];
                $rows[$at][$column] = round((float) $point['value'], 6);
            }
        }

        if ($rows) {
            ResourceMetric::upsert(array_values($rows), ['application_id', 'service_kind', 'measured_at'], array_values($columns));
        }

        return count($rows);
    }
}
