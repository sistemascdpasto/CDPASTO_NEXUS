<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Incident;
use App\Services\PanelAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Historial de caídas con duración, MTTR y causa raíz.
 */
class IncidentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['application_id', 'status']);
        $applications = Application::visibleTo($user)->orderBy('name')->get(['id', 'name']);
        $since = now()->subDays(30);

        // Estadísticas de 30 días por app: número de caídas, tiempo total caído y MTTR.
        $stats = Incident::visibleTo($user)
            ->where('started_at', '>=', $since)
            ->get(['application_id', 'started_at', 'resolved_at', 'duration_seconds'])
            ->groupBy('application_id')
            ->map(function ($incidents, $appId) use ($applications) {
                $resolved = $incidents->whereNotNull('duration_seconds');

                return [
                    'application_id' => $appId,
                    'name' => $applications->firstWhere('id', $appId)?->name,
                    'count' => $incidents->count(),
                    'downtime_seconds' => (int) $incidents->sum(fn (Incident $i) => $i->currentDuration()),
                    'mttr_seconds' => $resolved->isNotEmpty() ? (int) round($resolved->avg('duration_seconds')) : null,
                    'last_at' => $incidents->max('started_at'),
                ];
            })
            ->sortByDesc('count')
            ->values();

        return Inertia::render('incidents/index', [
            'incidents' => Incident::visibleTo($user)
                ->with('application:id,name', 'notesAuthor:id,name')
                ->when($filters['application_id'] ?? null, fn ($q, $id) => $q->where('application_id', $id))
                ->when(($filters['status'] ?? null) === 'open', fn ($q) => $q->whereNull('resolved_at'))
                ->when(($filters['status'] ?? null) === 'resolved', fn ($q) => $q->whereNotNull('resolved_at'))
                ->latest('started_at')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Incident $incident) => [
                    ...$incident->toArray(),
                    'current_duration' => $incident->currentDuration(),
                    'can_annotate' => $user->can('operate-application', $incident->application),
                ]),
            'stats' => $stats,
            'summary' => [
                'open' => Incident::visibleTo($user)->open()->count(),
                'count_30d' => $stats->sum('count'),
                'downtime_30d' => $stats->sum('downtime_seconds'),
                'mttr_30d' => ($mttr = Incident::visibleTo($user)->where('started_at', '>=', $since)->whereNotNull('duration_seconds')->avg('duration_seconds')) ? (int) round($mttr) : null,
            ],
            'filters' => $filters,
            'applications' => $applications,
        ]);
    }

    /**
     * Anotar la causa raíz / acciones tomadas.
     */
    public function update(Request $request, Incident $incident): RedirectResponse
    {
        Gate::authorize('operate-application', $incident->application);

        $data = $request->validate(['notes' => ['required', 'string', 'max:5000']]);

        $incident->update(['notes' => $data['notes'], 'notes_by' => $request->user()->id]);

        PanelAudit::log('incident.annotated', "Documentó la causa del incidente de {$incident->application->name} del {$incident->started_at->format('d/m/Y H:i')}", $incident);

        return back()->with('success', 'Incidente documentado.');
    }
}
