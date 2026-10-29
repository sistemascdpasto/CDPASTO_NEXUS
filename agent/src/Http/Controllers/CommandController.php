<?php

namespace Cdpasto\NexusAgent\Http\Controllers;

use Cdpasto\NexusAgent\Blocks;
use Cdpasto\NexusAgent\Location;
use Cdpasto\NexusAgent\Recorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Órdenes de Nexus: sobre usuarios (cerrar sesión, bloquear, desbloquear) y sobre la app
 * (mantenimiento, limpiar caché, reintentar trabajos fallidos, ubicación obligatoria).
 */
class CommandController
{
    private const USER_COMMANDS = ['logout', 'block', 'unblock'];

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:logout,logout_all,block,unblock,maintenance_on,maintenance_off,cache_clear,queue_retry,location_on,location_off'],
            'user_id' => ['nullable', 'required_if:type,'.implode(',', self::USER_COMMANDS), 'string', 'max:64'],
            'minutes' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $message = match ($data['type']) {
                'logout' => $this->logout($data['user_id']),
                'logout_all' => $this->logoutAll(),
                'block' => $this->block($data['user_id'], $data['minutes'] ?? null, $data['reason'] ?? null),
                'unblock' => $this->unblock($data['user_id']),
                'maintenance_on' => $this->maintenanceOn(),
                'maintenance_off' => $this->maintenanceOff(),
                'cache_clear' => $this->artisan('cache:clear', [], 'Caché de la aplicación limpiada.'),
                'queue_retry' => $this->artisan('queue:retry', ['id' => ['all']], 'Trabajos fallidos enviados de nuevo a la cola.'),
                'location_on' => $this->locationPolicy(true),
                'location_off' => $this->locationPolicy(false),
            };
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json(['ok' => true, 'message' => $message]);
    }

    private function logout(string $userId): string
    {
        $closed = 0;

        if (config('session.driver') === 'database') {
            $closed = DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $userId)
                ->delete();
        }

        // Invalida también las cookies de "recordarme".
        $model = config('auth.providers.users.model');

        if ($model && class_exists($model)) {
            $user = new $model;

            if (Schema::hasColumn($user->getTable(), 'remember_token')) {
                DB::table($user->getTable())->where($user->getKeyName(), $userId)->update(['remember_token' => null]);
            }
        }

        return $closed > 0 ? "Se cerraron {$closed} sesiones." : 'El usuario no tenía sesiones abiertas.';
    }

    /**
     * Cierre masivo: todas las sesiones con usuario y todas las cookies de «recordarme».
     */
    private function logoutAll(): string
    {
        $closed = 0;

        if (config('session.driver') === 'database') {
            $closed = DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->whereNotNull('user_id')
                ->delete();
        }

        $model = config('auth.providers.users.model');

        if ($model && class_exists($model)) {
            $user = new $model;

            if (Schema::hasColumn($user->getTable(), 'remember_token')) {
                DB::table($user->getTable())->whereNotNull('remember_token')->update(['remember_token' => null]);
            }
        }

        return config('session.driver') === 'database'
            ? "Se cerraron {$closed} sesiones."
            : 'Se invalidaron las cookies de «recordarme» (el driver de sesión '.config('session.driver').' no permite cerrar las sesiones abiertas).';
    }

    private function block(string $userId, ?int $minutes, ?string $reason): string
    {
        Blocks::block($userId, $minutes, $reason);
        $this->logout($userId);

        Recorder::push('login', [
            'event' => 'blocked',
            'user_id' => $userId,
            'identifier' => null,
            'user_name' => null,
            'ip' => null,
            'user_agent' => 'Nexus',
            'occurred_at' => now()->toIso8601String(),
        ]);

        return $minutes ? "Usuario bloqueado por {$minutes} minutos." : 'Usuario bloqueado hasta nuevo aviso.';
    }

    private function locationPolicy(bool $required): string
    {
        Location::storePolicy($required);

        return $required ? 'Ubicación obligatoria activada.' : 'Ubicación obligatoria desactivada.';
    }

    private function maintenanceOn(): string
    {
        if (app()->isDownForMaintenance()) {
            return 'La aplicación ya estaba en mantenimiento.';
        }

        // Las rutas /nexus/* quedan excluidas (ver NexusAgentServiceProvider), así Nexus puede reactivarla.
        Artisan::call('down', ['--retry' => 60, '--refresh' => 60]);

        return 'Aplicación en modo mantenimiento. Los usuarios ven la página de mantenimiento.';
    }

    private function maintenanceOff(): string
    {
        Artisan::call('up');

        return 'Aplicación disponible nuevamente.';
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function artisan(string $command, array $parameters, string $message): string
    {
        $exitCode = Artisan::call($command, $parameters);

        if ($exitCode !== 0) {
            throw new \RuntimeException(trim(Artisan::output()) ?: "{$command} terminó con código {$exitCode}");
        }

        return $message;
    }

    private function unblock(string $userId): string
    {
        Blocks::unblock($userId);

        return 'Usuario desbloqueado.';
    }
}
