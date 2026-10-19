/*!
 * Anuncios de Nexus · CD Pasto
 *
 * Muestra los anuncios (banners) configurados en Nexus en una ventana sobre la página.
 *
 *  - Apps con login (lo inyecta el agente de Nexus):
 *      <script src=".../anuncios/embed.js" data-nexus-modo="sesion" data-nexus-app="adenar"
 *              data-nexus-endpoint="/nexus/anuncios" defer></script>
 *    Se muestra justo después de iniciar sesión: el agente deja la cookie «nexus_anuncio» al
 *    autenticar al usuario y el script la consume.
 *
 *  - Webs sin login (p. ej. la página principal):
 *      <script src="https://cdpastonexus.up.railway.app/anuncios/embed.js"
 *              data-nexus-modo="web" data-nexus-app="bavaria-cdpasto" defer></script>
 *    Se muestra una vez por visita (pestaña del navegador).
 */
(function () {
    'use strict';

    var script = document.currentScript;
    if (!script || window.__nexusAnuncios) return;
    window.__nexusAnuncios = true;

    var modo = script.getAttribute('data-nexus-modo') || 'web';
    var app = script.getAttribute('data-nexus-app') || '';
    var endpoint = script.getAttribute('data-nexus-endpoint') || '/nexus/anuncios';
    var origenNexus = new URL(script.src, location.href).origin;
    var COOKIE = 'nexus_anuncio';
    var abierto = false;

    // ---------------------------------------------------------------- estilos
    var css =
        '.nxa-fondo{position:fixed;inset:0;z-index:2147483646;display:flex;align-items:center;justify-content:center;padding:16px;' +
        'background:rgba(5,10,24,.72);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);opacity:0;transition:opacity .25s ease;' +
        'font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}' +
        '.nxa-fondo.nxa-visible{opacity:1}' +
        '.nxa-caja{position:relative;max-width:min(960px,100%);max-height:100%;display:flex;flex-direction:column;align-items:center;gap:12px;' +
        'transform:translateY(12px) scale(.98);transition:transform .3s cubic-bezier(.2,.8,.2,1)}' +
        '.nxa-visible .nxa-caja{transform:none}' +
        '.nxa-img{display:block;max-width:100%;max-height:calc(100vh - 140px);width:auto;height:auto;border-radius:14px;' +
        'box-shadow:0 24px 60px rgba(0,0,0,.45);background:#fff}' +
        'a.nxa-enlace-img{display:block;border-radius:14px;outline-offset:4px}' +
        '.nxa-cerrar{position:absolute;top:-14px;right:-14px;width:38px;height:38px;border-radius:999px;border:0;cursor:pointer;' +
        'background:#fff;color:#0b1324;font-size:22px;line-height:38px;text-align:center;box-shadow:0 6px 18px rgba(0,0,0,.35)}' +
        '.nxa-cerrar:hover{background:#f1f5f9}' +
        '.nxa-acciones{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;align-items:center}' +
        '.nxa-boton{appearance:none;border:0;cursor:pointer;border-radius:999px;padding:10px 22px;font-size:14px;font-weight:600;' +
        'text-decoration:none;display:inline-block}' +
        '.nxa-boton-principal{background:#e3001b;color:#fff}.nxa-boton-principal:hover{background:#c40017}' +
        '.nxa-boton-sec{background:rgba(255,255,255,.14);color:#fff;border:1px solid rgba(255,255,255,.35)}.nxa-boton-sec:hover{background:rgba(255,255,255,.24)}' +
        '.nxa-puntos{display:flex;gap:6px}.nxa-punto{width:8px;height:8px;border-radius:999px;background:rgba(255,255,255,.35)}' +
        '.nxa-punto.nxa-activo{background:#fff}' +
        '@media (max-width:600px){.nxa-cerrar{top:-10px;right:-6px}.nxa-img{max-height:calc(100vh - 170px)}}' +
        '@media (prefers-reduced-motion:reduce){.nxa-fondo,.nxa-caja{transition:none}}';

    function agregarEstilos() {
        if (document.getElementById('nxa-estilos')) return;
        var style = document.createElement('style');
        style.id = 'nxa-estilos';
        style.textContent = css;
        document.head.appendChild(style);
    }

    // ---------------------------------------------------------------- métricas
    function registrar(anuncio, tipo) {
        if (!anuncio.event_url || !app) return;
        var url = anuncio.event_url + '?app=' + encodeURIComponent(app) + '&type=' + tipo;
        try {
            if (navigator.sendBeacon && navigator.sendBeacon(url)) return;
        } catch (e) {}
        try {
            fetch(url, { method: 'POST', mode: 'no-cors', keepalive: true });
        } catch (e) {}
    }

    // ---------------------------------------------------------------- ventana
    function mostrar(anuncios) {
        if (abierto || !anuncios || !anuncios.length) return;
        abierto = true;
        agregarEstilos();

        var indice = 0;
        var foco = document.activeElement;
        var fondo = document.createElement('div');
        fondo.className = 'nxa-fondo';
        fondo.setAttribute('role', 'dialog');
        fondo.setAttribute('aria-modal', 'true');

        var caja = document.createElement('div');
        caja.className = 'nxa-caja';
        fondo.appendChild(caja);

        function cerrar() {
            fondo.classList.remove('nxa-visible');
            document.removeEventListener('keydown', teclas, true);
            setTimeout(function () {
                fondo.remove();
                abierto = false;
                if (foco && foco.focus) foco.focus();
            }, 250);
        }

        function teclas(e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                cerrar();
            } else if (e.key === 'ArrowRight' && indice < anuncios.length - 1) {
                pintar(indice + 1);
            } else if (e.key === 'ArrowLeft' && indice > 0) {
                pintar(indice - 1);
            }
        }

        function pintar(i) {
            indice = i;
            var anuncio = anuncios[i];
            var ultimo = i === anuncios.length - 1;
            caja.innerHTML = '';
            fondo.setAttribute('aria-label', anuncio.title || 'Anuncio');

            var img = document.createElement('img');
            img.className = 'nxa-img';
            img.src = anuncio.image_url;
            img.alt = anuncio.title || 'Anuncio';
            if (anuncio.width && anuncio.height) {
                img.width = anuncio.width;
                img.height = anuncio.height;
            }

            if (anuncio.link_url) {
                var enlaceImg = document.createElement('a');
                enlaceImg.className = 'nxa-enlace-img';
                enlaceImg.href = anuncio.link_url;
                enlaceImg.target = '_blank';
                enlaceImg.rel = 'noopener noreferrer';
                enlaceImg.addEventListener('click', function () {
                    registrar(anuncio, 'click');
                });
                enlaceImg.appendChild(img);
                caja.appendChild(enlaceImg);
            } else {
                caja.appendChild(img);
            }

            var x = document.createElement('button');
            x.type = 'button';
            x.className = 'nxa-cerrar';
            x.setAttribute('aria-label', 'Cerrar anuncio');
            x.innerHTML = '&times;';
            x.addEventListener('click', cerrar);
            caja.appendChild(x);

            var acciones = document.createElement('div');
            acciones.className = 'nxa-acciones';

            if (anuncio.link_url) {
                var enlace = document.createElement('a');
                enlace.className = 'nxa-boton nxa-boton-sec';
                enlace.href = anuncio.link_url;
                enlace.target = '_blank';
                enlace.rel = 'noopener noreferrer';
                enlace.textContent = anuncio.link_label || 'Ver más';
                enlace.addEventListener('click', function () {
                    registrar(anuncio, 'click');
                });
                acciones.appendChild(enlace);
            }

            if (anuncios.length > 1) {
                var puntos = document.createElement('div');
                puntos.className = 'nxa-puntos';
                puntos.setAttribute('aria-label', 'Anuncio ' + (i + 1) + ' de ' + anuncios.length);
                for (var p = 0; p < anuncios.length; p++) {
                    var punto = document.createElement('span');
                    punto.className = 'nxa-punto' + (p === i ? ' nxa-activo' : '');
                    puntos.appendChild(punto);
                }
                acciones.appendChild(puntos);
            }

            var siguiente = document.createElement('button');
            siguiente.type = 'button';
            siguiente.className = 'nxa-boton nxa-boton-principal';
            siguiente.textContent = ultimo ? 'Entendido' : 'Siguiente';
            siguiente.addEventListener('click', function () {
                if (ultimo) cerrar();
                else pintar(indice + 1);
            });
            acciones.appendChild(siguiente);

            caja.appendChild(acciones);
            siguiente.focus({ preventScroll: true });
            registrar(anuncio, 'view');
        }

        fondo.addEventListener('click', function (e) {
            if (e.target === fondo) cerrar();
        });
        document.addEventListener('keydown', teclas, true);
        document.body.appendChild(fondo);
        pintar(0);
        requestAnimationFrame(function () {
            fondo.classList.add('nxa-visible');
        });
    }

    // ---------------------------------------------------------------- orígenes
    function hayCookie() {
        return document.cookie.split(';').some(function (c) {
            return c.trim().indexOf(COOKIE + '=') === 0;
        });
    }

    function borrarCookie() {
        document.cookie = COOKIE + '=; Max-Age=0; path=/';
    }

    // Apps con login: solo después de iniciar sesión (cookie que deja el agente).
    function revisarSesion() {
        if (abierto || !hayCookie()) return;
        borrarCookie();
        fetch(endpoint, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) {
                return r.ok ? r.json() : { data: [] };
            })
            .then(function (r) {
                mostrar(r.data);
            })
            .catch(function () {});
    }

    // Webs sin login: una vez por visita.
    function revisarWeb() {
        var clave = 'nexus-anuncios:' + app;
        try {
            if (sessionStorage.getItem(clave)) return;
        } catch (e) {}

        fetch(origenNexus + '/api/anuncios/publicos?app=' + encodeURIComponent(app), { headers: { Accept: 'application/json' } })
            .then(function (r) {
                return r.ok ? r.json() : { data: [] };
            })
            .then(function (r) {
                if (r.data && r.data.length) {
                    try {
                        sessionStorage.setItem(clave, '1');
                    } catch (e) {}
                    mostrar(r.data);
                }
            })
            .catch(function () {});
    }

    function iniciar() {
        if (modo === 'sesion') {
            revisarSesion();
            // Inertia cambia de página sin recargar: tras el login llega por navegación.
            document.addEventListener('inertia:navigate', revisarSesion);
            document.addEventListener('inertia:finish', revisarSesion);
            document.addEventListener('livewire:navigated', revisarSesion);
            window.addEventListener('focus', revisarSesion);
        } else {
            revisarWeb();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
