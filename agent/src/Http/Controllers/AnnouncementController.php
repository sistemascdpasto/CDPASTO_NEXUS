<?php

namespace Cdpasto\NexusAgent\Http\Controllers;

use Cdpasto\NexusAgent\Announcements;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cookie;

/**
 * Anuncios vigentes para el script que los muestra tras iniciar sesión.
 */
class AnnouncementController
{
    public function __invoke(): JsonResponse
    {
        // Si la cookie quedó en cola en esta misma petición, se descarta para que gane el borrado.
        Cookie::unqueue(Announcements::COOKIE);

        return response()
            ->json(['data' => Announcements::current()['items']])
            ->header('Cache-Control', 'no-store')
            ->withCookie(Cookie::forget(Announcements::COOKIE));
    }
}
