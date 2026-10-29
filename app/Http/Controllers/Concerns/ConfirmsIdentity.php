<?php

namespace App\Http\Controllers\Concerns;

use App\Services\PanelAudit;
use App\Services\TwoFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Contraseña + código del autenticador para las acciones masivas: así una sesión robada no
 * basta para ejecutarlas.
 */
trait ConfirmsIdentity
{
    protected function confirmIdentity(Request $request, string $action): void
    {
        $user = $request->user();

        if (! Hash::check((string) $request->input('password'), $user->password)) {
            PanelAudit::log("{$action}_denied", 'Contraseña incorrecta al confirmar una acción masiva', $user);

            throw ValidationException::withMessages(['password' => 'La contraseña no es correcta.']);
        }

        if (! $user->two_factor_secret || ! app(TwoFactor::class)->verify($user->two_factor_secret, (string) $request->input('code'), $user->id)) {
            PanelAudit::log("{$action}_denied", 'Código de doble factor inválido al confirmar una acción masiva', $user);

            throw ValidationException::withMessages(['code' => 'El código del autenticador no es válido.']);
        }
    }
}
