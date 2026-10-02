<?php

namespace App\Enums;

enum AppStatus: string
{
    case Online = 'online';
    case Degraded = 'degraded';
    case Down = 'down';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'En línea',
            self::Degraded => 'Degradada',
            self::Down => 'Caída',
            self::Unknown => 'Sin datos',
        };
    }
}
