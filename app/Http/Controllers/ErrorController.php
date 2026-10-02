<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ErrorGroup;
use App\Services\PanelAudit;
use App\Support\Sql;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ErrorController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['application_id', 'status', 'search']);
        $status = $filters['status'] ?? 'open';

        $groups = ErrorGroup::query()
            ->visibleTo($user)
            ->with('application:id,name')
            ->when($filters['application_id'] ?? null, fn ($q, $id) => $q->where('application_id', $id))
            ->when($status === 'open', fn ($q) => $q->whereNull('resolved_at'))
            ->when($status === 'resolved', fn ($q) => $q->whereNotNull('resolved_at'))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('message', 'like', "%{$search}%")
                ->orWhere('exception_class', 'like', "%{$search}%")
                ->orWhere('file', 'like', "%{$search}%")))
            ->withCount(['events as last_24h' => fn ($q) => $q->where('occurred_at', '>=', now()->subDay())])
            ->orderByDesc('last_seen_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('errors/index', [
            'groups' => $groups,
            'filters' => [...$filters, 'status' => $status],
            'applications' => Application::visibleTo($user)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, ErrorGroup $error): Response
    {
        Gate::authorize('view-application', $error->application);

        $day = Sql::dayBucket('occurred_at');

        return Inertia::render('errors/show', [
            'group' => $error->load('application:id,name', 'resolver:id,name'),
            'events' => $error->events()->latest('occurred_at')->paginate(20),
            'affectedUsers' => $error->events()
                ->whereNotNull('external_user_id')
                ->groupBy('external_user_id')
                ->selectRaw('external_user_id, MAX(user_name) as user_name, COUNT(*) as total, MAX(occurred_at) as last_at')
                ->orderByDesc('total')
                ->limit(20)
                ->get(),
            'daily' => $error->events()
                ->where('occurred_at', '>=', now()->subDays(14)->startOfDay())
                ->groupByRaw($day)
                ->orderByRaw($day)
                ->selectRaw("{$day} as date, COUNT(*) as total")
                ->get(),
            'canOperate' => $request->user()->can('operate-application', $error->application),
        ]);
    }

    public function resolve(Request $request, ErrorGroup $error): RedirectResponse
    {
        Gate::authorize('operate-application', $error->application);

        $resolved = $error->resolved_at === null;

        $error->update([
            'resolved_at' => $resolved ? now() : null,
            'resolved_by' => $resolved ? $request->user()->id : null,
        ]);

        PanelAudit::log($resolved ? 'error.resolved' : 'error.reopened',
            ($resolved ? 'Marcó como resuelto' : 'Reabrió')." el error {$error->exception_class} de {$error->application->name}", $error);

        return back()->with('success', $resolved ? 'Error marcado como resuelto.' : 'Error reabierto.');
    }
}
