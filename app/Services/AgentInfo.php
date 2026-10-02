<?php

namespace App\Services;

use App\Models\Application;
use App\Support\AgentSigner;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Ficha técnica de cada app (entorno, seguridad, trabajos fallidos, almacenamiento) vía /nexus/info.
 */
class AgentInfo
{
    /**
     * @return array<string, mixed>
     */
    public function refresh(Application $app): array
    {
        if (! $app->isLaravel()) {
            throw new RuntimeException('Solo las apps Laravel con agente reportan ficha técnica.');
        }

        $response = Http::withHeaders(AgentSigner::headers($app))->timeout(20)->get($app->agentUrl('info'));

        if (! $response->successful() || ! is_array($response->json('environment'))) {
            throw new RuntimeException($response->status() === 404
                ? 'El agente instalado no tiene /nexus/info: actualízalo a la versión 1.1.'
                : "El agente respondió HTTP {$response->status()}.");
        }

        $info = $response->json();

        $app->forceFill(['last_info' => $info, 'last_info_at' => now()])->save();

        return $info;
    }

    /**
     * Chequeos de seguridad que fallan.
     *
     * @return list<array<string, mixed>>
     */
    public static function findings(Application $app): array
    {
        return array_values(array_filter($app->last_info['security'] ?? [], fn ($check) => ! ($check['ok'] ?? true)));
    }
}
