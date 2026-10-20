<?php

use Cdpasto\NexusAgent\Http\Controllers\AnnouncementController;
use Cdpasto\NexusAgent\Http\Controllers\CommandController;
use Cdpasto\NexusAgent\Http\Controllers\HealthController;
use Cdpasto\NexusAgent\Http\Controllers\InfoController;
use Cdpasto\NexusAgent\Http\Controllers\LocationController;
use Cdpasto\NexusAgent\Http\Middleware\VerifyNexusSignature;
use Illuminate\Support\Facades\Route;

// Sin el grupo "web": no hay sesión ni CSRF. Toda llamada va firmada por Nexus.
Route::prefix('nexus')->middleware(VerifyNexusSignature::class)->group(function () {
    Route::get('health', HealthController::class);
    Route::get('info', InfoController::class);
    Route::post('commands', CommandController::class);
});

// Anuncios para el navegador del usuario (mismo dominio de la app; no requiere firma).
Route::get('nexus/anuncios', AnnouncementController::class)->middleware(['web', 'throttle:30,1']);

// Ubicación del usuario conectado (al iniciar sesión y cada 10 minutos).
Route::post('nexus/ubicacion', LocationController::class)->middleware(['web', 'throttle:20,1']);
