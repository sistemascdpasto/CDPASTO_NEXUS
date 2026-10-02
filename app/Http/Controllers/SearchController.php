<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\ErrorGroup;
use App\Models\LoginEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Búsqueda global para la paleta de comandos (Ctrl/⌘ + K): apps, personas y errores.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = trim((string) $request->query('q'));

        if (mb_strlen($q) < 2) {
            return response()->json(['apps' => [], 'people' => [], 'errors' => []]);
        }

        $apps = Application::visibleTo($user)->where('name', 'like', "%{$q}%")->limit(5)->get(['id', 'name', 'status']);
        $names = Application::visibleTo($user)->pluck('name', 'id');

        // Personas vistas por el agente en cualquier app (por nombre, correo o documento).
        $people = LoginEvent::visibleTo($user)
            ->whereNotNull('external_user_id')
            ->where(fn ($w) => $w->where('user_name', 'like', "%{$q}%")->orWhere('identifier', 'like', "%{$q}%"))
            ->groupBy('application_id', 'external_user_id')
            ->selectRaw('application_id, external_user_id, MAX(user_name) as user_name, MAX(identifier) as identifier')
            ->limit(8)
            ->get();

        if ($people->count() < 8) {
            $people = $people->concat(AuditLog::visibleTo($user)
                ->whereNotNull('external_user_id')
                ->where('user_name', 'like', "%{$q}%")
                ->groupBy('application_id', 'external_user_id')
                ->selectRaw('application_id, external_user_id, MAX(user_name) as user_name, NULL as identifier')
                ->limit(8 - $people->count())
                ->get())
                ->unique(fn ($p) => $p->application_id.'|'.$p->external_user_id);
        }

        $errors = ErrorGroup::visibleTo($user)
            ->where(fn ($w) => $w->where('message', 'like', "%{$q}%")->orWhere('exception_class', 'like', "%{$q}%"))
            ->latest('last_seen_at')->limit(5)
            ->get(['id', 'application_id', 'exception_class', 'message']);

        return response()->json([
            'apps' => $apps->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'status' => $a->status]),
            'people' => $people->values()->map(fn ($p) => [
                'name' => $p->user_name ?? $p->identifier ?? "#{$p->external_user_id}",
                'detail' => $p->identifier,
                'app' => $names[$p->application_id] ?? null,
                'href' => "/applications/{$p->application_id}/users/{$p->external_user_id}",
            ]),
            'errors' => $errors->map(fn ($e) => [
                'title' => class_basename($e->exception_class),
                'detail' => mb_strimwidth($e->message, 0, 80, '…').' · '.($names[$e->application_id] ?? ''),
                'href' => "/errors/{$e->id}",
            ]),
        ]);
    }
}
