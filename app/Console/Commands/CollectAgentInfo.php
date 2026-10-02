<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Services\AgentInfo;
use Illuminate\Console\Command;
use Throwable;

class CollectAgentInfo extends Command
{
    protected $signature = 'nexus:collect-info';

    protected $description = 'Actualiza la ficha técnica y de seguridad de las apps con agente';

    public function handle(AgentInfo $info): int
    {
        // Solo las apps cuyo agente ya envió datos (las que siguen en /up no tienen agente).
        $apps = Application::active()->where('type', Application::TYPE_LARAVEL)->whereNotNull('last_ingest_at')->get();

        foreach ($apps as $app) {
            try {
                $data = $info->refresh($app);
                $this->line("{$app->name}: ".count(AgentInfo::findings($app)).' hallazgos de seguridad, '.($data['failed_jobs']['total'] ?? 0).' trabajos fallidos');
            } catch (Throwable $e) {
                $this->warn("{$app->name}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
