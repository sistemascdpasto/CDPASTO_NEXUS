<?php

namespace Cdpasto\NexusAgent;

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Anuncios configurados en Nexus para esta app.
 *
 * Nexus los envía en la respuesta de cada ingesta (cada minuto) y aquí se guardan en
 * nexus_state, así que mostrarlos no depende de que Nexus responda en ese instante.
 */
class Announcements
{
    public const STATE_KEY = 'announcements';

    /** Cookie (legible por JavaScript) que marca «hay que mostrar anuncios tras este login». */
    public const COOKIE = 'nexus_anuncio';

    /** @var array{embed_url: ?string, app: ?string, items: list<array<string, mixed>>}|null */
    private static ?array $cache = null;

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public static function store(?array $payload): void
    {
        if (! is_array($payload)) {
            return;
        }

        $value = json_encode([
            'embed_url' => is_string($payload['embed_url'] ?? null) ? $payload['embed_url'] : null,
            'app' => is_string($payload['app'] ?? null) ? $payload['app'] : null,
            'items' => array_values(array_filter((array) ($payload['items'] ?? []), 'is_array')),
        ]);

        // Se reporta si falla: un error silencioso aquí deja a la app sin anuncios.
        rescue(fn () => DB::table('nexus_state')->updateOrInsert(
            ['key' => self::STATE_KEY],
            ['value' => $value, 'updated_at' => now()],
        ));

        self::$cache = null;
    }

    /**
     * @return array{embed_url: ?string, app: ?string, items: list<array<string, mixed>>}
     */
    public static function current(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $empty = ['embed_url' => null, 'app' => null, 'items' => []];

        try {
            $raw = DB::table('nexus_state')->where('key', self::STATE_KEY)->value('value');
            $data = $raw ? json_decode($raw, true) : null;
        } catch (Throwable) {
            $data = null;
        }

        return self::$cache = is_array($data) ? [...$empty, ...$data] : $empty;
    }

    public static function any(): bool
    {
        $current = self::current();

        return $current['embed_url'] !== null && $current['items'] !== [];
    }

    /**
     * Al iniciar sesión: el script de la página verá la cookie y mostrará los anuncios.
     */
    public static function flagLogin(): void
    {
        if (! self::any()) {
            return;
        }

        // httpOnly = false: la lee el script de anuncios. Solo dice «mostrar», no lleva datos.
        Cookie::queue(Cookie::make(self::COOKIE, '1', 10, '/', null, null, false, false, 'lax'));
    }

    /**
     * Etiqueta <script> que se inserta en las páginas HTML de la app: muestra los anuncios tras
     * el login y toma la ubicación del usuario conectado (al entrar y cada 10 minutos).
     */
    public static function scriptTag(): ?string
    {
        $current = self::current();

        if ($current['embed_url'] === null) {
            return null;
        }

        try {
            $authenticated = auth()->check();
        } catch (\Throwable) {
            $authenticated = false;
        }

        return sprintf(
            '<script src="%s" data-nexus-modo="sesion" data-nexus-app="%s" data-nexus-endpoint="%s" data-nexus-anuncios="%d" data-nexus-ubicacion="%s" data-nexus-sesion="%d" defer></script>',
            e($current['embed_url']),
            e((string) $current['app']),
            '/nexus/anuncios',
            self::any() ? 1 : 0,
            '/nexus/ubicacion',
            $authenticated ? 1 : 0,
        );
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
