<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AppUserController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CostController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\ErrorController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\LoginEventController;
use App\Http\Controllers\NocController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\PanelAuditController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('home');

Route::middleware(['auth', 'active', '2fa'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('noc', NocController::class)->name('noc');
    Route::get('activity', ActivityController::class)->name('activity');
    Route::get('search', SearchController::class)->middleware('throttle:60,1')->name('search');

    Route::get('incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::patch('incidents/{incident}', [IncidentController::class, 'update'])->name('incidents.update');

    // Aplicaciones
    Route::resource('applications', ApplicationController::class)->except('destroy');
    Route::delete('applications/{application}', [ApplicationController::class, 'destroy'])
        ->middleware('password.confirm')->name('applications.destroy');
    Route::post('applications/{application}/toggle', [ApplicationController::class, 'toggle'])->name('applications.toggle');
    Route::post('applications/{application}/regenerate-key', [ApplicationController::class, 'regenerateKey'])
        ->middleware('password.confirm')->name('applications.regenerate-key');
    Route::post('applications/{application}/check', [ApplicationController::class, 'check'])
        ->middleware('throttle:10,1')->name('applications.check');
    Route::post('applications/{application}/sync-railway', [ApplicationController::class, 'syncRailway'])
        ->middleware('throttle:6,1')->name('applications.sync-railway');

    // Directorio de usuarios de cada app (desde su base de datos) y vista 360 de cada uno
    Route::get('applications/{application}/users', [AppUserController::class, 'index'])->name('applications.users');
    Route::get('applications/{application}/users/{user}', [AppUserController::class, 'show'])->name('applications.users.show');

    // Usuarios de las apps: sesiones activas y acciones remotas
    Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');
    Route::post('applications/{application}/commands', [SessionController::class, 'command'])
        ->middleware('throttle:20,1')->name('applications.commands');

    Route::get('logins', [LoginEventController::class, 'index'])->name('logins.index');

    // Errores
    Route::get('errors', [ErrorController::class, 'index'])->name('errors.index');
    Route::get('errors/{error}', [ErrorController::class, 'show'])->name('errors.show');
    Route::post('errors/{error}/resolve', [ErrorController::class, 'resolve'])->name('errors.resolve');

    // Auditoría de las apps
    Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
    Route::get('audit/export/{format}', [AuditLogController::class, 'export'])
        ->middleware('throttle:10,1')->name('audit.export');

    // Alertas
    Route::get('alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::post('alerts/acknowledge-all', [AlertController::class, 'acknowledgeAll'])->name('alerts.acknowledge-all');
    Route::post('alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge'])->name('alerts.acknowledge');

    // Administración del panel (solo superadmin)
    Route::middleware('can:superadmin')->group(function () {
        Route::resource('users', UserController::class)->except('show');
        Route::post('users/{user}/reset-two-factor', [UserController::class, 'resetTwoFactor'])->name('users.reset-two-factor');
        Route::get('panel-audit', PanelAuditController::class)->name('panel-audit.index');
        Route::get('costs', CostController::class)->name('costs');

        // Operaciones de superusuario sobre cada app
        Route::get('applications/{application}/database', [DatabaseController::class, 'show'])->name('applications.database');
        Route::post('applications/{application}/info', [OperationsController::class, 'refreshInfo'])
            ->middleware('throttle:10,1')->name('applications.info');
        Route::post('applications/{application}/operations', [OperationsController::class, 'command'])
            ->middleware('throttle:10,1')->name('applications.operations');
        Route::post('applications/{application}/railway/restart', [OperationsController::class, 'restart'])
            ->middleware('throttle:3,1')->name('applications.railway.restart');
        Route::post('applications/{application}/railway/redeploy', [OperationsController::class, 'redeploy'])
            ->middleware('throttle:3,1')->name('applications.railway.redeploy');
        Route::get('applications/{application}/deployments/{deployment}/logs', [OperationsController::class, 'logs'])
            ->middleware('throttle:20,1')->name('applications.deployments.logs');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
