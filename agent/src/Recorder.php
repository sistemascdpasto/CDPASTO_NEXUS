<?php

namespace Cdpasto\NexusAgent;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Guarda eventos en la tabla nexus_buffer. Nunca debe romper la app: todo error se ignora.
 */
class Recorder
{
    private static bool $recording = false;

    public static function enabled(): bool
    {
        return (bool) config('nexus_agent.enabled') && filled(config('nexus_agent.url')) && filled(config('nexus_agent.key'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function push(string $type, array $payload): void
    {
        // Evita recursión si el propio insert dispara eventos o excepciones.
        if (self::$recording || ! self::enabled()) {
            return;
        }

        self::$recording = true;

        try {
            DB::table('nexus_buffer')->insert([
                'type' => $type,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR),
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // La tabla puede no existir aún o la BD estar caída: no afectar a la app.
        } finally {
            self::$recording = false;
        }
    }

    public static function request(string $method, string $route, int $durationMs, int $status, ?string $userId = null): void
    {
        self::push('request', ['m' => $method, 'r' => $route, 'ms' => $durationMs, 's' => $status, 'u' => $userId, 't' => now()->toIso8601String()]);
    }

    public static function exception(Throwable $e): void
    {
        $request = app()->runningInConsole() ? null : request();
        $user = self::currentUser();

        self::push('exception', [
            'class' => get_class($e),
            'message' => Str::limit($e->getMessage(), 5000, ''),
            'file' => self::relativePath($e->getFile()),
            'line' => $e->getLine(),
            'trace' => Str::limit($e->getTraceAsString(), 6000, ''),
            'url' => $request?->fullUrl(),
            'method' => $request?->method(),
            'ip' => $request?->ip(),
            'user_id' => $user ? (string) $user->getAuthIdentifier() : null,
            'user_name' => self::userName($user),
            'occurred_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $credentials
     */
    public static function login(string $event, ?Authenticatable $user = null, ?array $credentials = null): void
    {
        $request = app()->runningInConsole() ? null : request();

        // Identificador usado al intentar ingresar (correo, cédula…), nunca la contraseña.
        $identifier = collect($credentials ?? [])->except(['password'])->first(fn ($value) => is_string($value));

        self::push('login', [
            'event' => $event,
            'user_id' => $user ? (string) $user->getAuthIdentifier() : null,
            'identifier' => $identifier ?? ($user->email ?? null),
            'user_name' => self::userName($user),
            'ip' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 500, '') : null,
            'occurred_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Registro de auditoría manual: Nexus::audit('exported', 'Reporte de flota').
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public static function audit(string $action, string $module, int|string|null $recordId = null, ?array $old = null, ?array $new = null): void
    {
        if (! config('nexus_agent.audit.enabled') || (app()->runningInConsole() && ! config('nexus_agent.audit.console'))) {
            return;
        }

        $request = app()->runningInConsole() ? null : request();
        $user = self::currentUser();

        self::push('audit', [
            'action' => $action,
            'module' => $module,
            'record_id' => $recordId,
            'old' => $old,
            'new' => $new,
            'user_id' => $user ? (string) $user->getAuthIdentifier() : null,
            'user_name' => self::userName($user),
            'ip' => $request?->ip(),
            'url' => $request ? Str::limit($request->fullUrl(), 1000, '') : null,
            'occurred_at' => now()->toIso8601String(),
        ]);
    }

    public static function model(string $action, Model $model): void
    {
        $class = get_class($model);

        if (in_array($class, config('nexus_agent.audit.exclude_models', []), true) || str_starts_with($class, 'Cdpasto\\NexusAgent')) {
            return;
        }

        $hidden = array_merge(config('nexus_agent.audit.hidden_attributes', []), $model->getHidden());
        $mask = fn (array $values) => collect($values)
            ->map(fn ($value, $key) => in_array($key, $hidden, true) ? '***' : $value)
            ->all();

        [$old, $new] = match ($action) {
            'created' => [null, $mask($model->getAttributes())],
            'deleted' => [$mask($model->getAttributes()), null],
            'restored' => [null, null],
            default => (function () use ($model, $mask) {
                $changes = collect($model->getChanges())->except(config('nexus_agent.audit.ignore_only', []));

                if ($changes->isEmpty()) {
                    return [null, null];
                }

                $old = $changes->keys()->mapWithKeys(fn ($key) => [$key => $model->getOriginal($key)])->all();

                return [$mask($old), $mask($changes->all())];
            })(),
        };

        if ($action === 'updated' && $old === null) {
            return;
        }

        self::audit($action, self::moduleName($class), $model->getKey(), $old, $new);
    }

    public static function currentUser(): ?Authenticatable
    {
        try {
            return auth()->user();
        } catch (Throwable) {
            return null;
        }
    }

    public static function userName(?object $user): ?string
    {
        if (! $user) {
            return null;
        }

        $attribute = config('nexus_agent.user_name_attribute', 'name');

        return $user->{$attribute} ?? $user->email ?? null;
    }

    public static function userRole(object $user): ?string
    {
        try {
            if (method_exists($user, 'getRoleNames')) {
                return $user->getRoleNames()->implode(', ') ?: null;
            }

            $role = $user->role ?? $user->rol ?? null;

            return $role instanceof \BackedEnum ? (string) $role->value : ($role ? (string) $role : null);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * App\Models\Flota\Vehiculo -> "Flota / Vehiculo": conserva el módulo de la app.
     */
    public static function moduleName(string $class): string
    {
        $relative = str_starts_with($class, 'App\Models\\') ? substr($class, strlen('App\Models\\')) : $class;

        return str_replace('\\', ' / ', $relative);
    }

    private static function relativePath(string $path): string
    {
        return ltrim(str_replace(base_path(), '', $path), DIRECTORY_SEPARATOR.'/');
    }
}
