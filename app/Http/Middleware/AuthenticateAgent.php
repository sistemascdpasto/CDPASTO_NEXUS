<?php

namespace App\Http\Middleware;

use App\Models\Application;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica al agente de una app por su API key (Authorization: Bearer nx_...).
 */
class AuthenticateAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $application = $token ? Application::findByApiKey($token) : null;

        if (! $application || ! $application->is_active) {
            return response()->json(['message' => 'API key inválida o app desactivada.'], 401);
        }

        $request->attributes->set('application', $application);

        return $next($request);
    }
}
