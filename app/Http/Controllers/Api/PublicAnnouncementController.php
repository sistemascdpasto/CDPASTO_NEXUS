<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementStat;
use App\Models\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Endpoints públicos (sin sesión) que consumen los navegadores de los usuarios: la imagen de
 * cada anuncio, el listado para la web principal y el conteo de vistas y clics.
 */
class PublicAnnouncementController extends Controller
{
    public function image(string $announcement): Response
    {
        $model = Announcement::where('uuid', $announcement)->firstOrFail();

        return response(base64_decode($model->image_data), 200, [
            'Content-Type' => $model->image_mime,
            // La URL lleva ?v=<fecha de edición>: si el anuncio cambia, cambia la URL.
            'Cache-Control' => 'public, max-age=2592000, immutable',
            'Access-Control-Allow-Origin' => '*',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Anuncios vigentes de una web sin agente (p. ej. la página principal).
     */
    public function feed(Request $request): JsonResponse
    {
        $application = Application::where('slug', (string) $request->query('app'))->active()->first();

        return response()->json(['data' => $application ? Announcement::feedFor($application) : []])
            ->header('Cache-Control', 'public, max-age=60')
            // La web principal está en otro dominio.
            ->header('Access-Control-Allow-Origin', '*');
    }

    /**
     * Vista o clic (navigator.sendBeacon desde el navegador del usuario).
     */
    public function event(Request $request, string $announcement): Response
    {
        $model = Announcement::where('uuid', $announcement)->first();
        $application = $model?->applications()->where('slug', (string) $request->query('app'))->first();

        if ($model && $application) {
            AnnouncementStat::bump($model, $application, $request->query('type') === 'click' ? 'click' : 'view');
        }

        return response()->noContent();
    }
}
