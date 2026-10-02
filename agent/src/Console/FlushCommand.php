<?php

namespace Cdpasto\NexusAgent\Console;

use Cdpasto\NexusAgent\Flusher;
use Cdpasto\NexusAgent\Recorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FlushCommand extends Command
{
    public const HEARTBEAT_KEY = 'scheduler_at';

    protected $signature = 'nexus:flush';

    protected $description = 'Envía a Nexus la telemetría pendiente y las sesiones activas';

    public function handle(): int
    {
        // Latido: si el scheduler deja de correr, Nexus lo detecta en el health check.
        rescue(fn () => DB::table('nexus_state')->updateOrInsert(
            ['key' => self::HEARTBEAT_KEY],
            ['value' => (string) now()->timestamp, 'updated_at' => now()],
        ), report: false);

        if (! Recorder::enabled()) {
            $this->warn('Agente Nexus desactivado: configura NEXUS_URL y NEXUS_KEY.');

            return self::SUCCESS;
        }

        $result = (new Flusher)->flush();

        $result['ok'] ? $this->info("{$result['sent']} eventos enviados a Nexus.") : $this->error($result['message']);

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
