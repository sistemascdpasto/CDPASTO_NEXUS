<?php

namespace App\Enums;

enum UserRole: string
{
    /** Control total: apps, usuarios del panel, alertas. */
    case Superadmin = 'superadmin';

    /** Jefe de sistema: ve sus apps asignadas y puede cerrar sesiones / bloquear usuarios. */
    case Jefe = 'jefe';

    /** Encargado de solo lectura sobre sus apps asignadas. */
    case Lector = 'lector';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadministrador',
            self::Jefe => 'Jefe / Encargado',
            self::Lector => 'Solo lectura',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $role) => ['value' => $role->value, 'label' => $role->label()], self::cases());
    }
}
