<?php

namespace App\Services;

use App\Models\Application;
use App\Models\AppSession;
use App\Models\RemoteCommand;
use App\Models\User;
use App\Support\AgentSigner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Envía órdenes al agente de una app: cerrar sesión, bloquear y desbloquear usuarios (HU-14, HU-15).
 */
class RemoteCommander
{
    public const LOGOUT = 'logout';

    public const BLOCK = 'block';

    public const UNBLOCK = 'unblock';

    /** Comandos sobre la aplicación completa (solo superadmin). */
    public const APP_COMMANDS = [
        'maintenance_on' => 'Activó el modo mantenimiento de',
        'maintenance_off' => 'Desactivó el modo mantenimiento de',
        'cache_clear' => 'Limpió la caché de',
        'queue_retry' => 'Reintentó los trabajos fallidos de',
    ];

    /**
     * @param  array{minutes?: int|null, reason?: string|null}  $options
     */
    public function send(Application $app, User $actor, string $type, ?string $externalUserId, ?string $targetName = null, array $options = []): RemoteCommand
    {
        $command = RemoteCommand::create([
            'application_id' => $app->id,
            'user_id' => $actor->id,
            'type' => $type,
            'external_user_id' => $externalUserId,
            'target_name' => $targetName,
            'payload' => array_filter($options, fn ($value) => $value !== null) ?: null,
        ]);

        $body = json_encode([
            'id' => $command->id,
            'type' => $type,
            'user_id' => $externalUserId,
            'minutes' => $options['minutes'] ?? null,
            'reason' => $options['reason'] ?? null,
        ]);

        try {
            $response = Http::withHeaders(AgentSigner::headers($app, $body))
                ->timeout(30)
                ->withBody($body, 'application/json')
                ->post($app->agentUrl('commands'));

            $ok = $response->successful() && $response->json('ok') === true;
            $message = $response->json('message') ?? "HTTP {$response->status()}";
        } catch (Throwable $e) {
            $ok = false;
            $message = $e->getMessage();
        }

        $command->update([
            'status' => $ok ? 'done' : 'failed',
            'response' => mb_substr($message, 0, 500),
            'executed_at' => now(),
        ]);

        if ($ok && in_array($type, [self::LOGOUT, self::BLOCK], true)) {
            AppSession::where('application_id', $app->id)->where('external_user_id', $externalUserId)->delete();
        }

        $description = isset(self::APP_COMMANDS[$type])
            ? self::APP_COMMANDS[$type]." {$app->name}"
            : ['logout' => 'Cerró la sesión de', 'block' => 'Bloqueó a', 'unblock' => 'Desbloqueó a'][$type]." {$targetName} (#{$externalUserId}) en {$app->name}";

        PanelAudit::log("remote.{$type}", $description.($ok ? '' : ' — FALLÓ: '.$message), $app, [
            'external_user_id' => $externalUserId,
            'command_id' => $command->id,
            'status' => $command->status,
        ] + $options);

        return $command;
    }

    /**
     * Bloqueos vigentes por app: el último comando block/unblock exitoso de cada usuario.
     *
     * @param  list<int>|null  $applicationIds
     * @return Collection<int, RemoteCommand>
     */
    public function activeBlocks(?array $applicationIds = null)
    {
        return RemoteCommand::query()
            ->with('application:id,name', 'user:id,name')
            ->whereIn('type', [self::BLOCK, self::UNBLOCK])
            ->where('status', 'done')
            ->when($applicationIds !== null, fn ($q) => $q->whereIn('application_id', $applicationIds))
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($cmd) => $cmd->application_id.'|'.$cmd->external_user_id)
            ->map(fn ($commands) => $commands->last())
            ->filter(function (RemoteCommand $cmd) {
                if ($cmd->type !== self::BLOCK) {
                    return false;
                }

                $minutes = $cmd->payload['minutes'] ?? null;

                return $minutes === null || $cmd->executed_at->copy()->addMinutes($minutes)->isFuture();
            })
            ->values();
    }
}
