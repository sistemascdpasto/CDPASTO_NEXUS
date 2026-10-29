<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IngestRequest;
use App\Models\Announcement;
use App\Services\TelemetryIngestor;
use Illuminate\Http\JsonResponse;

class IngestController extends Controller
{
    public function __invoke(IngestRequest $request, TelemetryIngestor $ingestor): JsonResponse
    {
        $application = $request->attributes->get('application');
        $counts = $ingestor->ingest($application, $request->validated());

        return response()->json([
            'ok' => true,
            'stored' => $counts,
            // El agente guarda los anuncios vigentes y los muestra al iniciar sesión.
            'announcements' => [
                'embed_url' => self::embedUrl(),
                'app' => $application->slug,
                'items' => Announcement::feedFor($application),
            ],
            // Módulo «Ubicación obligatoria»: el agente la aplica aunque la orden directa falle.
            'policy' => [
                'location_required' => (bool) $application->location_required,
            ],
        ]);
    }

    /**
     * Script público que dibuja los anuncios (versionado para invalidar la caché del navegador).
     */
    public static function embedUrl(): string
    {
        $file = public_path('anuncios/embed.js');

        return Announcement::publicUrl('anuncios/embed.js?v='.(is_file($file) ? filemtime($file) : 1));
    }
}
