<?php

namespace Cdpasto\NexusAgent;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Ubicación del usuario conectado (trazabilidad).
 *
 * El navegador la envía al iniciar sesión y cada 10 minutos (embed.js, con permiso del
 * usuario). Si no hay permiso se usa la ciudad aproximada por IP. Se guarda en la sesión
 * —para que la app la use, p. ej. en la marca de agua de las pruebas— y se envía a Nexus.
 *
 * Uso desde la app:
 *   Location::current()   // ['city' => 'Ipiales', 'label' => 'Ipiales, Nariño · Colombia', ...] o null
 */
class Location
{
    public const SESSION_KEY = 'nexus_location';

    /** Cookie (legible por JavaScript) que marca «tomar la ubicación tras este login». */
    public const COOKIE = 'nexus_ubicacion';

    /**
     * @param  array{latitude?: float|null, longitude?: float|null, accuracy?: float|null}  $coords
     * @return array<string, mixed>
     */
    public static function record(Request $request, array $coords): array
    {
        $ip = self::clientIp($request);
        $hasCoords = isset($coords['latitude'], $coords['longitude']);

        if ($hasCoords) {
            $place = Geocoder::reverse((float) $coords['latitude'], (float) $coords['longitude']);
            $location = [
                'source' => 'gps',
                'latitude' => round((float) $coords['latitude'], 7),
                'longitude' => round((float) $coords['longitude'], 7),
                'accuracy' => isset($coords['accuracy']) ? round((float) $coords['accuracy']) : null,
                ...$place,
            ];
        } else {
            $place = Geocoder::ip($ip);
            $location = ['source' => 'ip', 'accuracy' => null, ...$place];
        }

        $location['label'] = self::label($location);
        $location['at'] = now()->toIso8601String();
        $request->session()->put(self::SESSION_KEY, $location);

        $user = $request->user();
        Recorder::push('location', [
            'user_id' => $user ? (string) $user->getAuthIdentifier() : null,
            'user_name' => Recorder::userName($user),
            'source' => $location['source'],
            'latitude' => $location['latitude'] ?? null,
            'longitude' => $location['longitude'] ?? null,
            'accuracy' => $location['accuracy'],
            'city' => $location['city'],
            'region' => $location['region'],
            'country' => $location['country'],
            'ip' => $ip,
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'occurred_at' => $location['at'],
        ]);

        return $location;
    }

    /**
     * Última ubicación conocida del usuario en esta sesión.
     *
     * @return array<string, mixed>|null
     */
    public static function current(): ?array
    {
        try {
            $value = request()->hasSession() ? request()->session()->get(self::SESSION_KEY) : null;
        } catch (\Throwable) {
            return null;
        }

        return is_array($value) ? $value : null;
    }

    /**
     * «Ipiales, Nariño · Colombia» (o lo que se conozca).
     *
     * @param  array<string, mixed>  $location
     */
    public static function label(array $location): ?string
    {
        $head = collect([$location['city'] ?? null, $location['region'] ?? null])->filter()->unique()->implode(', ');
        $country = $location['country'] ?? null;

        return trim($head.($head && $country ? ' · ' : '').($country ?? '')) ?: null;
    }

    public static function flagLogin(): void
    {
        // httpOnly = false: la lee el script del navegador. Solo dice «tomar ubicación», no lleva datos.
        Cookie::queue(Cookie::make(self::COOKIE, '1', 10, '/', null, null, false, false, 'lax'));
    }

    /**
     * IP real del usuario detrás del proxy de Railway.
     */
    public static function clientIp(Request $request): ?string
    {
        $forwarded = trim(explode(',', (string) $request->header('X-Forwarded-For'))[0] ?? '');

        return filter_var($forwarded, FILTER_VALIDATE_IP) ? $forwarded : $request->ip();
    }
}
