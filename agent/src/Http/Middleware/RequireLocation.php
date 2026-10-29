<?php

namespace Cdpasto\NexusAgent\Http\Middleware;

use Cdpasto\NexusAgent\Location;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ubicación obligatoria: un usuario conectado que no ha dado permiso de ubicación en esta
 * sesión no puede usar la app; ve un aviso que se la pide y recarga al obtenerla.
 *
 * Solo se frenan las navegaciones (GET): un formulario a medio enviar nunca pierde datos. Si el
 * usuario quita el permiso a mitad de sesión, el siguiente cambio de página muestra el aviso.
 */
class RequireLocation
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->applies($request) || Location::permitted($request)) {
            return $next($request);
        }

        // Inertia: fuerza una carga completa de la página, que llega a este aviso.
        if ($request->header('X-Inertia')) {
            return response('', 409)->header('X-Inertia-Location', $request->fullUrl());
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Debes permitir la ubicación para usar el sistema.'], 423);
        }

        return response($this->page($request), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function applies(Request $request): bool
    {
        return Location::required()
            && in_array($request->method(), ['GET', 'HEAD'], true)
            && $request->hasSession()
            && $request->user() !== null
            && ! Str::is(config('nexus_agent.location.except', []), $request->path());
    }

    private function page(Request $request): string
    {
        $app = e(config('app.name', 'el sistema'));
        $user = e((string) (data_get($request->user(), config('nexus_agent.user_name_attribute', 'name')) ?? ''));
        $token = e(csrf_token());
        $logout = Route::has('logout') ? e(route('logout')) : null;
        $logoutForm = $logout
            ? "<form method=\"POST\" action=\"{$logout}\"><input type=\"hidden\" name=\"_token\" value=\"{$token}\"><button type=\"submit\" class=\"link\">Cerrar sesión</button></form>"
            : '';

        return <<<HTML
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{$token}">
<title>Activa tu ubicación · {$app}</title>
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px;background:#0b1324;color:#e5e9f5;
font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
.card{width:min(480px,100%);background:#111b31;border:1px solid rgba(255,255,255,.1);border-radius:18px;padding:28px;box-shadow:0 24px 60px rgba(0,0,0,.45)}
.icon{font-size:40px;line-height:1}
h1{font-size:20px;margin:14px 0 8px;color:#fff}
p{font-size:14px;line-height:1.55;color:#c6cde0;margin:0 0 12px}
.btn{appearance:none;border:0;cursor:pointer;border-radius:999px;padding:12px 22px;font-size:15px;font-weight:600;background:#e3001b;color:#fff;width:100%}
.btn:hover{background:#c40017}.btn[disabled]{opacity:.6;cursor:wait}
.steps{display:none;background:rgba(255,255,255,.05);border-radius:12px;padding:12px 16px;margin:0 0 14px;font-size:13px;line-height:1.6;color:#e5e9f5}
.steps ol{margin:6px 0 0;padding-left:18px}
.status{min-height:20px;font-size:13px;color:#fbbf24;margin:12px 0 0}
.foot{display:flex;justify-content:space-between;align-items:center;margin-top:18px;font-size:12px;color:#8b95ad}
.link{background:none;border:0;color:#8b95ad;text-decoration:underline;cursor:pointer;font-size:12px;padding:0}
</style>
<script>window.__nexusUbicacionObligatoria = true;</script>
</head>
<body>
<main class="card">
  <div class="icon" aria-hidden="true">📍</div>
  <h1>Activa tu ubicación para continuar</h1>
  <p>Hola {$user}. Para usar {$app} es obligatorio compartir tu ubicación: se registra al ingresar y cada 10 minutos para la
  trazabilidad de la información (por ejemplo, la ciudad en las pruebas de alcoholemia). Solo la usa la empresa, conforme a la Ley 1581 de 2012.</p>
  <div class="steps" id="pasos">
    <strong>El navegador tiene la ubicación bloqueada para este sitio.</strong> Para activarla:
    <ol>
      <li>Toca el ícono a la izquierda de la dirección (🔒 o ⚙️).</li>
      <li>Busca <strong>Ubicación</strong> y elige <strong>Permitir</strong>.</li>
      <li>En el celular, revisa también que el GPS del teléfono esté encendido.</li>
      <li>Vuelve aquí y toca <strong>Reintentar</strong>.</li>
    </ol>
  </div>
  <button type="button" class="btn" id="permitir">Permitir ubicación</button>
  <p class="status" id="estado" role="status" aria-live="polite"></p>
  <div class="foot"><span>{$app}</span>{$logoutForm}</div>
</main>
<script>
(function () {
  var boton = document.getElementById('permitir');
  var estado = document.getElementById('estado');
  var pasos = document.getElementById('pasos');
  var token = document.querySelector('meta[name="csrf-token"]').content;
  var ocupado = false;

  function enviar(datos) {
    return fetch('/nexus/ubicacion', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
      body: JSON.stringify(datos)
    }).then(function (r) { return r.ok ? r.json() : { blocked: true }; });
  }

  function listo() {
    estado.style.color = '#4ade80';
    estado.textContent = 'Ubicación activada. Entrando…';
    location.reload();
  }

  function negado() {
    ocupado = false;
    boton.disabled = false;
    boton.textContent = 'Reintentar';
    pasos.style.display = 'block';
    estado.textContent = 'Sin permiso de ubicación no puedes usar el sistema.';
  }

  function pedir() {
    if (ocupado) return;
    ocupado = true;
    boton.disabled = true;
    estado.style.color = '#fbbf24';
    estado.textContent = 'Obteniendo tu ubicación…';

    if (!navigator.geolocation) {
      enviar({ error: 'unsupported' }).then(function () {
        ocupado = false;
        boton.disabled = false;
        estado.textContent = 'Este navegador no permite compartir la ubicación. Usa Chrome, Edge, Safari o Firefox actualizados.';
      });
      return;
    }

    navigator.geolocation.getCurrentPosition(function (pos) {
      enviar({ latitude: pos.coords.latitude, longitude: pos.coords.longitude, accuracy: pos.coords.accuracy })
        .then(function (r) { if (r.blocked) negado(); else listo(); })
        .catch(negado);
    }, function (err) {
      var codigo = err.code === 1 ? 'denied' : err.code === 3 ? 'timeout' : 'unavailable';
      enviar({ error: codigo }).then(function (r) { if (r.blocked) negado(); else listo(); }).catch(negado);
    }, { enableHighAccuracy: true, timeout: 20000, maximumAge: 5 * 60 * 1000 });
  }

  boton.addEventListener('click', pedir);

  // Si el permiso ya está dado (o el usuario lo cambia en la configuración), entra solo.
  if (navigator.permissions && navigator.permissions.query) {
    navigator.permissions.query({ name: 'geolocation' }).then(function (p) {
      if (p.state === 'granted') pedir();
      else if (p.state === 'denied') negado();
      p.onchange = function () { if (p.state === 'granted') pedir(); };
    }).catch(function () {});
  }
})();
</script>
</body>
</html>
HTML;
    }
}
