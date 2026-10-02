# Agente Nexus (`cdpasto/nexus-agent`)

Paquete Laravel que conecta una aplicación con el panel **Nexus**. Una vez instalado:

| Envía a Nexus | Cómo lo obtiene |
|---|---|
| Peticiones por ruta (volumen, tiempo promedio, errores 4xx/5xx) | Middleware global; se agrupa por minuto y patrón de ruta |
| Excepciones (clase, mensaje, archivo, línea, usuario, URL, traza) | `reportable()` del manejador de excepciones |
| Inicios de sesión exitosos, fallidos, bloqueos por intentos y salidas | Eventos `Login`, `Failed`, `Lockout`, `Logout` |
| Auditoría: creó / editó / eliminó, con valores antes y después | Eventos de Eloquent de **todos** los modelos (campos sensibles enmascarados) |
| Usuarios conectados | Tabla `sessions` (requiere `SESSION_DRIVER=database`) |

| Expone (firmado por Nexus con HMAC) | Para |
|---|---|
| `GET /nexus/health` | Estado de BD, caché, cola y almacenamiento |
| `POST /nexus/commands` | Cerrar sesión, bloquear y desbloquear usuarios |

Los eventos se guardan en la tabla `nexus_buffer` y se envían como máximo una vez por minuto, al terminar
una petición (después de entregar la respuesta) o desde el scheduler (`nexus:flush`) si la app tiene uno.
Si Nexus no responde, la app sigue funcionando normalmente y los datos esperan hasta 48 h.

## Instalación

El agente vive dentro del repo de cada app, en `packages/nexus-agent` (así Railway lo instala sin
acceso a otros repos). Adenar, EasyOL y Tickets ya lo tienen. Para una app nueva:

```bash
# copiar Nexus/agent a <app>/packages/nexus-agent, luego:
composer config repositories.nexus-agent '{"type":"path","url":"packages/nexus-agent","options":{"symlink":false}}'
composer require cdpasto/nexus-agent:^1.1
php artisan vendor:publish --tag=nexus-config   # opcional: excluir modelos, campo de login…
```

Variables en los servicios **web y scheduler** de Railway (la API key se obtiene en Nexus → app → Integración):

```env
NEXUS_URL=https://<dominio-de-nexus>
NEXUS_KEY=nx_...
```

El `php artisan migrate --force` del despliegue crea `nexus_buffer` y `nexus_blocks`. Luego, en Nexus →
editar la app → dejar **Ruta de salud** vacía (usa `/nexus/health`).

## Actualizar el agente en las apps

Después de cambiar el código en `Nexus/agent`:

```bash
bash agent/sync-to-apps.sh   # copia a cada app y refresca composer.lock / vendor
```

Luego commit + push en cada app.

## Qué audita

- **Cambios de datos**: creó / editó / eliminó / restauró cualquier modelo Eloquent, con valores antes
  y después. El módulo se toma del namespace (`App\Models\Flota\Vehiculo` → `Flota / Vehiculo`).
- **Archivos subidos**: nombre, tamaño, tipo y campo de cada archivo de la petición (nunca el contenido).
- **Descargas y exportaciones**: cualquier respuesta con `Content-Disposition: attachment` (Excel, PDF…).
- **Actividad**: por usuario y hora, cuántas peticiones hizo y qué rutas usó.
- **Accesos**: ingresos, intentos fallidos, bloqueos por intentos y salidas.

Lo hecho desde consola (seeders, comandos programados, colas) no se audita salvo `NEXUS_AUDIT_CONSOLE=true`.

## Comandos que Nexus puede enviar

Cerrar sesión, bloquear y desbloquear usuarios; activar/quitar modo mantenimiento (las rutas `/nexus/*`
siguen respondiendo); `cache:clear`; reintentar trabajos fallidos. `/nexus/info` reporta entorno,
chequeos de seguridad, trabajos fallidos, uso del disco y paquetes.

## Auditoría manual

Para acciones que no son cambios de modelos (exportar, consultar datos sensibles):

```php
use Cdpasto\NexusAgent\Nexus;

Nexus::audit('exported', 'Reporte de asistencia');
Nexus::audit('viewed', 'Hoja de vida', $colaborador->id);
```

## Configuración opcional

`php artisan vendor:publish --tag=nexus-config` crea `config/nexus_agent.php`, donde se pueden excluir
modelos de la auditoría, ocultar más atributos o ignorar rutas. `NEXUS_ENABLED=false` apaga el agente.
