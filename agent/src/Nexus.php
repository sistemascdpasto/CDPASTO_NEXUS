<?php

namespace Cdpasto\NexusAgent;

/**
 * API pública para registrar eventos que no son cambios de modelos.
 *
 *   Nexus::audit('exported', 'Reporte de asistencia');
 *   Nexus::audit('viewed', 'Hoja de vida', $colaborador->id);
 */
class Nexus
{
    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public static function audit(string $action, string $module, int|string|null $recordId = null, ?array $old = null, ?array $new = null): void
    {
        Recorder::audit($action, $module, $recordId, $old, $new);
    }
}
