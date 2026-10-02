<?php

use Cdpasto\NexusAgent\Http\Controllers\CommandController;
use Cdpasto\NexusAgent\Http\Controllers\HealthController;
use Cdpasto\NexusAgent\Http\Controllers\InfoController;
use Cdpasto\NexusAgent\Http\Middleware\VerifyNexusSignature;
use Illuminate\Support\Facades\Route;

// Sin el grupo "web": no hay sesión ni CSRF. Toda llamada va firmada por Nexus.
Route::prefix('nexus')->middleware(VerifyNexusSignature::class)->group(function () {
    Route::get('health', HealthController::class);
    Route::get('info', InfoController::class);
    Route::post('commands', CommandController::class);
});
