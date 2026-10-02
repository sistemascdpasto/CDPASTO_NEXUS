<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Services\ActivityFeed;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Actividad en vivo de todas las apps en una sola línea de tiempo.
 */
class ActivityController extends Controller
{
    private const KINDS = ['audit', 'login', 'error', 'alert'];

    public function __invoke(Request $request, ActivityFeed $feed): Response
    {
        $user = $request->user();
        $applications = Application::visibleTo($user)->orderBy('name')->get(['id', 'name']);
        $appId = $request->integer('application_id') ?: null;
        $kinds = array_values(array_intersect(self::KINDS, (array) $request->query('kinds', self::KINDS))) ?: self::KINDS;

        $ids = $appId && $applications->contains('id', $appId) ? [$appId] : $applications->pluck('id')->all();

        return Inertia::render('activity/index', [
            'items' => $feed->latest($ids, 80, $kinds),
            'applications' => $applications,
            'filters' => ['application_id' => $appId, 'kinds' => $kinds],
            'refreshedAt' => now(),
        ]);
    }
}
