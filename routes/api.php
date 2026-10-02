<?php

use App\Http\Controllers\Api\IngestController;
use Illuminate\Support\Facades\Route;

// Endpoints que consume el agente instalado en cada app.
Route::prefix('v1')->middleware(['agent', 'throttle:agent'])->group(function () {
    Route::post('ingest', IngestController::class)->name('api.ingest');
});
