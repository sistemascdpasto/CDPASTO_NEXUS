<?php

namespace Cdpasto\NexusAgent\Console;

use Cdpasto\NexusAgent\Flusher;
use Cdpasto\NexusAgent\Recorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FlushCommand extends Command
{
    public const HEARTBEAT_KEY = 'scheduler_at';

    public const FAILURES_KEY = 'flush_failures';

    /**
     * Fallos seguidos tolerados antes de que el comando falle. Mientras Nexus no responde la
     * telemetría queda en nexus_buffer y se reenvía sola, así que un corte breve (un despliegue,
     * un reinicio) no debe reportarse como error de la aplicación.
     */
    public const TOLERATED_FAILURES = 15;

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

        if ($result['ok']) {
            $this->recordFailures(0);
            $this->info("{$result['sent']} eventos enviados a Nexus.");

            return self::SUCCESS;
        }

        $failures = $this->recordFailures(null);

        if ($failures < self::TOLERATED_FAILURES) {
            $this->warn("Nexus no respondió ({$failures} seguidos); se reintenta en el próximo minuto: {$result['message']}");

            return self::SUCCESS;
        }

        $this->error("Nexus lleva {$failures} intentos seguidos sin responder: {$result['message']}");

        return self::FAILURE;
    }

    /**
     * Reinicia (0) o incrementa (null) el contador de fallos seguidos y devuelve su valor.
     */
    private function recordFailures(?int $value): int
    {
        return rescue(function () use ($value) {
            $current = (int) DB::table('nexus_state')->where('key', self::FAILURES_KEY)->value('value');
            $next = $value ?? $current + 1;

            if ($next !== $current) {
                DB::table('nexus_state')->updateOrInsert(['key' => self::FAILURES_KEY], ['value' => (string) $next, 'updated_at' => now()]);
            }

            return $next;
        }, $value ?? 1, report: false);
    }
}
