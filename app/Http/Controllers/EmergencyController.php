<?php

namespace App\Http\Controllers;

use App\Models\EmergencyLockdown;
use App\Services\EmergencyLockdownService;
use App\Services\PanelAudit;
use App\Services\RailwayClient;
use App\Services\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Botón de emergencia (solo superadmin): desconecta y reconecta los sistemas en Railway.
 */
class EmergencyController extends Controller
{
    public function index(EmergencyLockdownService $service, RailwayClient $railway): Response
    {
        $targets = [];
        $error = null;

        if (! $railway->isConfigured()) {
            $error = 'Railway no está configurado en Nexus (token, proyecto o entorno).';
        } else {
            try {
                $targets = $service->targets()->all();
            } catch (Throwable $e) {
                $error = 'No se pudo consultar Railway: '.$e->getMessage();
            }
        }

        return Inertia::render('emergency/index', [
            'services' => $targets,
            'railwayError' => $error,
            'current' => EmergencyLockdown::current()?->load('starter:id,name'),
            'history' => EmergencyLockdown::with(['starter:id,name', 'releaser:id,name'])->latest('id')->limit(10)->get(),
            'protected' => config('nexus.emergency.protected_services'),
            'projectId' => config('nexus.railway.project_id'),
        ]);
    }

    public function disconnect(Request $request, EmergencyLockdownService $service, TwoFactor $twoFactor): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'services' => ['nullable', 'array'],
            'services.*' => ['string', 'max:64'],
            'confirmation' => ['required', 'in:DESCONECTAR'],
            'password' => ['required', 'string'],
            'code' => ['required', 'string'],
        ], [
            'confirmation.in' => 'Escribe DESCONECTAR en mayúsculas para confirmar.',
        ]);

        $this->confirmIdentity($request, $twoFactor, 'emergency.disconnect');

        try {
            $lockdown = $service->disconnect($request->user(), $data['reason'], ($data['services'] ?? null) ?: null);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $failed = collect($lockdown->services)->where('state', 'failed')->count();

        return $failed
            ? back()->with('error', "Modo emergencia activo, pero {$failed} servicio(s) no se pudieron detener. Revisa la lista y usa el Plan B.")
            : back()->with('success', 'Sistemas desconectados. Las bases de datos y Nexus siguen en línea.');
    }

    public function reconnect(Request $request, EmergencyLockdownService $service, TwoFactor $twoFactor): RedirectResponse
    {
        $data = $request->validate([
            'services' => ['nullable', 'array'],
            'services.*' => ['string', 'max:64'],
            'password' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        $this->confirmIdentity($request, $twoFactor, 'emergency.reconnect');

        $lockdown = $service->reconnect($request->user(), ($data['services'] ?? null) ?: null);

        if (! $lockdown) {
            return back()->with('error', 'No hay una emergencia activa.');
        }

        return $lockdown->status === EmergencyLockdown::RELEASED
            ? back()->with('success', 'Reconexión iniciada. Los sistemas vuelven en 1 o 2 minutos.')
            : back()->with('success', 'Reconexión parcial iniciada. Algunos servicios siguen detenidos.');
    }

    /**
     * Contraseña + código del autenticador: así una sesión robada no basta para apagar (ni
     * encender) los sistemas.
     */
    private function confirmIdentity(Request $request, TwoFactor $twoFactor, string $action): void
    {
        $user = $request->user();

        if (! Hash::check($request->input('password'), $user->password)) {
            PanelAudit::log("{$action}_denied", 'Contraseña incorrecta al confirmar el botón de emergencia', $user);

            throw ValidationException::withMessages(['password' => 'La contraseña no es correcta.']);
        }

        if (! $user->two_factor_secret || ! $twoFactor->verify($user->two_factor_secret, $request->input('code'), $user->id)) {
            PanelAudit::log("{$action}_denied", 'Código de doble factor inválido al confirmar el botón de emergencia', $user);

            throw ValidationException::withMessages(['code' => 'El código del autenticador no es válido.']);
        }
    }
}
