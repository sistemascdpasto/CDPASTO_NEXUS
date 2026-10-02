<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\AppSession;
use App\Models\AuditLog;
use App\Models\ErrorEvent;
use App\Models\LoginEvent;
use App\Models\UserActivity;
use App\Services\DatabaseInspector;
use App\Services\RemoteCommander;
use App\Support\Sql;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Directorio de usuarios de una app (leído de su base de datos) y vista 360 de cada usuario.
 */
class AppUserController extends Controller
{
    public function index(Request $request, Application $application, DatabaseInspector $inspector): Response
    {
        Gate::authorize('view-application', $application);

        $filters = $request->only(['search', 'status']);
        $directory = null;
        $error = null;

        if ($application->hasDatabase()) {
            try {
                $directory = $inspector->users($application, $filters['search'] ?? null, max(1, (int) $request->query('page', 1)), 30, $filters['status'] ?? null);
            } catch (Throwable $e) {
                report($e);
                $error = $e->getMessage();
            }
        } else {
            $error = 'Esta app no tiene una base de datos de Railway vinculada. Se muestran solo los usuarios vistos por el agente.';
        }

        // Sin base vinculada: usuarios que Nexus conoce por logins y actividad.
        $known = $directory ? null : LoginEvent::where('application_id', $application->id)
            ->whereNotNull('external_user_id')
            ->groupBy('external_user_id')
            ->selectRaw('external_user_id as id, MAX(user_name) as name, MAX(identifier) as email, MAX(occurred_at) as last_login_at')
            ->orderByDesc('last_login_at')
            ->limit(200)
            ->get();

        return Inertia::render('applications/users', [
            'application' => $application->only(['id', 'name', 'database_service_name']),
            'directory' => $directory,
            'known' => $known,
            'filters' => $filters,
            'error' => $error,
            'canOperate' => $request->user()->can('operate-application', $application),
            'blockedIds' => app(RemoteCommander::class)->activeBlocks([$application->id])->pluck('external_user_id'),
        ]);
    }

    public function show(Request $request, Application $application, string $user, DatabaseInspector $inspector, RemoteCommander $commander): Response
    {
        Gate::authorize('view-application', $application);

        $profile = null;
        $profileError = null;

        if ($application->hasDatabase()) {
            try {
                $profile = $inspector->user($application, $user);
            } catch (Throwable $e) {
                report($e);
                $profileError = $e->getMessage();
            }
        }

        // Solo el superadmin ve la fila completa de la base de datos.
        if ($profile && ! $request->user()->isSuperadmin()) {
            unset($profile['raw']);
        }

        $scope = fn ($query) => $query->where('application_id', $application->id)->where('external_user_id', $user);
        $day = Sql::dayBucket('bucket');

        $name = $profile['name']
            ?? LoginEvent::where('application_id', $application->id)->where('external_user_id', $user)->whereNotNull('user_name')->latest('occurred_at')->value('user_name')
            ?? AuditLog::where('application_id', $application->id)->where('external_user_id', $user)->whereNotNull('user_name')->latest('occurred_at')->value('user_name')
            ?? "Usuario #{$user}";

        $actionFilter = $request->query('action');

        return Inertia::render('applications/user-show', [
            'application' => $application->only(['id', 'name']),
            'externalId' => $user,
            'name' => $name,
            'profile' => $profile,
            'profileError' => $profileError,
            'canOperate' => $request->user()->can('operate-application', $application),
            'blocked' => $commander->activeBlocks([$application->id])->firstWhere('external_user_id', $user),
            'sessions' => $scope(AppSession::query())->orderByDesc('last_activity_at')->get(),
            'stats' => [
                'logins_30d' => $scope(LoginEvent::query())->where('event', 'login')->where('occurred_at', '>=', now()->subDays(30))->count(),
                'failed_30d' => LoginEvent::where('application_id', $application->id)
                    ->where('event', 'failed')
                    ->where('occurred_at', '>=', now()->subDays(30))
                    ->where(fn ($q) => $q->where('external_user_id', $user)->when($profile['email'] ?? null, fn ($q, $email) => $q->orWhere('identifier', $email))->when($profile['document'] ?? null, fn ($q, $doc) => $q->orWhere('identifier', $doc)))
                    ->count(),
                'actions_30d' => $scope(AuditLog::query())->where('occurred_at', '>=', now()->subDays(30))->groupBy('action')->selectRaw('action, COUNT(*) as total')->pluck('total', 'action'),
                'errors_30d' => $scope(ErrorEvent::query())->where('occurred_at', '>=', now()->subDays(30))->count(),
            ],
            'activity' => $scope(UserActivity::query())
                ->where('bucket', '>=', now()->subDays(29)->startOfDay())
                ->groupByRaw($day)->orderByRaw($day)
                ->selectRaw("{$day} as date, SUM(requests) as requests")
                ->get()
                ->map(fn ($row) => ['date' => $row->date, 'requests' => (int) $row->requests]),
            'topRoutes' => collect($scope(UserActivity::query())
                ->where('bucket', '>=', now()->subDays(30))
                ->pluck('routes')
                ->reduce(function (array $carry, $routes) {
                    foreach ($routes ?? [] as $route => $count) {
                        $carry[$route] = ($carry[$route] ?? 0) + $count;
                    }

                    return $carry;
                }, []))->sortDesc()->take(15),
            'logins' => LoginEvent::where('application_id', $application->id)
                ->where(fn ($q) => $q->where('external_user_id', $user)->when($profile['email'] ?? null, fn ($q, $email) => $q->orWhere('identifier', $email))->when($profile['document'] ?? null, fn ($q, $doc) => $q->orWhere('identifier', $doc)))
                ->latest('occurred_at')->limit(25)->get(),
            'audits' => $scope(AuditLog::query())
                ->when($actionFilter, fn ($q, $action) => $q->where('action', $action))
                ->latest('occurred_at')->latest('id')
                ->paginate(20, ['*'], 'audit_page')
                ->withQueryString(),
            'actionFilter' => $actionFilter,
            'errors' => $scope(ErrorEvent::query())->with('group:id,exception_class,message')->latest('occurred_at')->limit(10)->get(),
        ]);
    }
}
