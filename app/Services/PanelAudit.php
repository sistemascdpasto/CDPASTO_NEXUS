<?php

namespace App\Services;

use App\Models\PanelAuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Trazabilidad de las acciones hechas dentro del propio panel (HU-24).
 */
class PanelAudit
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function log(string $action, string $description, ?Model $subject = null, array $properties = [], ?int $userId = null): PanelAuditLog
    {
        $request = request();

        return PanelAuditLog::create([
            'user_id' => $userId ?? $request->user()?->id,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);
    }
}
