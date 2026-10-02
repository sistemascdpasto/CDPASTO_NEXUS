<?php

namespace App\Http\Controllers;

use App\Enums\AppStatus;
use App\Models\Application;
use App\Models\Incident;
use App\Services\Metrics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Página de bienvenida pública con el estado de los sistemas en vivo.
 * Solo expone nombre, estado y disponibilidad: nada de URLs internas, usuarios ni errores.
 */
class WelcomeController extends Controller
{
    public function __invoke(Request $request, Metrics $metrics): Response
    {
        $status = Cache::remember('welcome-status', now()->addSeconds(30), function () use ($metrics) {
            $apps = Application::active()->orderBy('name')->get();
            $uptime = $metrics->uptimeByApp(now()->subDays(30), $apps->pluck('id')->all());

            $systems = $apps->map(fn (Application $app) => [
                'name' => $app->name,
                'status' => $app->status,
                'uptime_30d' => $uptime[$app->id] ?? null,
                'response_ms' => $app->last_response_ms,
                'daily' => collect($metrics->dailyUptime($app->id, 30))->map(fn ($d) => ['date' => $d['date'], 'uptime' => $d['uptime']])->all(),
            ]);

            $down = $apps->where('status', AppStatus::Down)->count();
            $degraded = $apps->where('status', AppStatus::Degraded)->count();
            $values = array_filter($uptime, fn ($v) => $v !== null);

            return [
                'systems' => $systems->values()->all(),
                'overall' => $down > 0 ? 'down' : ($degraded > 0 ? 'degraded' : 'online'),
                'uptime_avg' => $values ? round(array_sum($values) / count($values), 2) : null,
                'incidents_30d' => Incident::where('started_at', '>=', now()->subDays(30))->count(),
                'checked_at' => $apps->max('last_checked_at'),
            ];
        });

        return Inertia::render('welcome', [
            ...$status,
            'isAuthenticated' => $request->user() !== null,
        ]);
    }
}
