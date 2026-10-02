<?php

use Illuminate\Support\Facades\Schedule;

// Cada app define su propio intervalo; el comando solo revisa las que ya toca.
Schedule::command('nexus:check-health')->everyMinute()->withoutOverlapping(5);

Schedule::command('nexus:sync-railway')->everyFiveMinutes()->withoutOverlapping(10);

Schedule::command('nexus:collect-info')->hourly()->withoutOverlapping(10);

Schedule::command('nexus:prune')->dailyAt('03:30');

Schedule::command('nexus:weekly-report')->weeklyOn(1, '07:00');

// Notificaciones encoladas (correo / WhatsApp) sin necesitar un worker aparte.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->runInBackground();
