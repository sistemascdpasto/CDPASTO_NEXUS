<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Services\HealthChecker;
use Illuminate\Console\Command;

class CheckHealth extends Command
{
    protected $signature = 'nexus:check-health {--all : Revisar todas las apps sin importar su intervalo}';

    protected $description = 'Consulta el endpoint de salud de las apps que corresponda según su intervalo';

    public function handle(HealthChecker $checker): int
    {
        $apps = Application::active()->get()
            ->when(! $this->option('all'), fn ($apps) => $apps->filter->isDueForCheck());

        $checker->checkMany($apps->values());

        foreach ($apps as $app) {
            $app->refresh();
            $this->line(sprintf('%-35s %-10s %5s ms', $app->name, $app->status->value, $app->last_response_ms ?? '—'));
        }

        return self::SUCCESS;
    }
}
