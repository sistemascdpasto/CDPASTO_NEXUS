<?php

namespace App\Support;

/**
 * Descripción corta del dispositivo a partir del User-Agent ("Chrome · Windows").
 */
class UserAgent
{
    public static function describe(?string $userAgent): ?string
    {
        if (! $userAgent) {
            return null;
        }

        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') => 'Opera',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Otro',
        };

        $os = match (true) {
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Otro',
        };

        $mobile = str_contains($userAgent, 'Mobile') ? ' (móvil)' : '';

        return "{$browser} · {$os}{$mobile}";
    }
}
