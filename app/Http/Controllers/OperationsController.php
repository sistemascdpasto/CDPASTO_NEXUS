<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Deployment;
use App\Services\AgentInfo;
use App\Services\PanelAudit;
use App\Services\RailwayClient;
use App\Services\RemoteCommander;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Acciones de superusuario sobre una app completa. Rutas protegidas con can:superadmin.
 */
class OperationsController extends Controller
{
    public function refreshInfo(Application $application, AgentInfo $info): RedirectResponse
    {
        try {
            $info->refresh($application);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Ficha técnica actualizada.');
    }

    public function command(Request $request, Application $application, RemoteCommander $commander): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(RemoteCommander::APP_COMMANDS))],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $command = $commander->send($application, $request->user(), $data['type'], null, null, ['reason' => $data['reason'] ?? null]);

        if ($command->status === 'done' && in_array($data['type'], ['maintenance_on', 'maintenance_off', 'queue_retry'], true)) {
            rescue(fn () => app(AgentInfo::class)->refresh($application), report: false);
        }

        return $command->status === 'done'
            ? back()->with('success', $command->response)
            : back()->with('error', "La app no ejecutó la acción: {$command->response}");
    }

    public function restart(Request $request, Application $application, RailwayClient $railway): RedirectResponse
    {
        $deployment = $application->deployments()->where('status', 'SUCCESS')->latest('deployed_at')->first();

        if (! $deployment) {
            return back()->with('error', 'No hay un despliegue activo sincronizado para reiniciar.');
        }

        return $this->railwayAction(
            fn () => $railway->restartDeployment($deployment->railway_id),
            'railway.restart', "Reinició el servicio de {$application->name} en Railway", $application, $request,
            'Reinicio solicitado. El servicio vuelve en unos segundos.',
        );
    }

    public function redeploy(Request $request, Application $application, RailwayClient $railway): RedirectResponse
    {
        if (! $application->railway_service_id) {
            return back()->with('error', 'La app no está vinculada a un servicio de Railway.');
        }

        return $this->railwayAction(
            fn () => $railway->redeployService($application->railway_service_id),
            'railway.redeploy', "Redesplegó {$application->name} en Railway", $application, $request,
            'Redespliegue iniciado. Railway construirá y publicará de nuevo el último commit.',
        );
    }

    /**
     * Logs de un despliegue (JSON para el visor del frontend).
     */
    public function logs(Application $application, Deployment $deployment, RailwayClient $railway): JsonResponse
    {
        abort_unless($deployment->application_id === $application->id, 404);

        try {
            return response()->json(['logs' => $railway->deploymentLogs($deployment->railway_id)]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    private function railwayAction(callable $action, string $auditAction, string $description, Application $application, Request $request, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (Throwable $e) {
            PanelAudit::log($auditAction, "{$description} — FALLÓ: {$e->getMessage()}", $application);

            return back()->with('error', 'Railway rechazó la acción: '.$e->getMessage());
        }

        PanelAudit::log($auditAction, $description, $application, ['reason' => $request->input('reason')]);

        return back()->with('success', $success);
    }
}
