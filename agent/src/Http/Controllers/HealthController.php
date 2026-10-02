<?php

namespace Cdpasto\NexusAgent\Http\Controllers;

use Cdpasto\NexusAgent\Console\FlushCommand;
use Cdpasto\NexusAgent\NexusAgentServiceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Estado interno de la app: base de datos, caché, cola y almacenamiento.
 * Siempre responde 200 si PHP está vivo; los fallos van en cada componente.
 */
class HealthController
{
    public function __invoke(): JsonResponse
    {
        $components = [
            'database' => $this->measure(function () {
                DB::select('select 1');

                return ['driver' => DB::getDriverName()];
            }),
            'cache' => $this->measure(function () {
                $key = 'nexus-agent:health:'.Str::random(8);
                Cache::put($key, 'ok', 10);
                $ok = Cache::get($key) === 'ok';
                Cache::forget($key);

                return ['ok' => $ok, 'driver' => config('cache.default')];
            }),
            'queue' => $this->measure(fn () => $this->queue()),
            'storage' => $this->measure(fn () => ['ok' => is_writable(storage_path('framework')), 'driver' => config('filesystems.default')]),
        ];

        // Solo si la app tiene scheduler (alguna vez registró latido).
        $heartbeat = rescue(fn () => Cache::get(FlushCommand::HEARTBEAT_KEY), null, false);

        if ($heartbeat) {
            $minutes = (int) floor((time() - $heartbeat) / 60);
            $components['scheduler'] = array_filter([
                'ok' => $minutes < 5,
                'last_run_minutes' => $minutes,
                'error' => $minutes >= 5 ? "El scheduler no corre hace {$minutes} min" : null,
            ], fn ($v) => $v !== null);
        }

        $healthy = collect($components)->every(fn ($c) => $c['ok']);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'components' => $components,
            'version' => NexusAgentServiceProvider::VERSION,
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'buffer_pending' => rescue(fn () => DB::table('nexus_buffer')->count(), null, false),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function queue(): array
    {
        $driver = config('queue.default');
        $connection = config("queue.connections.{$driver}", []);

        if (($connection['driver'] ?? $driver) !== 'database') {
            return ['driver' => $driver];
        }

        $jobs = DB::connection($connection['connection'] ?? null)->table($connection['table'] ?? 'jobs');
        $oldest = (clone $jobs)->whereNull('reserved_at')->min('available_at');
        $waitMinutes = $oldest ? (int) floor((time() - $oldest) / 60) : null;

        $failedTable = config('queue.failed.table', 'failed_jobs');

        return [
            'ok' => $waitMinutes === null || $waitMinutes < (int) config('nexus_agent.queue.max_wait_minutes', 30),
            'driver' => 'database',
            'pending' => (clone $jobs)->count(),
            'failed' => Schema::hasTable($failedTable) ? DB::table($failedTable)->count() : 0,
            'oldest_pending_minutes' => $waitMinutes !== null && $waitMinutes > 0 ? $waitMinutes : null,
            'error' => $waitMinutes !== null && $waitMinutes >= (int) config('nexus_agent.queue.max_wait_minutes', 30)
                ? 'Hay trabajos sin procesar: ¿está corriendo el worker o el scheduler?'
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function measure(callable $check): array
    {
        $start = microtime(true);

        try {
            $result = $check();

            return array_filter(['ok' => $result['ok'] ?? true, 'ms' => (int) round((microtime(true) - $start) * 1000)] + $result, fn ($v) => $v !== null);
        } catch (Throwable $e) {
            return ['ok' => false, 'ms' => (int) round((microtime(true) - $start) * 1000), 'error' => Str::limit($e->getMessage(), 200)];
        }
    }
}
