<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Obliga a configurar el doble factor antes de usar el panel (HU-23).
 */
class EnsureTwoFactorEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && config('nexus.require_two_factor') && ! $user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup');
        }

        return $next($request);
    }
}
