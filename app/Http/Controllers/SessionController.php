<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\AppSession;
use App\Services\RemoteCommander;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    /**
     * Usuarios conectados en tiempo real en todas las apps (HU-12).
     */
    public function index(Request $request, RemoteCommander $commander): Response
    {
        $user = $request->user();
        $applicationId = $request->query('application_id');

        $sessions = AppSession::query()
            ->visibleTo($user)
            ->with('application:id,name')
            ->where('last_activity_at', '>=', now()->subMinutes(config('nexus.active_session_minutes')))
            ->when($applicationId, fn ($q, $id) => $q->where('application_id', $id))
            ->orderByDesc('last_activity_at')
            ->get();

        $operable = Application::visibleTo($user)->get()
            ->filter(fn (Application $app) => $user->can('operate-application', $app))
            ->pluck('id');

        return Inertia::render('sessions/index', [
            'sessions' => $sessions,
            'blocks' => $commander->activeBlocks($user->accessibleApplicationIds())->map(fn ($cmd) => [
                'application' => $cmd->application,
                'external_user_id' => $cmd->external_user_id,
                'target_name' => $cmd->target_name,
                'blocked_at' => $cmd->executed_at,
                'blocked_by' => $cmd->user?->name,
                'minutes' => $cmd->payload['minutes'] ?? null,
                'reason' => $cmd->payload['reason'] ?? null,
            ]),
            'applications' => Application::visibleTo($user)->orderBy('name')->get(['id', 'name', 'last_ingest_at']),
            'operableIds' => $operable->values(),
            'filters' => ['application_id' => $applicationId],
            'refreshedAt' => now(),
        ]);
    }

    /**
     * Cierre de sesión remoto, bloqueo y desbloqueo (HU-14, HU-15).
     */
    public function command(Request $request, Application $application, RemoteCommander $commander): RedirectResponse
    {
        Gate::authorize('operate-application', $application);

        $data = $request->validate([
            'type' => ['required', Rule::in([RemoteCommander::LOGOUT, RemoteCommander::BLOCK, RemoteCommander::UNBLOCK])],
            'external_user_id' => ['required', 'string', 'max:64'],
            'target_name' => ['nullable', 'string', 'max:255'],
            'minutes' => ['nullable', 'integer', 'min:5', 'max:525600'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $command = $commander->send($application, $request->user(), $data['type'], $data['external_user_id'], $data['target_name'] ?? null, [
            'minutes' => $data['type'] === RemoteCommander::BLOCK ? ($data['minutes'] ?? null) : null,
            'reason' => $data['reason'] ?? null,
        ]);

        return $command->status === 'done'
            ? back()->with('success', $command->response ?: 'Acción ejecutada.')
            : back()->with('error', "La app no ejecutó la acción: {$command->response}");
    }
}
