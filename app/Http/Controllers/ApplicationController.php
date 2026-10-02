<?php

namespace App\Http\Controllers;

use App\Enums\AppStatus;
use App\Enums\UserRole;
use App\Http\Requests\ApplicationRequest;
use App\Models\Application;
use App\Models\AppSession;
use App\Models\User;
use App\Services\AgentInfo;
use App\Services\AppDatabase;
use App\Services\HealthChecker;
use App\Services\Metrics;
use App\Services\PanelAudit;
use App\Services\RailwayClient;
use App\Services\RailwaySync;
use App\Services\RemoteCommander;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ApplicationController extends Controller
{
    private const RANGES = ['24h' => 24, '7d' => 24 * 7, '30d' => 24 * 30];

    /**
     * Listado general con estado (HU-02).
     */
    public function index(Request $request, Metrics $metrics): Response
    {
        $user = $request->user();
        $status = $request->query('status');

        $applications = Application::query()
            ->visibleTo($user)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->withCount([
                'errorGroups as open_errors' => fn ($q) => $q->whereNull('resolved_at'),
                'sessions as active_users' => fn ($q) => $q->where('last_activity_at', '>=', now()->subMinutes(config('nexus.active_session_minutes'))),
            ])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $uptime = $metrics->uptimeByApp(now()->subDay(), $applications->pluck('id')->all());

        return Inertia::render('applications/index', [
            'applications' => $applications->map(fn (Application $app) => [
                ...$this->summary($app),
                'open_errors' => $app->open_errors,
                'active_users' => $app->active_users,
                'uptime_24h' => $uptime[$app->id] ?? null,
            ]),
            'filters' => ['status' => $status],
            'counts' => Application::visibleTo($user)->active()->toBase()
                ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function create(RailwayClient $railway): Response
    {
        Gate::authorize('superadmin');

        return Inertia::render('applications/form', $this->formData($railway, new Application([
            'type' => Application::TYPE_LARAVEL,
            'environment' => 'production',
            'check_interval_minutes' => 1,
            'slow_threshold_ms' => 3000,
            'error_threshold' => 20,
            'failed_login_threshold' => 10,
            'mass_delete_threshold' => 30,
            'is_active' => true,
        ])));
    }

    public function store(ApplicationRequest $request): RedirectResponse
    {
        $application = new Application(Arr::except($request->validated(), ['user_ids']));
        $key = $application->regenerateApiKey();
        $application->save();
        $application->users()->sync($request->validated('user_ids', []));

        PanelAudit::log('application.created', "Registró la app {$application->name}", $application);

        return redirect()->route('applications.show', $application)->with('apiKey', $key);
    }

    /**
     * Detalle de una app: salud, métricas, usuarios y eventos (HU-03).
     */
    public function show(Request $request, Application $application, Metrics $metrics, RemoteCommander $commander): Response
    {
        Gate::authorize('view-application', $application);

        $range = array_key_exists($request->query('range'), self::RANGES) ? $request->query('range') : '24h';
        $from = now()->subHours(self::RANGES[$range]);
        $id = $application->id;

        return Inertia::render('applications/show', [
            'application' => [
                ...$this->summary($application),
                'description' => $application->description,
                'repository' => $application->repository,
                'railway_service_name' => $application->railway_service_name,
                'health_url' => $application->healthUrl(),
                'check_interval_minutes' => $application->check_interval_minutes,
                'slow_threshold_ms' => $application->slow_threshold_ms,
                'error_threshold' => $application->error_threshold,
                'failed_login_threshold' => $application->failed_login_threshold,
                'mass_delete_threshold' => $application->mass_delete_threshold,
                'api_key_prefix' => $application->api_key_prefix,
                'last_ingest_at' => $application->last_ingest_at,
                'agent_version' => $application->agent_version,
                'components' => $application->last_components,
                'database_service_name' => $application->database_service_name,
                'has_database' => $application->hasDatabase(),
                'info' => $application->last_info,
                'info_at' => $application->last_info_at,
                'findings' => AgentInfo::findings($application),
            ],
            'range' => $range,
            'can' => [
                'manage' => $request->user()->can('superadmin'),
                'operate' => $request->user()->can('operate-application', $application),
            ],
            'apiKey' => session('apiKey'),
            'uptime' => [
                'day' => $metrics->uptime($id, now()->subDay()),
                'week' => $metrics->uptime($id, now()->subWeek()),
                'month' => $metrics->uptime($id, now()->subMonth()),
                'daily' => $metrics->dailyUptime($id, 30),
            ],
            'healthSeries' => $metrics->healthResponseSeries($id, $from),
            'recentChecks' => $application->healthChecks()->latest('checked_at')->limit(15)
                ->get(['id', 'status', 'http_status', 'response_ms', 'error', 'checked_at']),
            'requests' => [
                'totals' => $metrics->requestTotals($id, $from),
                'series' => $metrics->requestSeries($from, [$id], $range === '24h' ? 'hour' : 'day'),
                'slowest' => $metrics->slowestRoutes($id, $from),
            ],
            'resources' => $metrics->resourceSeries($id, $from),
            'deployments' => $application->deployments()->latest('deployed_at')->limit(10)->get(),
            'errors' => $application->errorGroups()->unresolved()->latest('last_seen_at')->limit(8)
                ->get(['id', 'exception_class', 'message', 'file', 'line', 'occurrences', 'last_seen_at']),
            'sessions' => AppSession::where('application_id', $id)
                ->where('last_activity_at', '>=', now()->subMinutes(config('nexus.active_session_minutes')))
                ->orderByDesc('last_activity_at')->get(),
            'blocks' => $commander->activeBlocks([$id])->map(fn ($cmd) => [
                'external_user_id' => $cmd->external_user_id,
                'target_name' => $cmd->target_name,
                'blocked_at' => $cmd->executed_at,
                'minutes' => $cmd->payload['minutes'] ?? null,
                'reason' => $cmd->payload['reason'] ?? null,
            ]),
            'logins' => $application->loginEvents()->latest('occurred_at')->limit(10)->get(),
            'audits' => $application->auditLogs()->latest('occurred_at')->limit(10)->get(),
            'alerts' => $application->alerts()->latest()->limit(8)->get(),
        ]);
    }

    public function edit(Application $application, RailwayClient $railway): Response
    {
        Gate::authorize('superadmin');

        return Inertia::render('applications/form', $this->formData($railway, $application));
    }

    public function update(ApplicationRequest $request, Application $application): RedirectResponse
    {
        $application->fill(Arr::except($request->validated(), ['user_ids']));
        $changes = $application->getDirty();

        if (array_key_exists('database_service_id', $changes)) {
            app(AppDatabase::class)->forget($application);
        }
        $application->save();
        $application->users()->sync($request->validated('user_ids', []));

        PanelAudit::log('application.updated', "Editó la app {$application->name}", $application, ['changes' => array_keys($changes)]);

        return redirect()->route('applications.show', $application)->with('success', 'Aplicación actualizada.');
    }

    public function toggle(Application $application): RedirectResponse
    {
        Gate::authorize('superadmin');

        $application->update(['is_active' => ! $application->is_active]);

        if (! $application->is_active) {
            $application->forceFill(['status' => AppStatus::Unknown])->save();
        }

        PanelAudit::log('application.toggled', ($application->is_active ? 'Activó' : 'Desactivó')." la app {$application->name}", $application);

        return back()->with('success', $application->is_active ? 'Aplicación activada.' : 'Aplicación desactivada: no se monitoreará.');
    }

    public function regenerateKey(Application $application): RedirectResponse
    {
        Gate::authorize('superadmin');

        $key = $application->regenerateApiKey();
        $application->save();

        PanelAudit::log('application.key_regenerated', "Regeneró la API key de {$application->name}", $application);

        return redirect()->route('applications.show', $application)->with('apiKey', $key);
    }

    public function destroy(Application $application): RedirectResponse
    {
        Gate::authorize('superadmin');

        PanelAudit::log('application.deleted', "Eliminó la app {$application->name} y toda su telemetría", null, ['id' => $application->id, 'name' => $application->name]);

        $application->delete();

        return redirect()->route('applications.index')->with('success', 'Aplicación eliminada.');
    }

    /**
     * Ejecuta un health check inmediato.
     */
    public function check(Application $application, HealthChecker $checker): RedirectResponse
    {
        Gate::authorize('view-application', $application);

        $check = $checker->check($application);

        return back()->with('success', "Verificación: {$check->status->label()}".($check->response_ms ? " ({$check->response_ms} ms)" : ''));
    }

    public function syncRailway(Application $application, RailwaySync $sync, RailwayClient $client): RedirectResponse
    {
        Gate::authorize('view-application', $application);

        if (! $client->isConfigured() || ! $application->railway_service_id) {
            return back()->with('error', 'La app no está vinculada a un servicio de Railway o falta configurar el token.');
        }

        try {
            $sync->syncDeployments($application);
            $sync->syncMetrics($application, now()->subDay());
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudo consultar Railway: '.$e->getMessage());
        }

        return back()->with('success', 'Datos de Railway actualizados.');
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Application $app): array
    {
        return [
            'id' => $app->id,
            'name' => $app->name,
            'slug' => $app->slug,
            'type' => $app->type,
            'url' => $app->url,
            'environment' => $app->environment,
            'is_active' => $app->is_active,
            'status' => $app->status,
            'last_status_code' => $app->last_status_code,
            'last_response_ms' => $app->last_response_ms,
            'last_checked_at' => $app->last_checked_at,
            'status_changed_at' => $app->status_changed_at,
            'railway_service_id' => $app->railway_service_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(RailwayClient $railway, Application $application): array
    {
        try {
            $services = $railway->services();
        } catch (Throwable $e) {
            report($e);
            $services = [];
        }

        return [
            'application' => $application->exists ? [
                ...$application->only([
                    'id', 'name', 'slug', 'description', 'type', 'url', 'internal_url', 'health_path', 'environment', 'railway_service_id',
                    'railway_service_name', 'database_service_id', 'database_service_name', 'repository', 'check_interval_minutes', 'slow_threshold_ms', 'error_threshold',
                    'failed_login_threshold', 'mass_delete_threshold', 'is_active',
                ]),
                'user_ids' => $application->users()->pluck('users.id'),
            ] : [
                ...$application->only([
                    'type', 'environment', 'check_interval_minutes', 'slow_threshold_ms', 'error_threshold',
                    'failed_login_threshold', 'mass_delete_threshold', 'is_active',
                ]),
                'user_ids' => [],
            ],
            'railwayServices' => $services,
            'users' => User::where('role', '!=', UserRole::Superadmin)->orderBy('name')->get(['id', 'name', 'email', 'role']),
        ];
    }
}
