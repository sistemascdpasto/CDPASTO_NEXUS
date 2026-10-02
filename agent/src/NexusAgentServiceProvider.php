<?php

namespace Cdpasto\NexusAgent;

use Cdpasto\NexusAgent\Console\FlushCommand;
use Cdpasto\NexusAgent\Http\Middleware\EnforceBlocks;
use Cdpasto\NexusAgent\Http\Middleware\RecordRequest;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Throwable;

class NexusAgentServiceProvider extends ServiceProvider
{
    public const VERSION = '1.1.0';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nexus_agent.php', 'nexus_agent');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/nexus.php');

        $this->publishes([__DIR__.'/../config/nexus_agent.php' => config_path('nexus_agent.php')], 'nexus-config');

        if ($this->app->runningInConsole()) {
            $this->commands([FlushCommand::class]);

            // Si la app tiene scheduler, también envía cada minuto aunque no haya tráfico web.
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
                $schedule->command('nexus:flush')->everyMinute()->withoutOverlapping(5)->runInBackground();
            });
        }

        $this->registerMiddleware();
        $this->registerAuthListeners();
        $this->registerAuditListeners();
        $this->registerExceptionReporting();
    }

    private function registerMiddleware(): void
    {
        // Nexus debe poder consultar y reactivar la app aunque esté en mantenimiento.
        PreventRequestsDuringMaintenance::except(['nexus/*']);

        $this->app->make(Kernel::class)->pushMiddleware(RecordRequest::class);

        $this->app->booted(function () {
            $this->app->make(Router::class)->pushMiddlewareToGroup('web', EnforceBlocks::class);
        });
    }

    private function registerAuthListeners(): void
    {
        Event::listen(Login::class, function (Login $event) {
            Recorder::login('login', $event->user);

            // Un usuario bloqueado que logre autenticarse es expulsado de inmediato.
            if (Blocks::isBlocked((string) $event->user->getAuthIdentifier())) {
                Recorder::login('blocked', $event->user);
                Auth::guard($event->guard)->logout();
            }
        });

        Event::listen(Failed::class, fn (Failed $event) => Recorder::login('failed', $event->user, $event->credentials));
        Event::listen(Logout::class, fn (Logout $event) => $event->user ? Recorder::login('logout', $event->user) : null);
        Event::listen(Lockout::class, fn (Lockout $event) => Recorder::login('lockout', null, $event->request->except(['password', '_token'])));
    }

    private function registerAuditListeners(): void
    {
        if (! config('nexus_agent.audit.enabled')) {
            return;
        }

        foreach (['created', 'updated', 'deleted', 'restored'] as $action) {
            Event::listen("eloquent.{$action}: *", function (string $event, array $data) use ($action) {
                if (($model = $data[0] ?? null) instanceof Model) {
                    // Un soft delete dispara "deleted": se registra igual como eliminación.
                    Recorder::model($action, $model);
                }
            });
        }
    }

    private function registerExceptionReporting(): void
    {
        $this->callAfterResolving(ExceptionHandler::class, function ($handler) {
            if (method_exists($handler, 'reportable')) {
                $handler->reportable(function (Throwable $e) {
                    Recorder::exception($e);
                });
            }
        });
    }
}
