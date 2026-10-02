<?php

namespace Cdpasto\NexusAgent;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class Blocks
{
    private const CACHE_KEY = 'nexus-agent:blocked-users';

    public static function isBlocked(string $userId): bool
    {
        return array_key_exists($userId, self::all());
    }

    /**
     * Bloqueos vigentes, cacheados 60 s para no consultar la BD en cada petición.
     *
     * @return array<string, true>
     */
    public static function all(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, 60, fn () => DB::table('nexus_blocks')
                ->where(fn ($q) => $q->whereNull('blocked_until')->orWhere('blocked_until', '>', now()))
                ->pluck('user_id')
                ->mapWithKeys(fn ($id) => [(string) $id => true])
                ->all());
        } catch (Throwable) {
            return [];
        }
    }

    public static function block(string $userId, ?int $minutes, ?string $reason): void
    {
        DB::table('nexus_blocks')->updateOrInsert(['user_id' => $userId], [
            'blocked_until' => $minutes ? now()->addMinutes($minutes) : null,
            'reason' => $reason ? mb_substr($reason, 0, 255) : null,
            'created_at' => now(),
        ]);

        Cache::forget(self::CACHE_KEY);
    }

    public static function unblock(string $userId): void
    {
        DB::table('nexus_blocks')->where('user_id', $userId)->delete();

        Cache::forget(self::CACHE_KEY);
    }
}
