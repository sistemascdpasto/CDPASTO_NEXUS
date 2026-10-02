<?php

namespace Cdpasto\NexusAgent\Http\Controllers;

use Cdpasto\NexusAgent\NexusAgentServiceProvider;
use Composer\InstalledVersions;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Ficha técnica de la app para Nexus: entorno, chequeos de seguridad, trabajos fallidos y almacenamiento.
 * Nunca expone valores secretos, solo si están configurados o no.
 */
class InfoController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'environment' => [
                'app_name' => config('app.name'),
                'env' => config('app.env'),
                'debug' => (bool) config('app.debug'),
                'url' => config('app.url'),
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale'),
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'agent' => NexusAgentServiceProvider::VERSION,
                'drivers' => [
                    'database' => config('database.default'),
                    'cache' => config('cache.default'),
                    'session' => config('session.driver'),
                    'queue' => config('queue.default'),
                    'mail' => config('mail.default'),
                    'filesystem' => config('filesystems.default'),
                ],
                'config_cached' => app()->configurationIsCached(),
                'routes_cached' => app()->routesAreCached(),
                'maintenance' => app()->isDownForMaintenance(),
            ],
            'packages' => $this->packages(),
            'security' => $this->securityChecks(),
            'storage' => $this->storage(),
            'failed_jobs' => $this->failedJobs(),
            'users' => $this->userStats(),
        ]);
    }

    /**
     * @return list<array{key: string, label: string, ok: bool, detail: string}>
     */
    private function securityChecks(): array
    {
        $production = app()->isProduction();
        $https = str_starts_with((string) config('app.url'), 'https://');

        $checks = [
            ['key' => 'debug', 'label' => 'APP_DEBUG desactivado', 'ok' => ! config('app.debug'), 'detail' => config('app.debug') ? 'Con APP_DEBUG=true los errores muestran código y variables al usuario.' : 'Correcto.'],
            ['key' => 'env', 'label' => 'Entorno de producción', 'ok' => $production, 'detail' => 'APP_ENV='.config('app.env')],
            ['key' => 'key', 'label' => 'APP_KEY configurada', 'ok' => filled(config('app.key')), 'detail' => filled(config('app.key')) ? 'Correcto.' : 'Sin APP_KEY las sesiones y datos cifrados no son seguros.'],
            ['key' => 'https', 'label' => 'APP_URL con HTTPS', 'ok' => $https, 'detail' => (string) config('app.url')],
            ['key' => 'session_http_only', 'label' => 'Cookie de sesión HttpOnly', 'ok' => (bool) config('session.http_only'), 'detail' => 'session.http_only'],
            ['key' => 'session_secure', 'label' => 'Cookie de sesión solo por HTTPS', 'ok' => (bool) config('session.secure') || ! $https, 'detail' => 'SESSION_SECURE_COOKIE='.(config('session.secure') ? 'true' : 'false')],
            ['key' => 'session_lifetime', 'label' => 'Sesiones expiran en ≤ 8 h', 'ok' => (int) config('session.lifetime') <= 480, 'detail' => config('session.lifetime').' minutos'],
            ['key' => 'log_level', 'label' => 'Nivel de log adecuado', 'ok' => ! $production || config('logging.channels.single.level', 'debug') !== 'debug', 'detail' => 'LOG_LEVEL='.config('logging.channels.single.level', 'debug')],
            ['key' => 'mail', 'label' => 'Correo configurado', 'ok' => ! in_array(config('mail.default'), ['log', 'array'], true), 'detail' => 'MAIL_MAILER='.config('mail.default')],
        ];

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'password')) {
            $model = config('auth.providers.users.model');
            $table = $model && class_exists($model) ? (new $model)->getTable() : 'users';
            $weak = rescue(fn () => DB::table($table)->whereNull('password')->orWhere('password', '')->count(), 0, false);

            $checks[] = ['key' => 'users_without_password', 'label' => 'Usuarios sin contraseña', 'ok' => $weak === 0, 'detail' => "{$weak} usuarios"];
        }

        return $checks;
    }

    /**
     * @return array<string, string|null>
     */
    private function packages(): array
    {
        $packages = ['laravel/framework', 'inertiajs/inertia-laravel', 'spatie/laravel-permission', 'maatwebsite/excel', 'barryvdh/laravel-dompdf', 'cdpasto/nexus-agent'];

        return collect($packages)
            ->mapWithKeys(fn ($package) => [$package => rescue(fn () => InstalledVersions::isInstalled($package) ? InstalledVersions::getPrettyVersion($package) : null, null, false)])
            ->filter()
            ->all();
    }

    /**
     * Uso del disco donde se guardan los archivos subidos (en Railway, el volumen montado).
     *
     * @return array<string, mixed>|null
     */
    private function storage(): ?array
    {
        $path = storage_path('app');
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);

        if (! $total) {
            return null;
        }

        return [
            'path' => $path,
            'total_gb' => round($total / 1073741824, 2),
            'used_gb' => round(($total - $free) / 1073741824, 2),
            'used_percent' => round(($total - $free) / $total * 100, 1),
        ];
    }

    /**
     * @return array{total: int, recent: list<array<string, mixed>>}
     */
    private function failedJobs(): array
    {
        $table = config('queue.failed.table', 'failed_jobs');

        try {
            if (! Schema::hasTable($table)) {
                return ['total' => 0, 'recent' => []];
            }

            $recent = DB::table($table)->orderByDesc('id')->limit(10)->get()->map(fn ($job) => [
                'id' => $job->id,
                'uuid' => $job->uuid ?? null,
                'queue' => $job->queue,
                'job' => json_decode($job->payload, true)['displayName'] ?? null,
                'exception' => Str::limit(strtok((string) $job->exception, "\n"), 300),
                'failed_at' => $job->failed_at,
            ])->all();

            return ['total' => DB::table($table)->count(), 'recent' => $recent];
        } catch (Throwable) {
            return ['total' => 0, 'recent' => []];
        }
    }

    /**
     * @return array<string, int>|null
     */
    private function userStats(): ?array
    {
        $model = config('auth.providers.users.model');

        if (! $model || ! class_exists($model)) {
            return null;
        }

        try {
            $query = $model::query();
            $table = (new $model)->getTable();
            $stats = ['total' => (clone $query)->count()];

            foreach (['is_active', 'activo', 'active'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $stats['active'] = (clone $query)->where($column, true)->count();
                    break;
                }
            }

            $stats['created_last_30d'] = (clone $query)->where('created_at', '>=', now()->subDays(30))->count();

            return $stats;
        } catch (Throwable) {
            return null;
        }
    }
}
