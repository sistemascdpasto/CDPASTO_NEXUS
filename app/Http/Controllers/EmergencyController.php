<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ConfirmsIdentity;
use App\Models\EmergencyLockdown;
use App\Services\EmergencyLockdownService;
use App\Services\RailwayClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Botón de emergencia (solo superadmin): desconecta y reconecta los sistemas en Railway.
 */
class EmergencyController extends Controller
{
    use ConfirmsIdentity;

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

    public function disconnect(Request $request, EmergencyLockdownService $service): RedirectResponse
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

        $this->confirmIdentity($request, 'emergency.disconnect');

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

    public function reconnect(Request $request, EmergencyLockdownService $service): RedirectResponse
    {
        $data = $request->validate([
            'services' => ['nullable', 'array'],
            'services.*' => ['string', 'max:64'],
            'password' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        $this->confirmIdentity($request, 'emergency.reconnect');

        $lockdown = $service->reconnect($request->user(), ($data['services'] ?? null) ?: null);

        if (! $lockdown) {
            return back()->with('error', 'No hay una emergencia activa.');
        }

        return $lockdown->status === EmergencyLockdown::RELEASED
            ? back()->with('success', 'Reconexión iniciada. Los sistemas vuelven en 1 o 2 minutos.')
            : back()->with('success', 'Reconexión parcial iniciada. Algunos servicios siguen detenidos.');
    }
}
