<?php

namespace Cdpasto\NexusAgent\Http\Controllers;

use Cdpasto\NexusAgent\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Recibe la ubicación del navegador (o el aviso de que no hay permiso) del usuario conectado.
 */
class LocationController
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! $request->user()) {
            return response()->json(['message' => 'Sin sesión.'], 401);
        }

        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ]);

        $location = Location::record($request, $data);
        Cookie::unqueue(Location::COOKIE);

        return response()
            ->json([
                'source' => $location['source'],
                'label' => $location['label'],
            ])
            ->header('Cache-Control', 'no-store')
            ->withCookie(Cookie::forget(Location::COOKIE));
    }
}
