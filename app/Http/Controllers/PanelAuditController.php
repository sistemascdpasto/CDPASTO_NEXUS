<?php

namespace App\Http\Controllers;

use App\Models\PanelAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Trazabilidad de lo que hacen los usuarios dentro de Nexus (HU-24).
 */
class PanelAuditController extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('superadmin');

        $filters = $request->only(['user_id', 'action', 'from', 'to']);

        return Inertia::render('panel-audit/index', [
            'logs' => PanelAuditLog::query()
                ->with('user:id,name')
                ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
                ->when($filters['action'] ?? null, fn ($q, $action) => $q->where('action', 'like', "{$action}%"))
                ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from))
                ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', $to.' 23:59:59'))
                ->latest('id')
                ->paginate(40)
                ->withQueryString(),
            'filters' => $filters,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'actionGroups' => [
                ['value' => 'auth', 'label' => 'Accesos'],
                ['value' => 'security', 'label' => 'Seguridad'],
                ['value' => 'remote', 'label' => 'Acciones remotas'],
                ['value' => 'application', 'label' => 'Aplicaciones'],
                ['value' => 'user', 'label' => 'Usuarios'],
                ['value' => 'error', 'label' => 'Errores'],
                ['value' => 'alert', 'label' => 'Alertas'],
                ['value' => 'audit', 'label' => 'Exportaciones'],
            ],
        ]);
    }
}
