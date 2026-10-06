<?php

namespace Cdpasto\NexusAgent;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Envía a Nexus lo acumulado en nexus_buffer junto con la fotografía de sesiones activas.
 */
class Flusher
{
    private const BATCH = 1000;

    /**
     * Envía solo si pasó el intervalo mínimo (se llama al final de cada petición).
     */
    public static function flushIfDue(): void
    {
        if (! Recorder::enabled()) {
            return;
        }

        try {
            if (Cache::add('nexus-agent:flush-due', true, (int) config('nexus_agent.flush_interval', 60))) {
                (new self)->flush();
            }
        } catch (Throwable) {
            // Nunca romper la petición del usuario.
        }
    }

    /**
     * @return array{ok: bool, sent: int, message: string}
     */
    public function flush(): array
    {
        $lock = Cache::lock('nexus-agent:flushing', 120);

        if (! $lock->get()) {
            // La petición web o el scheduler ya están enviando: no es una falla.
            return ['ok' => true, 'sent' => 0, 'message' => 'Otro envío en curso'];
        }

        try {
            $rows = DB::table('nexus_buffer')->orderBy('id')->limit(self::BATCH)->get();
            $payload = $this->buildPayload($rows);

            $response = Http::withToken(config('nexus_agent.key'))
                ->acceptJson()
                ->timeout(8)
                ->connectTimeout(4)
                ->post(rtrim(config('nexus_agent.url'), '/').'/api/v1/ingest', $payload);

            // Un lote que Nexus rechaza por formato nunca va a pasar: se descarta para no trabar el buffer.
            if ($response->status() === 422 && $rows->isNotEmpty()) {
                DB::table('nexus_buffer')->where('id', '<=', $rows->last()->id)->delete();

                return ['ok' => false, 'sent' => 0, 'message' => 'Lote descartado por formato inválido: '.mb_substr($response->body(), 0, 300)];
            }

            if (! $response->successful()) {
                $this->pruneStale();

                return ['ok' => false, 'sent' => 0, 'message' => "Nexus respondió HTTP {$response->status()}: ".mb_substr($response->body(), 0, 200)];
            }

            if ($rows->isNotEmpty()) {
                DB::table('nexus_buffer')->where('id', '<=', $rows->last()->id)->delete();
            }

            return ['ok' => true, 'sent' => $rows->count(), 'message' => 'Enviado'];
        } catch (Throwable $e) {
            $this->pruneStale();

            return ['ok' => false, 'sent' => 0, 'message' => $e->getMessage()];
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string, mixed>
     */
    private function buildPayload($rows): array
    {
        $grouped = $rows->groupBy('type')->map(fn ($items) => $items->map(fn ($row) => json_decode($row->payload, true))->filter()->values());

        return [
            'agent_version' => NexusAgentServiceProvider::VERSION,
            'requests' => $this->aggregateRequests($grouped->get('request', collect())),
            'activity' => $this->aggregateActivity($grouped->get('request', collect())),
            'exceptions' => $grouped->get('exception', collect())->all(),
            'logins' => $grouped->get('login', collect())->all(),
            'audits' => $grouped->get('audit', collect())->all(),
            'sessions' => $this->activeSessions(),
        ];
    }

    /**
     * Agrupa las peticiones por minuto, método y ruta.
     *
     * @param  Collection<int, array<string, mixed>>  $requests
     * @return list<array<string, mixed>>
     */
    private function aggregateRequests($requests): array
    {
        return $requests
            ->groupBy(fn ($r) => Carbon::parse($r['t'])->startOfMinute()->toIso8601String().'|'.$r['m'].'|'.$r['r'])
            ->map(function ($items, $key) {
                [$bucket, $method, $route] = explode('|', $key, 3);

                return [
                    'bucket' => $bucket,
                    'method' => $method,
                    'route' => $route,
                    'count' => $items->count(),
                    'total_ms' => (int) $items->sum('ms'),
                    'max_ms' => (int) $items->max('ms'),
                    'errors_4xx' => $items->filter(fn ($r) => $r['s'] >= 400 && $r['s'] < 500)->count(),
                    'errors_5xx' => $items->filter(fn ($r) => $r['s'] >= 500)->count(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Actividad por usuario y hora: cuántas peticiones hizo y qué rutas usó más.
     *
     * @param  Collection<int, array<string, mixed>>  $requests
     * @return list<array<string, mixed>>
     */
    private function aggregateActivity($requests): array
    {
        $byUser = $requests
            ->filter(fn ($r) => ! empty($r['u']))
            ->groupBy(fn ($r) => Carbon::parse($r['t'])->startOfHour()->toIso8601String().'|'.$r['u']);

        if ($byUser->isEmpty()) {
            return [];
        }

        $names = $this->userNames($byUser->map(fn ($items) => $items->first()['u'])->unique()->values()->all());

        return $byUser->map(function ($items, $key) use ($names) {
            [$bucket, $userId] = explode('|', $key, 2);

            return [
                'bucket' => $bucket,
                'user_id' => $userId,
                'user_name' => $names[$userId] ?? null,
                'requests' => $items->count(),
                'routes' => $items->countBy(fn ($r) => $r['m'].' '.$r['r'])->sortDesc()->take(15)->all(),
            ];
        })->values()->all();
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, string|null>
     */
    private function userNames(array $ids): array
    {
        $model = config('auth.providers.users.model');

        if (! $ids || ! $model || ! class_exists($model)) {
            return [];
        }

        return $model::query()->whereIn((new $model)->getKeyName(), $ids)->get()
            ->mapWithKeys(fn ($user) => [(string) $user->getKey() => Recorder::userName($user)])
            ->all();
    }

    /**
     * Sesiones con actividad reciente (requiere SESSION_DRIVER=database).
     *
     * @return list<array<string, mixed>>|null
     */
    private function activeSessions(): ?array
    {
        if (config('session.driver') !== 'database') {
            return null;
        }

        $table = config('session.table', 'sessions');
        $since = now()->subMinutes((int) config('nexus_agent.sessions.active_minutes', 15))->timestamp;

        $sessions = DB::connection(config('session.connection'))
            ->table($table)
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $since)
            ->orderByDesc('last_activity')
            ->get(['user_id', 'ip_address', 'user_agent', 'last_activity'])
            ->unique('user_id');

        $model = config('auth.providers.users.model');
        $users = $model && class_exists($model)
            ? $model::query()->whereIn((new $model)->getKeyName(), $sessions->pluck('user_id'))->get()->keyBy(fn ($u) => (string) $u->getKey())
            : collect();

        return $sessions->map(function ($session) use ($users) {
            $user = $users->get((string) $session->user_id);

            return [
                'user_id' => (string) $session->user_id,
                'user_name' => Recorder::userName($user),
                'user_email' => $user->email ?? null,
                'user_role' => $user ? Recorder::userRole($user) : null,
                'ip' => $session->ip_address,
                'user_agent' => mb_substr((string) $session->user_agent, 0, 500),
                'last_activity' => Carbon::createFromTimestamp($session->last_activity)->toIso8601String(),
            ];
        })->values()->all();
    }

    /**
     * Si Nexus está caído por mucho tiempo, no dejar crecer el buffer sin límite.
     */
    private function pruneStale(): void
    {
        try {
            DB::table('nexus_buffer')->where('created_at', '<', now()->subHours((int) config('nexus_agent.buffer_retention_hours', 48)))->delete();
        } catch (Throwable) {
        }
    }

    public static function tablesExist(): bool
    {
        return Schema::hasTable('nexus_buffer') && Schema::hasTable('nexus_blocks');
    }
}
