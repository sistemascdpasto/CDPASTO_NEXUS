<?php

namespace App\Support;

use App\Models\Application;

/**
 * Firma HMAC de las llamadas Nexus → agente. El agente valida con la misma API key.
 */
class AgentSigner
{
    /**
     * @return array<string, string>
     */
    public static function headers(Application $application, string $body = ''): array
    {
        $timestamp = (string) now()->timestamp;

        return [
            'X-Nexus-Timestamp' => $timestamp,
            'X-Nexus-Signature' => hash_hmac('sha256', $timestamp."\n".$body, $application->api_key),
            'Accept' => 'application/json',
            'User-Agent' => 'Nexus-Monitor/1.0',
        ];
    }
}
