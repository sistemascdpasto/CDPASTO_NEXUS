<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\AppSession;
use App\Models\Deployment;
use App\Models\ErrorEvent;
use App\Models\HealthCheck;
use App\Models\Incident;
use App\Services\ActivityFeed;
use App\Services\HealthScore;
use App\Services\Metrics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Centro de operaciones: vista de pantalla completa pensada para un monitor de pared.
 */
class NocController extends Controller
{
    public function __invoke(Request $request, Metrics $metrics, HealthScore $scores, ActivityFeed $feed): Response
    {
        $applications = Application::visibleTo($request->user())->active()->orderBy('name')->get();
        $ids = $applications->pluck('id')->all();

        $sessions = AppSession::whereIn('application_id', $ids)
            ->where('last_activity_at', '>=', now()->subMinutes(config('nexus.active_session_minutes')))
            ->groupBy('application_id')->selectRaw('application_id, COUNT(DISTINCT external_user_id) as total')
            ->pluck('total', 'application_id');

        $errors = ErrorEvent::whereIn('application_id', $ids)->where('occurred_at', '>=', today())
            ->groupBy('application_id')->selectRaw('application_id, COUNT(*) as total')->pluck('total', 'application_id');

        // Últimos 40 checks por app para la mini-gráfica de latencia.
        $checks = HealthCheck::whereIn('application_id', $ids)->where('checked_at', '>=', now()->subHours(2))
            ->orderBy('checked_at')->get(['application_id', 'status', 'response_ms', 'checked_at'])
            ->groupBy('application_id');

        $lastDeploys = Deployment::whereIn('application_id', $ids)
            ->whereIn('id', Deployment::selectRaw('MAX(id)')->groupBy('application_id'))
            ->get()->keyBy('application_id');

        $openIncidents = Incident::whereIn('application_id', $ids)->open()->get()->keyBy('application_id');
        $healthScores = $scores->forApplications($applications);
        $uptime = $metrics->uptimeByApp(now()->subDay(), $ids);

        return Inertia::render('noc', [
            'systems' => $applications->map(fn (Application $app) => [
                'id' => $app->id,
                'name' => $app->name,
                'type' => $app->type,
                'status' => $app->status,
                'response_ms' => $app->last_response_ms,
                'uptime_24h' => $uptime[$app->id] ?? null,
                'score' => $healthScores[$app->id]['score'] ?? null,
                'active_users' => (int) ($sessions[$app->id] ?? 0),
                'errors_today' => (int) ($errors[$app->id] ?? 0),
                'latency' => ($checks[$app->id] ?? collect())->slice(-40)->map(fn ($c) => ['ms' => $c->response_ms, 'status' => $c->status])->values(),
                'incident_since' => $openIncidents->get($app->id)?->started_at,
                'last_deploy' => isset($lastDeploys[$app->id]) ? [
                    'status' => $lastDeploys[$app->id]->status,
                    'at' => $lastDeploys[$app->id]->deployed_at,
                    'message' => $lastDeploys[$app->id]->commit_message,
                ] : null,
                'maintenance' => (bool) ($app->last_info['environment']['maintenance'] ?? false),
            ]),
            'feed' => $feed->latest($ids, 14),
            'refreshedAt' => now(),
        ]);
    }
}
