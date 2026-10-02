<?php

namespace App\Services;

use App\Enums\AlertType;
use App\Models\Application;
use App\Models\AppSession;
use App\Models\AuditLog;
use App\Models\ErrorEvent;
use App\Models\ErrorGroup;
use App\Models\LoginEvent;
use App\Models\RequestMetric;
use App\Models\UserActivity;
use App\Support\UserAgent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Guarda el lote de telemetría enviado por el agente de una app y evalúa alertas.
 */
class TelemetryIngestor
{
    public function __construct(private AlertManager $alerts) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, int>
     */
    public function ingest(Application $app, array $payload): array
    {
        $counts = DB::transaction(fn () => [
            'requests' => $this->storeRequests($app, $payload['requests'] ?? []),
            'activity' => $this->storeActivity($app, $payload['activity'] ?? []),
            'exceptions' => $this->storeExceptions($app, $payload['exceptions'] ?? []),
            'logins' => $this->storeLogins($app, $payload['logins'] ?? []),
            'audits' => $this->storeAudits($app, $payload['audits'] ?? []),
            'sessions' => array_key_exists('sessions', $payload) && is_array($payload['sessions'])
                ? $this->replaceSessions($app, $payload['sessions'])
                : 0,
        ]);

        $app->forceFill([
            'last_ingest_at' => now(),
            'agent_version' => $payload['agent_version'] ?? $app->agent_version,
        ])->save();

        $this->evaluateAlerts($app, $counts);

        return $counts;
    }

    private function storeRequests(Application $app, array $rows): int
    {
        $records = array_map(fn ($row) => [
            'application_id' => $app->id,
            'bucket' => $this->time($row['bucket']),
            'method' => strtoupper(substr($row['method'], 0, 10)),
            'route' => mb_substr($row['route'], 0, 255),
            'count' => (int) $row['count'],
            'total_ms' => (int) $row['total_ms'],
            'max_ms' => (int) $row['max_ms'],
            'errors_4xx' => (int) ($row['errors_4xx'] ?? 0),
            'errors_5xx' => (int) ($row['errors_5xx'] ?? 0),
        ], $rows);

        foreach (array_chunk($records, 500) as $chunk) {
            RequestMetric::insert($chunk);
        }

        return count($records);
    }

    private function storeActivity(Application $app, array $rows): int
    {
        $records = array_map(fn ($row) => [
            'application_id' => $app->id,
            'external_user_id' => (string) $row['user_id'],
            'user_name' => $row['user_name'] ?? null,
            'bucket' => $this->time($row['bucket']),
            'requests' => (int) $row['requests'],
            'routes' => isset($row['routes']) ? json_encode(array_slice($row['routes'], 0, 15, true)) : null,
        ], $rows);

        foreach (array_chunk($records, 500) as $chunk) {
            UserActivity::insert($chunk);
        }

        return count($records);
    }

    private function storeExceptions(Application $app, array $rows): int
    {
        foreach ($rows as $row) {
            $occurredAt = $this->time($row['occurred_at']);
            $fingerprint = hash('sha256', $row['class'].'|'.($row['file'] ?? '').'|'.($row['line'] ?? ''));

            $group = ErrorGroup::firstOrNew(['application_id' => $app->id, 'fingerprint' => $fingerprint]);

            if (! $group->exists) {
                $group->fill([
                    'exception_class' => mb_substr($row['class'], 0, 255),
                    'file' => isset($row['file']) ? mb_substr($row['file'], -500) : null,
                    'line' => $row['line'] ?? null,
                    'first_seen_at' => $occurredAt,
                ]);
            }

            $group->message = mb_substr($row['message'] ?? '', 0, 5000);
            $group->occurrences++;
            $group->last_seen_at = $occurredAt;

            // Un error resuelto que vuelve a aparecer se reabre.
            if ($group->resolved_at && $occurredAt->gt($group->resolved_at)) {
                $group->resolved_at = null;
                $group->resolved_by = null;
            }

            $group->save();

            ErrorEvent::create([
                'error_group_id' => $group->id,
                'application_id' => $app->id,
                'external_user_id' => $row['user_id'] ?? null,
                'user_name' => $row['user_name'] ?? null,
                'url' => isset($row['url']) ? mb_substr($row['url'], 0, 1000) : null,
                'method' => $row['method'] ?? null,
                'ip' => $row['ip'] ?? null,
                'trace' => isset($row['trace']) ? mb_substr($row['trace'], 0, 15000) : null,
                'occurred_at' => $occurredAt,
            ]);
        }

        return count($rows);
    }

    private function storeLogins(Application $app, array $rows): int
    {
        $records = array_map(fn ($row) => [
            'application_id' => $app->id,
            'external_user_id' => $row['user_id'] ?? null,
            'identifier' => isset($row['identifier']) ? mb_substr($row['identifier'], 0, 255) : null,
            'user_name' => $row['user_name'] ?? null,
            'event' => $row['event'],
            'ip' => $row['ip'] ?? null,
            'user_agent' => isset($row['user_agent']) ? mb_substr($row['user_agent'], 0, 500) : null,
            'device' => UserAgent::describe($row['user_agent'] ?? null),
            'occurred_at' => $this->time($row['occurred_at']),
        ], $rows);

        foreach (array_chunk($records, 500) as $chunk) {
            LoginEvent::insert($chunk);
        }

        return count($records);
    }

