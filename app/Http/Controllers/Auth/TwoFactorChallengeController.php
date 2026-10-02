<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PanelAudit;
use App\Services\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('login');
        }

        return Inertia::render('auth/two-factor-challenge');
    }

    public function store(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $request->validate([
            'code' => ['nullable', 'string', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'required_without:code'],
        ]);

        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'La verificación expiró. Inicia sesión de nuevo.']);
        }

        $key = "2fa-challenge:{$user->id}";

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'code' => 'Demasiados intentos. Espera '.RateLimiter::availableIn($key).' segundos.',
            ]);
        }

        $valid = $request->filled('recovery_code')
            ? $twoFactor->useRecoveryCode($user, $request->string('recovery_code'))
            : $twoFactor->verify($user->two_factor_secret, $request->string('code'), $user->id);

        if (! $valid) {
            RateLimiter::hit($key, 300);
            PanelAudit::log('auth.2fa_failed', "Código de doble factor inválido para {$user->name}", $user, userId: $user->id);

            throw ValidationException::withMessages([
                $request->filled('recovery_code') ? 'recovery_code' : 'code' => 'El código no es válido.',
            ]);
        }

        RateLimiter::clear($key);

        if ($request->filled('recovery_code')) {
            PanelAudit::log('auth.recovery_code', "{$user->name} usó un código de recuperación", $user, userId: $user->id);
        }

        $remember = (bool) $request->session()->pull('login.remember', false);
        $request->session()->forget(['login.id', 'login.expires']);

        return AuthenticatedSessionController::completeLogin($request, $user, $remember);
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get('login.id');
        $expires = $request->session()->get('login.expires', 0);

        if (! $id || $expires < now()->timestamp) {
            return null;
        }

        return User::active()->find($id);
    }
}
