<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\PanelAudit;
use App\Services\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorController extends Controller
{
    /**
     * Configuración inicial (o reconfiguración) del doble factor.
     */
    public function setup(Request $request, TwoFactor $twoFactor): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled() && ! $request->session()->has('two_factor.recovery_codes')) {
            return redirect()->route('two-factor.edit');
        }

        $secret = $request->session()->get('two_factor.pending') ?? tap($twoFactor->generateSecret(), function ($secret) use ($request) {
            $request->session()->put('two_factor.pending', $secret);
        });

        return Inertia::render('auth/two-factor-setup', [
            'qrSvg' => $user->hasTwoFactorEnabled() ? null : $twoFactor->qrSvg($user, $secret),
            'secret' => $user->hasTwoFactorEnabled() ? null : $secret,
            'recoveryCodes' => $request->session()->pull('two_factor.recovery_codes'),
        ]);
    }

    public function confirm(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $secret = $request->session()->get('two_factor.pending');

        if (! $secret || ! $twoFactor->verify($secret, $request->string('code'))) {
            throw ValidationException::withMessages(['code' => 'El código no es válido. Verifica la hora de tu teléfono e intenta de nuevo.']);
        }

        $codes = $twoFactor->generateRecoveryCodes();

        $request->user()->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->forget('two_factor.pending');
        $request->session()->put('two_factor.recovery_codes', $codes);

        PanelAudit::log('security.2fa_enabled', "{$request->user()->name} activó el doble factor", $request->user());

        return redirect()->route('two-factor.setup');
    }

    public function edit(Request $request): Response
    {
        return Inertia::render('settings/two-factor', [
            'enabled' => $request->user()->hasTwoFactorEnabled(),
            'confirmedAt' => $request->user()->two_factor_confirmed_at,
            'recoveryCodesLeft' => count($request->user()->two_factor_recovery_codes ?? []),
            'recoveryCodes' => $request->session()->get('two_factor.new_codes'),
        ]);
    }

    public function regenerateRecoveryCodes(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $codes = $twoFactor->generateRecoveryCodes();
        $request->user()->forceFill(['two_factor_recovery_codes' => $codes])->save();

        PanelAudit::log('security.recovery_codes', "{$request->user()->name} regeneró sus códigos de recuperación", $request->user());

        return back()->with('two_factor.new_codes', $codes);
    }

    /**
     * Cambiar de teléfono: borra el secreto actual y obliga a configurar de nuevo.
     */
    public function reset(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        PanelAudit::log('security.2fa_reset', "{$request->user()->name} reinició su doble factor", $request->user());

        return redirect()->route('two-factor.setup');
    }
}
