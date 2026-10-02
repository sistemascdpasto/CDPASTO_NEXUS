<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\ResourceMetric;
use App\Services\RailwayClient;
use App\Services\RailwaySync;
use Illuminate\Console\Command;
use Throwable;

class SyncRailway extends Command
{
    protected $signature = 'nexus:sync-railway {--hours=1 : Horas de métricas a traer}';

    protected $description = 'Sincroniza despliegues y consumo de recursos desde Railway';

    public function handle(RailwayClient $client, RailwaySync $sync): int
    {
        if (! $client->isConfigured()) {
            $this->warn('Railway no está configurado (RAILWAY_API_TOKEN, NEXUS_RAILWAY_PROJECT_ID, NEXUS_RAILWAY_ENVIRONMENT_ID).');

            return self::SUCCESS;
        }

        $apps = Application::active()->whereNotNull('railway_service_id')->get();

        foreach ($apps as $app) {
            try {
                $deployments = $sync->syncDeployments($app);
                $points = $sync->syncMetrics($app, now()->subHours((int) $this->option('hours')));
                $points += $sync->syncMetrics($app, now()->subHours((int) $this->option('hours')), ResourceMetric::KIND_DATABASE);
                $this->line("{$app->name}: {$deployments} despliegues nuevos, {$points} puntos de métricas");
            } catch (Throwable $e) {
                report($e);
                $this->error("{$app->name}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
