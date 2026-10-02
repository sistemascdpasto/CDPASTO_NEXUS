<?php

namespace Cdpasto\NexusAgent\Http\Middleware;

use Cdpasto\NexusAgent\Flusher;
use Cdpasto\NexusAgent\Recorder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Mide cada petición, audita archivos subidos/descargados y al terminar envía el buffer si corresponde.
 */
class RecordRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('nexus_started_at', microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! Recorder::enabled()) {
            return;
        }

        try {
            $this->record($request, $response);
        } catch (Throwable) {
            // Nunca afectar la respuesta del usuario.
        }

        Flusher::flushIfDue();
    }

    private function record(Request $request, Response $response): void
    {
        if ($request->method() === 'OPTIONS' || Str::is(config('nexus_agent.requests.ignore', []), $request->path())) {
            return;
        }

        $user = Recorder::currentUser();
        $userId = $user ? (string) $user->getAuthIdentifier() : null;
        // Se agrupa por patrón de ruta (clientes/{id}) y no por URL concreta.
        $route = '/'.ltrim($request->route()?->uri() ?? 'sin-ruta', '/');

        if (config('nexus_agent.requests.enabled')) {
            $started = $request->attributes->get('nexus_started_at') ?? (defined('LARAVEL_START') ? LARAVEL_START : microtime(true));

            Recorder::request($request->method(), $route, (int) round((microtime(true) - $started) * 1000), $response->getStatusCode(), $userId);
        }

        if (! config('nexus_agent.audit.files', true) || $response->getStatusCode() >= 400) {
            return;
        }

        $module = $this->moduleFor($request, $route);
        $files = $this->uploadedFiles($request);

        if ($files) {
            Recorder::audit('uploaded', $module, $this->firstRouteKey($request), null, [
                'archivos' => $files,
                'ruta' => $request->method().' '.$route,
            ]);
        }

        if ($download = $this->downloadName($response)) {
            Recorder::audit('downloaded', $module, $this->firstRouteKey($request), null, [
                'archivo' => $download,
                'ruta' => $request->method().' '.$route,
            ]);
        }
    }

    /**
     * @return list<array{campo: string, nombre: string, tamano_kb: int, tipo: ?string}>
     */
    private function uploadedFiles(Request $request): array
    {
        $files = [];

        foreach (Arr::dot($request->allFiles()) as $field => $file) {
            if ($file instanceof UploadedFile) {
                $files[] = [
                    'campo' => (string) $field,
                    'nombre' => $file->getClientOriginalName(),
                    'tamano_kb' => (int) ceil(($file->getSize() ?: 0) / 1024),
                    'tipo' => $file->getClientMimeType(),
                ];
            }
        }

        return $files;
    }

    private function downloadName(Response $response): ?string
    {
        $disposition = (string) $response->headers->get('Content-Disposition');

        if (! str_contains(strtolower($disposition), 'attachment')
            && ! $response instanceof BinaryFileResponse
            && ! ($response instanceof StreamedResponse && $disposition !== '')) {
            return null;
        }

        if (preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $disposition, $match)) {
            return rawurldecode($match[1]);
        }

        return $response instanceof BinaryFileResponse ? $response->getFile()->getFilename() : 'descarga';
    }

    /**
     * Módulo legible a partir del nombre de la ruta (flota.vehiculos.documentos.store → Flota / Vehiculos / Documentos).
     */
    private function moduleFor(Request $request, string $route): string
    {
        $name = $request->route()?->getName();

        if ($name) {
            $parts = array_filter(explode('.', $name), fn ($part) => ! in_array($part, ['store', 'update', 'index', 'show', 'download', 'export', 'upload', 'import', 'create', 'edit', 'destroy'], true));

            if ($parts) {
                return implode(' / ', array_map(fn ($p) => Str::headline($p), $parts));
            }
        }

        return $route;
    }

    private function firstRouteKey(Request $request): ?string
    {
        foreach ($request->route()?->parameters() ?? [] as $value) {
            if (is_scalar($value)) {
                return (string) $value;
            }

            if (is_object($value) && method_exists($value, 'getKey')) {
                return (string) $value->getKey();
            }
        }

        return null;
    }
}
