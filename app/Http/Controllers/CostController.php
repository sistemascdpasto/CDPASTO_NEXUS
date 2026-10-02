<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Services\CostEstimator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Costo estimado del mes en Railway, por servicio (app + su base de datos). Solo superadmin.
 */
class CostController extends Controller
{
    public function __invoke(CostEstimator $estimator): Response
    {
        return Inertia::render('costs/index', [
            ...$estimator->monthly(Application::whereNotNull('railway_service_id')->get()),
            'month' => now()->translatedFormat('F Y'),
        ]);
    }
}
