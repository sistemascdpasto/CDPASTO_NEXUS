<?php

namespace App\Http\Controllers;

use App\Enums\AppStatus;
use App\Models\Alert;
use App\Models\Application;
use App\Models\AppSession;
use App\Models\ErrorEvent;
use App\Models\ErrorGroup;
use App\Models\LoginEvent;
use App\Services\AgentInfo;
use App\Services\Metrics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Metrics $metrics): Response
    {
        $user = $request->user();
        $applications = Application::visibleTo($user)->active()->orderBy('name')->get();
        $ids = $applications->pluck('id')->all();
        $activeSince = now()->subMinutes(config('nexus.active_session_minutes'));

        $uptime24h = $metrics->uptimeByApp(now()->subDay(), $ids);
        $uptimeValues = array_filter($uptime24h, fn ($value) => $value !== null);

        $activeUsers = AppSession::whereIn('application_id', $ids)
            ->where('last_activity_at', '>=', $activeSince)
            ->groupBy('application_id')
            ->selectRaw('application_id, COUNT(DISTINCT external_user_id) as total')
            ->pluck('total', 'application_id');

        $errorsToday = ErrorEvent::whereIn('application_id', $ids)
            ->where('occurred_at', '>=', today())
            ->groupBy('application_id')
            ->selectRaw('application_id, COUNT(*) as total')
            ->pluck('total', 'application_id');

        return Inertia::render('dashboard', [
            'kpis' => [
                'apps_total' => $applications->count(),
                'apps_online' => $applications->where('status', AppStatus::Online)->count(),
                'apps_degraded' => $applications->where('status', AppStatus::Degraded)->count(),
                'apps_down' => $applications->where('status', AppStatus::Down)->count(),
                'active_users' => (int) $activeUsers->sum(),
                'errors_today' => (int) $errorsToday->sum(),
                'open_error_groups' => ErrorGroup::whereIn('application_id', $ids)->unresolved()->count(),
                'failed_logins_today' => LoginEvent::whereIn('application_id', $ids)->where('event', 'failed')->where('occurred_at', '>=', today())->count(),
                'uptime_avg' => $uptimeValues ? round(array_sum($uptimeValues) / count($uptimeValues), 2) : null,
                'open_alerts' => Alert::whereIn('application_id', $ids)->whereNull('acknowledged_at')->where('severity', '!=', 'info')->count(),
            ],
            'applications' => $applications->map(fn (Application $app) => [
                'id' => $app->id,
                'name' => $app->name,
                'url' => $app->url,
                'type' => $app->type,
                'status' => $app->status,
                'last_response_ms' => $app->last_response_ms,
                'last_checked_at' => $app->last_checked_at,
                'uptime_24h' => $uptime24h[$app->id] ?? null,
                'active_users' => (int) ($activeUsers[$app->id] ?? 0),
                'errors_today' => (int) ($errorsToday[$app->id] ?? 0),
                'agent' => $app->last_ingest_at !== null,
                'findings' => count(AgentInfo::findings($app)),
                'failed_jobs' => $app->last_info['failed_jobs']['total'] ?? null,
                'maintenance' => (bool) ($app->last_info['environment']['maintenance'] ?? false),
                'storage_percent' => $app->last_info['storage']['used_percent'] ?? null,
            ]),
            'findings' => $user->isSuperadmin() ? $applications
                ->flatMap(fn (Application $app) => collect(AgentInfo::findings($app))->map(fn ($f) => [...$f, 'app' => $app->name, 'app_id' => $app->id]))
                ->values() : [],
            'traffic' => $metrics->requestSeries(now()->subDay(), $ids),
            'recentAlerts' => Alert::with('application:id,name')
                ->whereIn('application_id', $ids)
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }
}
