<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Services\DatabaseInspector;
use App\Services\PanelAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Explorador de solo lectura de la base de datos de una app en Railway (solo superadmin).
 */
class DatabaseController extends Controller
{
    public function show(Request $request, Application $application, DatabaseInspector $inspector): Response
    {
        $table = $request->query('table');
        $search = $request->query('search');
        $overview = null;
        $browse = null;
        $error = null;

        try {
            $overview = $request->boolean('refresh')
                ? tap($inspector->overview($application), fn ($o) => Cache::put("app-db-overview:{$application->id}", $o, now()->addMinutes(2)))
                : Cache::remember("app-db-overview:{$application->id}", now()->addMinutes(2), fn () => $inspector->overview($application));

            if ($table) {
                $browse = $inspector->browse($application, $table, max(1, (int) $request->query('page', 1)), $search);

                PanelAudit::log('database.browse', "Consultó la tabla {$table} de {$application->name}".($search ? " (búsqueda: {$search})" : ''), $application, [
                    'table' => $table,
                    'page' => $browse['page'],
                ]);
            }
        } catch (Throwable $e) {
            report($e);
            $error = $e->getMessage();
        }

        return Inertia::render('applications/database', [
            'application' => $application->only(['id', 'name', 'database_service_name']),
            'overview' => $overview,
            'browse' => $browse,
            'filters' => ['table' => $table, 'search' => $search],
            'error' => $error,
        ]);
    }
}
