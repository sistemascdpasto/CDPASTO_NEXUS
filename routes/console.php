<?php

use Illuminate\Support\Facades\Schedule;

// Cada app define su propio intervalo; el comando solo revisa las que ya toca.
Schedule::command('nexus:check-health')->everyMinute()->withoutOverlapping(5);

Schedule::command('nexus:sync-railway')->everyFiveMinutes()->withoutOverlapping(10);

Schedule::command('nexus:collect-info')->hourly()->withoutOverlapping(10);

Schedule::command('nexus:prune')->dailyAt('03:30');

Schedule::command('nexus:weekly-report')->weeklyOn(1, '07:00');

// Notificaciones encoladas (correo / WhatsApp) sin necesitar un worker aparte. Va al final y sin
// runInBackground(): en Railway el contenedor no tiene init, los procesos en segundo plano quedan
// zombis y al agotarse el límite de procesos el scheduler no puede ni resolver DNS.
Schedule::command('queue:work --stop-when-empty --max-time=35 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(2);
