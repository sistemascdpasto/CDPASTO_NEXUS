<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\Application;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        // Railway termina HTTPS en su proxy: las URLs generadas deben salir siempre con https.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // Gestión del panel: apps, usuarios del panel, auditoría del panel.
        Gate::define('superadmin', fn (User $user) => $user->isSuperadmin());

        Gate::define('view-application', fn (User $user, Application $application) => $user->canAccessApplication($application));

        // Cerrar sesiones, bloquear usuarios y resolver errores: superadmin o jefe asignado.
        Gate::define('operate-application', fn (User $user, Application $application) => $user->isSuperadmin()
            || ($user->role === UserRole::Jefe && $user->canAccessApplication($application)));

        RateLimiter::for('agent', fn (Request $request) => Limit::perMinute(120)->by($request->bearerToken() ?: $request->ip()));
    }
}