    private function storeAudits(Application $app, array $rows): int
    {
        $records = array_map(fn ($row) => [
            'application_id' => $app->id,
            'external_user_id' => $row['user_id'] ?? null,
            'user_name' => $row['user_name'] ?? null,
            'action' => $row['action'],
            'module' => mb_substr($row['module'], 0, 150),
            'record_id' => isset($row['record_id']) ? (string) $row['record_id'] : null,
            'old_values' => isset($row['old']) ? json_encode($row['old']) : null,
            'new_values' => isset($row['new']) ? json_encode($row['new']) : null,
            'ip' => $row['ip'] ?? null,
            'url' => isset($row['url']) ? mb_substr($row['url'], 0, 1000) : null,
            'occurred_at' => $this->time($row['occurred_at']),
        ], $rows);

        foreach (array_chunk($records, 300) as $chunk) {
            AuditLog::insert($chunk);
        }

        return count($records);
    }

    private function replaceSessions(Application $app, array $rows): int
    {
        AppSession::where('application_id', $app->id)->delete();

        // La hora de inicio de sesión se toma del último login exitoso registrado.
        $userIds = array_values(array_unique(array_map(fn ($row) => (string) $row['user_id'], $rows)));
        $loginTimes = LoginEvent::query()
            ->where('application_id', $app->id)
            ->where('event', 'login')
            ->whereIn('external_user_id', $userIds)
            ->groupBy('external_user_id')
            ->selectRaw('external_user_id, MAX(occurred_at) as last_login')
            ->pluck('last_login', 'external_user_id');

        $records = array_map(fn ($row) => [
            'application_id' => $app->id,
            'external_user_id' => (string) $row['user_id'],
            'user_name' => $row['user_name'] ?? null,
            'user_email' => $row['user_email'] ?? null,
            'user_role' => $row['user_role'] ?? null,
            'ip' => $row['ip'] ?? null,
            'user_agent' => isset($row['user_agent']) ? mb_substr($row['user_agent'], 0, 500) : null,
            'device' => UserAgent::describe($row['user_agent'] ?? null),
            'login_at' => $loginTimes[(string) $row['user_id']] ?? null,
            'last_activity_at' => $this->time($row['last_activity']),
        ], $rows);

        foreach (array_chunk($records, 500) as $chunk) {
            AppSession::insert($chunk);
        }

        return count($records);
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function evaluateAlerts(Application $app, array $counts): void
    {
        $since = now()->subMinutes(config('nexus.alerts.window_minutes'));
        $window = config('nexus.alerts.window_minutes');

        if ($counts['exceptions'] > 0) {
            $errors = ErrorEvent::where('application_id', $app->id)->where('occurred_at', '>=', $since)->count();

            if ($errors >= $app->error_threshold) {
                $this->alerts->raise($app, AlertType::ErrorSpike, "Pico de errores en {$app->name}",
                    "{$errors} excepciones en los últimos {$window} minutos (umbral: {$app->error_threshold}).",
                    ['count' => $errors],
                );
            }
        }

        if ($counts['logins'] > 0) {
            $failed = LoginEvent::where('application_id', $app->id)
                ->where('event', 'failed')
                ->where('occurred_at', '>=', $since);

            $total = (clone $failed)->count();

            if ($total >= $app->failed_login_threshold) {
                $topIps = (clone $failed)->selectRaw('ip, COUNT(*) as total')->groupBy('ip')->orderByDesc('total')->limit(3)->pluck('total', 'ip');

                $this->alerts->raise($app, AlertType::FailedLogins, "Intentos de acceso fallidos en {$app->name}",
                    "{$total} intentos fallidos en {$window} minutos. IPs principales: ".$topIps->map(fn ($n, $ip) => "{$ip} ({$n})")->implode(', '),
                    ['count' => $total, 'ips' => $topIps],
                );
            }
        }

        if ($counts['audits'] > 0) {
            $deleter = AuditLog::where('application_id', $app->id)
                ->where('action', 'deleted')
                ->where('occurred_at', '>=', $since)
                ->selectRaw('external_user_id, MAX(user_name) as user_name, COUNT(*) as total')
                ->groupBy('external_user_id')
                ->orderByDesc('total')
                ->first();

            if ($deleter && $deleter->total >= $app->mass_delete_threshold) {
                $this->alerts->raise($app, AlertType::MassDelete, "Eliminación masiva en {$app->name}",
                    ($deleter->user_name ?? 'Un proceso del sistema')." eliminó {$deleter->total} registros en {$window} minutos.",
                    ['user_id' => $deleter->external_user_id, 'count' => $deleter->total],
                );
            }
        }
    }

    private function time(string $value): Carbon
    {
        return Carbon::parse($value)->setTimezone(config('app.timezone'));
    }
}
