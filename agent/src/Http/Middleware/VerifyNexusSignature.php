<?php

namespace Cdpasto\NexusAgent\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Solo Nexus puede llamar a /nexus/*: firma HMAC-SHA256 de "timestamp\ncuerpo" con NEXUS_KEY.
 */
class VerifyNexusSignature
{
    private const TOLERANCE_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) config('nexus_agent.key');
        $timestamp = (string) $request->header('X-Nexus-Timestamp');
        $signature = (string) $request->header('X-Nexus-Signature');

        $valid = $key !== ''
            && ctype_digit($timestamp)
            && abs(time() - (int) $timestamp) <= self::TOLERANCE_SECONDS
            && hash_equals(hash_hmac('sha256', $timestamp."\n".$request->getContent(), $key), $signature);

        if (! $valid) {
            return response()->json(['ok' => false, 'message' => 'Firma inválida'], 401);
        }

        return $next($request);
    }
}
