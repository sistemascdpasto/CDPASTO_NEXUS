<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\ErrorEvent;
use App\Models\HealthCheck;
use App\Models\LoginEvent;
use App\Models\RequestMetric;
use App\Models\ResourceMetric;
use App\Models\UserActivity;
use Illuminate\Console\Command;

class PruneTelemetry extends Command
{
    protected $signature = 'nexus:prune';

    protected $description = 'Elimina telemetría más antigua que los días de retención configurados';

    public function handle(): int
    {
        $targets = [
            'health_checks' => [HealthCheck::class, 'checked_at'],
            'request_metrics' => [RequestMetric::class, 'bucket'],
            'user_activity' => [UserActivity::class, 'bucket'],
            'resource_metrics' => [ResourceMetric::class, 'measured_at'],
            'error_events' => [ErrorEvent::class, 'occurred_at'],
            'login_events' => [LoginEvent::class, 'occurred_at'],
            'audit_logs' => [AuditLog::class, 'occurred_at'],
            'alerts' => [Alert::class, 'created_at'],
        ];

        foreach ($targets as $key => [$model, $column]) {
            $cutoff = now()->subDays(config("nexus.retention_days.{$key}"));
            $deleted = 0;

            // Por lotes para no bloquear tablas grandes.
            do {
                $batch = $model::where($column, '<', $cutoff)->limit(5000)->delete();
                $deleted += $batch;
            } while ($batch > 0);

            $this->line("{$key}: {$deleted} registros eliminados");
        }

        return self::SUCCESS;
    }
}
