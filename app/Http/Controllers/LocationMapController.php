<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\UserLocation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mapa de dónde están conectados los usuarios de las apps.
 */
class LocationMapController extends Controller
{
    private const PERIODS = ['now' => 15, '24h' => 1440, '7d' => 10080];

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $period = array_key_exists($request->query('period'), self::PERIODS) ? $request->query('period') : 'now';
        $appId = $request->integer('app') ?: null;
        $since = now()->subMinutes(self::PERIODS[$period]);

        $base = UserLocation::query()
            ->visibleTo($user)
            ->where('recorded_at', '>=', $since)
            ->when($appId, fn ($q) => $q->where('application_id', $appId));

        // Última ubicación de cada usuario en cada app dentro del periodo.
        $latestIds = (clone $base)
            ->selectRaw('MAX(id) as id')
            ->groupBy('application_id', 'external_user_id')
            ->pluck('id');

        $points = UserLocation::query()
            ->with('application:id,name,slug')
            ->whereIn('id', $latestIds)
            ->orderByDesc('recorded_at')
            ->limit(2000)
            ->get()
            ->map(fn (UserLocation $l) => [
                'id' => $l->id,
                'application' => $l->application?->only(['id', 'name', 'slug']),
                'user_id' => $l->external_user_id,
                'user_name' => $l->user_name,
                'source' => $l->source,
                'latitude' => $l->latitude,
                'longitude' => $l->longitude,
                'accuracy_m' => $l->accuracy_m,
                'place' => $l->place(),
                'city' => $l->city,
                'device' => $l->device,
                'ip' => $l->ip,
                'recorded_at' => $l->recorded_at,
            ]);

        $byCity = $points->groupBy(fn ($p) => $p['city'] ?: 'Desconocida')
            ->map(fn ($group, $city) => ['city' => $city, 'users' => $group->count()])
            ->sortByDesc('users')
            ->values()
            ->take(12);

        return Inertia::render('locations/map', [
            'points' => $points,
            'byCity' => $byCity,
            'stats' => [
                'users' => $points->count(),
                'gps' => $points->where('source', 'gps')->count(),
                'ip' => $points->where('source', 'ip')->count(),
                'cities' => $points->pluck('city')->filter()->unique()->count(),
            ],
            'applications' => Application::visibleTo($user)->active()->where('type', Application::TYPE_LARAVEL)->orderBy('name')->get(['id', 'name', 'slug']),
            'filters' => ['period' => $period, 'app' => $appId],
        ]);
    }
}
