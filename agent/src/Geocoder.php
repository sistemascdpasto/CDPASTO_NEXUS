<?php

namespace Cdpasto\NexusAgent;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Convierte coordenadas o una IP en ciudad / departamento / país.
 *
 * - Coordenadas → Nominatim (OpenStreetMap). Su política pide identificarse y no repetir
 *   consultas: se cachea por zona de ~1 km durante 30 días.
 * - IP → ipwho.is, cacheado 7 días por IP. Solo se usa si el usuario no dio permiso de
 *   ubicación (resultado aproximado: suele acertar la ciudad, no la calle).
 */
class Geocoder
{
    /**
     * @return array{city: ?string, region: ?string, country: ?string}
     */
    public static function reverse(float $latitude, float $longitude): array
    {
        $key = sprintf('nexus-agent:geo:%.2f,%.2f', $latitude, $longitude);

        return Cache::remember($key, now()->addDays(30), function () use ($latitude, $longitude) {
            try {
                $response = Http::timeout(5)
                    ->withHeaders(['User-Agent' => 'CDPasto-NexusAgent/'.NexusAgentServiceProvider::VERSION.' ('.config('app.url').')'])
                    ->get('https://nominatim.openstreetmap.org/reverse', [
                        'format' => 'jsonv2',
                        'lat' => $latitude,
                        'lon' => $longitude,
                        'zoom' => 10,
                        'accept-language' => 'es',
                    ]);

                $address = $response->json('address') ?? [];

                return [
                    'city' => $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['municipality'] ?? $address['county'] ?? null,
                    'region' => $address['state'] ?? null,
                    'country' => $address['country'] ?? null,
                ];
            } catch (Throwable) {
                return ['city' => null, 'region' => null, 'country' => null];
            }
        });
    }

    /**
     * @return array{city: ?string, region: ?string, country: ?string, latitude: ?float, longitude: ?float}
     */
    public static function ip(?string $ip): array
    {
        $empty = ['city' => null, 'region' => null, 'country' => null, 'latitude' => null, 'longitude' => null];

        if (! $ip || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $empty;
        }

        return Cache::remember('nexus-agent:geoip:'.$ip, now()->addDays(7), function () use ($ip, $empty) {
            try {
                $data = Http::timeout(5)->get("https://ipwho.is/{$ip}", ['lang' => 'es'])->json();

                if (! ($data['success'] ?? false)) {
                    return $empty;
                }

                return [
                    'city' => $data['city'] ?? null,
                    'region' => $data['region'] ?? null,
                    'country' => $data['country'] ?? null,
                    'latitude' => isset($data['latitude']) ? (float) $data['latitude'] : null,
                    'longitude' => isset($data['longitude']) ? (float) $data['longitude'] : null,
                ];
            } catch (Throwable) {
                return $empty;
            }
        });
    }
}
