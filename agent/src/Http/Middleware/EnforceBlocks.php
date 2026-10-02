<?php

namespace Cdpasto\NexusAgent\Http\Middleware;

use Cdpasto\NexusAgent\Blocks;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Expulsa a los usuarios bloqueados desde Nexus en su siguiente petición.
 */
class EnforceBlocks
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && Blocks::isBlocked((string) $user->getAuthIdentifier())) {
            Auth::guard()->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            $message = 'Tu acceso fue suspendido por el administrador del sistema.';

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['message' => $message], 403);
            }

            return redirect()->guest(route('login'))->with('status', $message)->withErrors([config('nexus_agent.login_field', 'email') => $message]);
        }

        return $next($request);
    }
}
