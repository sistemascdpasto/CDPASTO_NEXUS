# Nexus · CD Pasto

Panel central de monitoreo, salud y auditoría de los sistemas desplegados en Railway
(Adenar, EasyOL, Tickets, Reempaque, TAT SIDER…). Laravel 12 + Inertia + React.

## Módulos

| Módulo | Historias |
|---|---|
| Aplicaciones: registro, API key, listado con estado, detalle | HU-01, 02, 03 |
| Health checks programados, uptime diario/semanal/mensual, BD/caché/cola | HU-04, 05, 06 |
| Despliegues y CPU/memoria/red desde la API de Railway | HU-07, 11 |
| Tiempos de respuesta, rutas lentas, volumen de peticiones | HU-08, 09 |
| Errores agrupados, usuarios afectados, resolver/reabrir | HU-10 |
| Conectados ahora, historial de logins, cierre de sesión y bloqueo remotos | HU-12 a 15 |
| Auditoría con antes/después, filtros y exportación Excel/PDF | HU-16 a 19 |
| Alertas por caída/recuperación, picos de errores, actividad sospechosa (correo + WhatsApp) | HU-20 a 22 |
| Login con doble factor obligatorio y auditoría del propio panel | HU-23, 24 |
| Dashboard ejecutivo | HU-25 |
| Usuarios del panel: superadmin, jefe/encargado, solo lectura, con apps asignadas | — |
| **Superusuario**: directorio de usuarios de cada app (desde su BD) y vista *Usuario 360* | — |
| **Superusuario**: explorador de solo lectura de la base MySQL de cada app en Railway | — |
| **Superusuario**: reiniciar / redesplegar en Railway, logs de despliegue, modo mantenimiento, limpiar caché, reintentar trabajos fallidos | — |
| **Superusuario**: ficha técnica y chequeos de seguridad de cada app (APP_DEBUG, HTTPS, cookies, disco, trabajos fallidos) | — |

### Bases de datos de las apps

Cada app Laravel está vinculada a su servicio MySQL de Railway (`MySQL`, `MySQL-1_L8`, `MySQL-lwyK`).
Nexus **no guarda contraseñas**: las lee en vivo de las variables del servicio vía la API de Railway
(caché cifrada de 10 min) y abre la sesión en modo `READ ONLY`, por lo que no puede modificar datos.
Dentro de Railway (mismo proyecto) usa la red privada; en local, el proxy TCP público.

Los datos internos de cada app (peticiones, errores, logins, auditoría, sesiones) llegan desde el
**agente** (`agent/`, paquete `cdpasto/nexus-agent`). Ver [agent/README.md](agent/README.md).
Los sitios estáticos solo se monitorean por HTTP y Railway.

## Roles

- **Superadministrador**: todo, incluidas apps, usuarios del panel y auditoría del panel.
- **Jefe / Encargado**: sus apps asignadas; puede cerrar sesiones, bloquear usuarios y resolver errores.
- **Solo lectura**: consulta sus apps asignadas.

Todos deben configurar doble factor (TOTP) en su primer ingreso. No hay registro público.

## Desarrollo local

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed          # registra los 5 sistemas
php artisan nexus:superadmin        # crea tu usuario
composer dev                        # servidor + cola + vite
php artisan schedule:work           # health checks y sincronización
```

## Despliegue en Railway

1. Servicio **web** desde este repo + base MySQL. `railway.json` migra y siembra en cada despliegue.
2. Servicio **scheduler** (mismo repo) con start command `php artisan schedule:work`.
   Ejecuta health checks (cada minuto), sincronización de Railway (5 min), depuración diaria y
   el envío de notificaciones encoladas.
3. Variables (ambos servicios): las de `.env.example`, más `APP_URL`, `DB_*`, `MAIL_*`,
   `RAILWAY_API_TOKEN` (token de cuenta o equipo con acceso al proyecto), `NEXUS_RAILWAY_PROJECT_ID`,
   `NEXUS_RAILWAY_ENVIRONMENT_ID` y, para WhatsApp,
   `WHATSAPP_DRIVER` (`callmebot` o `meta`).
4. `php artisan nexus:superadmin` una vez (con `railway run` o `railway ssh`).

## Comandos

| Comando | Qué hace |
|---|---|
| `nexus:check-health [--all]` | Verifica las apps a las que les toca según su intervalo |
| `nexus:sync-railway [--hours=1]` | Trae despliegues y métricas de Railway |
| `nexus:collect-info` | Actualiza ficha técnica y hallazgos de seguridad (cada hora) |
| `nexus:prune` | Borra telemetría vieja según `config/nexus.php` |
| `nexus:superadmin` | Crea o promueve un superadministrador |
