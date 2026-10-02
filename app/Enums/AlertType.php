<?php

namespace App\Enums;

enum AlertType: string
{
    case Down = 'down';
    case Recovered = 'recovered';
    case Degraded = 'degraded';
    case ErrorSpike = 'error_spike';
    case FailedLogins = 'failed_logins';
    case MassDelete = 'mass_delete';
    case DeployFailed = 'deploy_failed';

    public function label(): string
    {
        return match ($this) {
            self::Down => 'Caída',
            self::Recovered => 'Recuperada',
            self::Degraded => 'Degradada',
            self::ErrorSpike => 'Pico de errores',
            self::FailedLogins => 'Intentos fallidos de login',
            self::MassDelete => 'Eliminación masiva',
            self::DeployFailed => 'Despliegue fallido',
        };
    }

    public function severity(): string
    {
        return match ($this) {
            self::Down, self::FailedLogins, self::MassDelete => 'critical',
            self::ErrorSpike, self::DeployFailed, self::Degraded => 'warning',
            self::Recovered => 'info',
        };
    }
}
