<?php

namespace App\Http\Controllers;

use App\Enums\AppStatus;
use App\Models\Alert;
use App\Models\Application;
use App\Models\AppSession;
use App\Models\ErrorEvent;
use App\Models\ErrorGroup;
use App\Models\Incident;
use App\Models\LoginEvent;
use App\Services\ActivityFeed;
use App\Services\AgentInfo;
use App\Services\CostEstimator;
use App\Services\HealthScore;
use App\Services\Metrics;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Metrics $metrics, HealthScore $healthScore, ActivityFeed $feed, CostEstimator $costs): Response
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

        $scores = $healthScore->forApplications($applications);
        $openIncidents = Incident::whereIn('application_id', $ids)->open()->with('application:id,name')->get();

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
                'score' => $scores[$app->id]['score'] ?? null,
                'grade' => $scores[$app->id]['grade'] ?? null,
            ]),
            'findings' => $user->isSuperadmin() ? $applications
                ->flatMap(fn (Application $app) => collect(AgentInfo::findings($app))->map(fn ($f) => [...$f, 'app' => $app->name, 'app_id' => $app->id]))
                ->values() : [],
            'traffic' => $metrics->requestSeries(now()->subDay(), $ids),
            'openIncidents' => $openIncidents->map(fn (Incident $i) => [
                'id' => $i->id,
                'app' => $i->application?->name,
                'started_at' => $i->started_at,
                'cause' => $i->cause,
            ]),
            'feed' => $feed->latest($ids, 8, ['audit', 'login', 'error']),
            'costs' => $user->isSuperadmin() ? Arr::only($costs->monthly($applications), ['total_projected', 'total_to_date', 'month_progress']) : null,
            'recentAlerts' => Alert::with('application:id,name')
                ->whereIn('application_id', $ids)
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }
}
