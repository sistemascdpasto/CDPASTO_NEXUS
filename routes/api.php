<?php

use App\Http\Controllers\Api\IngestController;
use App\Http\Controllers\Api\PublicAnnouncementController;
use Illuminate\Support\Facades\Route;

// Endpoints que consume el agente instalado en cada app.
Route::prefix('v1')->middleware(['agent', 'throttle:agent'])->group(function () {
    Route::post('ingest', IngestController::class)->name('api.ingest');
});

// Anuncios: endpoints públicos que consumen los navegadores de los usuarios de cada app.
Route::prefix('anuncios')->group(function () {
    Route::get('publicos', [PublicAnnouncementController::class, 'feed'])
        ->middleware('throttle:120,1')->name('announcements.feed');
    Route::get('{announcement}/imagen', [PublicAnnouncementController::class, 'image'])
        ->middleware('throttle:600,1')->name('announcements.image');
    Route::post('{announcement}/evento', [PublicAnnouncementController::class, 'event'])
        ->middleware('throttle:60,1')->name('announcements.event');
});
