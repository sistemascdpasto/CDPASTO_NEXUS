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

            // Solo alertamos fallos recientes; en la primera sincronización llegan fallos históricos.
            if ($becameFailed && $deployment->deployed_at->gt(now()->subHours(2))) {
                $this->alerts->raise($app, AlertType::DeployFailed, "Despliegue fallido en {$app->name}",
                    "Estado {$deployment->status}. Commit: ".($deployment->commit_message ?? $deployment->commit_hash ?? 'desconocido'),
                    ['deployment_id' => $deployment->railway_id],
                );
            }

            $new += (int) $deployment->wasRecentlyCreated;
        }

        return $new;
    }

    public function syncMetrics(Application $app, ?Carbon $from = null): int
    {
        $series = $this->railway->metrics($app->railway_service_id, $from ?? now()->subHour());

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
                $rows[$at] ??= ['application_id' => $app->id, 'measured_at' => $at, 'cpu' => null, 'memory_gb' => null, 'network_rx_gb' => null, 'network_tx_gb' => null];
                $rows[$at][$column] = round((float) $point['value'], 6);
            }
        }

        if ($rows) {
            ResourceMetric::upsert(array_values($rows), ['application_id', 'measured_at'], array_values($columns));
        }

        return count($rows);
    }
}
