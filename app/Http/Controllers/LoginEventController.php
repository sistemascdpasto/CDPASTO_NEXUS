<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\LoginEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoginEventController extends Controller
{
    /**
     * Historial de inicios de sesión exitosos y fallidos (HU-13).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['application_id', 'event', 'search', 'from', 'to']);

        $base = LoginEvent::query()
            ->visibleTo($user)
            ->when($filters['application_id'] ?? null, fn ($q, $id) => $q->where('application_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('occurred_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('occurred_at', '<=', $to.' 23:59:59'))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('identifier', 'like', "%{$search}%")
                ->orWhere('user_name', 'like', "%{$search}%")
                ->orWhere('ip', 'like', "%{$search}%")));

        // IPs con más intentos fallidos en las últimas 24 h: indicio de ataque.
        $suspiciousIps = LoginEvent::query()
            ->visibleTo($user)
            ->where('event', 'failed')
            ->where('occurred_at', '>=', now()->subDay())
            ->whereNotNull('ip')
            ->groupBy('ip')
            ->selectRaw('ip, COUNT(*) as total, COUNT(DISTINCT identifier) as identifiers, MAX(occurred_at) as last_at')
            ->havingRaw('COUNT(*) >= 5')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return Inertia::render('logins/index', [
            'events' => (clone $base)
                ->with('application:id,name')
                ->when($filters['event'] ?? null, fn ($q, $event) => $q->where('event', $event))
                ->latest('occurred_at')
                ->paginate(30)
                ->withQueryString(),
            'stats' => (clone $base)->where('occurred_at', '>=', now()->subDay())
                ->groupBy('event')->selectRaw('event, COUNT(*) as total')->pluck('total', 'event'),
            'suspiciousIps' => $suspiciousIps,
            'filters' => $filters,
            'applications' => Application::visibleTo($user)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
