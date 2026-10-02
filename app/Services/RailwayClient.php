<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente mínimo de la API GraphQL pública de Railway.
 */
class RailwayClient
{
    public function isConfigured(): bool
    {
        return filled(config('nexus.railway.token'))
            && filled(config('nexus.railway.project_id'))
            && filled(config('nexus.railway.environment_id'));
    }

    /**
     * Servicios del proyecto, para vincularlos al registrar una app.
     *
     * @return list<array{id: string, name: string}>
     */
    public function services(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        return Cache::remember('railway:services', now()->addMinutes(10), function () {
            $data = $this->query('query ($id: String!) { project(id: $id) { services { edges { node { id name } } } } }', [
                'id' => config('nexus.railway.project_id'),
            ]);

            return collect($data['project']['services']['edges'] ?? [])
                ->map(fn ($edge) => ['id' => $edge['node']['id'], 'name' => $edge['node']['name']])
                ->sortBy('name')
                ->values()
                ->all();
        });
    }

    /**
     * @return list<array{id: string, status: string, createdAt: string, meta: ?array}>
     */
    public function deployments(string $serviceId, int $limit = 15): array
    {
        $data = $this->query(
            'query ($input: DeploymentListInput!, $first: Int) { deployments(first: $first, input: $input) { edges { node { id status createdAt meta } } } }',
            [
                'first' => $limit,
                'input' => [
                    'projectId' => config('nexus.railway.project_id'),
                    'environmentId' => config('nexus.railway.environment_id'),
                    'serviceId' => $serviceId,
                ],
            ],
        );

        return array_map(fn ($edge) => $edge['node'], $data['deployments']['edges'] ?? []);
    }

    /**
     * Series de CPU (vCPU), memoria (GB) y red (GB) del servicio.
     *
     * @return array<string, list<array{ts: int, value: float}>>
     */
    public function metrics(string $serviceId, Carbon $from, int $sampleSeconds = 300): array
    {
        $data = $this->query(
            'query ($projectId: String!, $environmentId: String!, $serviceId: String!, $startDate: DateTime!, $sample: Int) {
                metrics(projectId: $projectId, environmentId: $environmentId, serviceId: $serviceId, startDate: $startDate,
                        sampleRateSeconds: $sample, measurements: [CPU_USAGE, MEMORY_USAGE_GB, NETWORK_RX_GB, NETWORK_TX_GB]) {
                    measurement values { ts value }
                }
            }',
            [
                'projectId' => config('nexus.railway.project_id'),
                'environmentId' => config('nexus.railway.environment_id'),
                'serviceId' => $serviceId,
                'startDate' => $from->toIso8601ZuluString(),
                'sample' => $sampleSeconds,
            ],
        );

        return collect($data['metrics'] ?? [])
            ->mapWithKeys(fn ($series) => [$series['measurement'] => $series['values']])
            ->all();
    }

    /**
     * Variables de entorno resueltas de un servicio (incluye secretos: no se deben mostrar ni guardar).
     *
     * @return array<string, string>
     */
    public function serviceVariables(string $serviceId): array
    {
        $data = $this->query('query ($projectId: String!, $environmentId: String!, $serviceId: String) {
            variables(projectId: $projectId, environmentId: $environmentId, serviceId: $serviceId)
        }', [
            'projectId' => config('nexus.railway.project_id'),
            'environmentId' => config('nexus.railway.environment_id'),
            'serviceId' => $serviceId,
        ]);

        return $data['variables'] ?? [];
    }

    /**
     * Últimas líneas de log de un despliegue (sin las de nivel debug).
     *
     * @return list<array{message: string, severity: string, timestamp: string}>
     */
    public function deploymentLogs(string $deploymentId, int $limit = 300): array
    {
        $data = $this->query('query ($id: String!, $limit: Int) { deploymentLogs(deploymentId: $id, limit: $limit) { message severity timestamp } }', [
            'id' => $deploymentId,
            'limit' => $limit,
        ]);

        return array_values(array_filter($data['deploymentLogs'] ?? [], fn ($line) => ($line['severity'] ?? '') !== 'debug'));
    }

    /**
     * Reinicia el contenedor del despliegue actual (sin reconstruir).
     */
    public function restartDeployment(string $deploymentId): void
    {
        $this->query('mutation ($id: String!) { deploymentRestart(id: $id) }', ['id' => $deploymentId]);
    }

    /**
     * Vuelve a construir y desplegar el servicio con su último commit.
     */
    public function redeployService(string $serviceId): void
    {
        $this->query('mutation ($environmentId: String!, $serviceId: String!) { serviceInstanceRedeploy(environmentId: $environmentId, serviceId: $serviceId) }', [
            'environmentId' => config('nexus.railway.environment_id'),
            'serviceId' => $serviceId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function query(string $query, array $variables = []): array
    {
        $response = $this->http()->post(config('nexus.railway.endpoint'), [
            'query' => $query,
            'variables' => $variables,
        ]);

        $response->throw();

        if ($errors = $response->json('errors')) {
            throw new RuntimeException('Railway: '.($errors[0]['message'] ?? 'error desconocido'));
        }

        return $response->json('data') ?? [];
    }

    private function http(): PendingRequest
    {
        $token = config('nexus.railway.token');

        $request = Http::timeout(20)->acceptJson();

        return config('nexus.railway.token_type') === 'project'
            ? $request->withHeaders(['Project-Access-Token' => $token])
            : $request->withToken($token);
    }
}
