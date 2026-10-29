<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ConfirmsIdentity;
use App\Models\Application;
use App\Models\RemoteCommand;
use App\Services\RemoteCommander;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ubicación obligatoria (solo superadmin): decide en qué apps hay que permitir la ubicación
 * para usar el sistema. Apagarla pide contraseña y 2FA porque se pierde la trazabilidad.
 */
class LocationPolicyController extends Controller
{
    use ConfirmsIdentity;

    public function index(): Response
    {
        $apps = Application::active()->where('type', Application::TYPE_LARAVEL)->orderBy('name')->get();

        $last = RemoteCommand::query()
            ->with('user:id,name')
            ->whereIn('type', [RemoteCommander::LOCATION_ON, RemoteCommander::LOCATION_OFF])
            ->whereIn('application_id', $apps->pluck('id'))
            ->latest('id')
            ->get()
            ->unique('application_id')
            ->keyBy('application_id');

        return Inertia::render('location-policy/index', [
            'apps' => $apps->map(fn (Application $app) => [
                'id' => $app->id,
                'name' => $app->name,
                'location_required' => $app->location_required,
                'last_ingest_at' => $app->last_ingest_at?->toIso8601String(),
                'last_change' => ($cmd = $last->get($app->id)) ? [
                    'required' => $cmd->type === RemoteCommander::LOCATION_ON,
                    'by' => $cmd->user?->name,
                    'at' => $cmd->created_at->toIso8601String(),
                    'reason' => $cmd->payload['reason'] ?? null,
                    'applied' => $cmd->status === 'done',
                ] : null,
            ])->values(),
        ]);
    }

    public function update(Request $request, Application $application, RemoteCommander $commander): RedirectResponse
    {
        abort_unless($application->type === Application::TYPE_LARAVEL, 404);

        $data = $request->validate([
            'required' => ['required', 'boolean'],
            'reason' => [$request->boolean('required') ? 'nullable' : 'required', 'string', 'min:5', 'max:255'],
            'password' => [$request->boolean('required') ? 'nullable' : 'required', 'string'],
            'code' => [$request->boolean('required') ? 'nullable' : 'required', 'string'],
        ]);

        $required = (bool) $data['required'];

        if (! $required) {
            $this->confirmIdentity($request, 'location_policy.off');
        }

        $application->update(['location_required' => $required]);

        $command = $commander->send(
            $application,
            $request->user(),
            $required ? RemoteCommander::LOCATION_ON : RemoteCommander::LOCATION_OFF,
            null,
            null,
            ['reason' => $data['reason'] ?? null],
        );

        $state = $required ? 'activada' : 'desactivada';

        return $command->status === 'done'
            ? back()->with('success', "Ubicación obligatoria {$state} en {$application->name}. Ya está aplicada.")
            : back()->with('success', "Ubicación obligatoria {$state} en {$application->name}. La app no respondió ahora; la aplicará en su próximo reporte (≈1 minuto).");
    }
}
