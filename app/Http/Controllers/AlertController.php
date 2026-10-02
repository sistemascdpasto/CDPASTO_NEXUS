<?php

namespace App\Http\Controllers;

use App\Enums\AlertType;
use App\Models\Alert;
use App\Models\Application;
use App\Services\PanelAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['application_id', 'type', 'status']);

        return Inertia::render('alerts/index', [
            'alerts' => Alert::query()
                ->visibleTo($user)
                ->with('application:id,name', 'acknowledgedBy:id,name')
                ->when($filters['application_id'] ?? null, fn ($q, $id) => $q->where('application_id', $id))
                ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
                ->when(($filters['status'] ?? null) === 'open', fn ($q) => $q->whereNull('acknowledged_at'))
                ->latest()
                ->paginate(30)
                ->withQueryString(),
            'filters' => $filters,
            'applications' => Application::visibleTo($user)->orderBy('name')->get(['id', 'name']),
            'types' => collect(AlertType::cases())->map(fn (AlertType $type) => ['value' => $type->value, 'label' => $type->label()]),
            'channels' => [
                'mail' => config('mail.default') !== 'log',
                'whatsapp' => (bool) config('nexus.whatsapp.driver'),
            ],
        ]);
    }

    public function acknowledge(Request $request, Alert $alert): RedirectResponse
    {
        abort_unless($alert->application === null ? $request->user()->isSuperadmin() : $request->user()->canAccessApplication($alert->application), 403);

        $alert->update(['acknowledged_at' => now(), 'acknowledged_by' => $request->user()->id]);

        PanelAudit::log('alert.acknowledged', "Atendió la alerta \"{$alert->title}\"", $alert);

        return back();
    }

    public function acknowledgeAll(Request $request): RedirectResponse
    {
        $count = Alert::visibleTo($request->user())
            ->whereNull('acknowledged_at')
            ->update(['acknowledged_at' => now(), 'acknowledged_by' => $request->user()->id]);

        PanelAudit::log('alert.acknowledged_all', "Marcó {$count} alertas como atendidas");

        return back()->with('success', "{$count} alertas marcadas como atendidas.");
    }
}
