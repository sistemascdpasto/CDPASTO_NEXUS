<?php

return [

    /*
    | Exigir doble factor (TOTP) a todos los usuarios del panel.
    */
    'require_two_factor' => env('NEXUS_REQUIRE_2FA', true),

    'health' => [
        // Fallos consecutivos antes de declarar una app como caída.
        'down_after_failures' => (int) env('NEXUS_DOWN_AFTER_FAILURES', 2),
        'timeout_seconds' => (int) env('NEXUS_HEALTH_TIMEOUT', 10),
    ],

    'alerts' => [
        // Minutos durante los que no se repite la misma alerta para la misma app.
        'cooldown_minutes' => (int) env('NEXUS_ALERT_COOLDOWN', 30),
        // Ventana (minutos) para evaluar picos de errores, logins fallidos y eliminaciones masivas.
        'window_minutes' => (int) env('NEXUS_ALERT_WINDOW', 10),
    ],

    'whatsapp' => [
        // callmebot | meta | null (desactivado)
        'driver' => env('WHATSAPP_DRIVER'),
        'callmebot_url' => 'https://api.callmebot.com/whatsapp.php',
        'meta_token' => env('WHATSAPP_META_TOKEN'),
        'meta_phone_number_id' => env('WHATSAPP_META_PHONE_NUMBER_ID'),
    ],

    'railway' => [
        'endpoint' => 'https://backboard.railway.com/graphql/v2',
        // Token de cuenta o de equipo (Bearer) o de proyecto (Project-Access-Token).
        'token' => env('RAILWAY_API_TOKEN'),
        'token_type' => env('RAILWAY_TOKEN_TYPE', 'bearer'),
        // Prefijo NEXUS_: Railway ya inyecta RAILWAY_PROJECT_ID/RAILWAY_ENVIRONMENT_ID del propio servicio.
        'project_id' => env('NEXUS_RAILWAY_PROJECT_ID'),
        'environment_id' => env('NEXUS_RAILWAY_ENVIRONMENT_ID'),
        // La red privada (mysql.railway.internal) solo sirve si Nexus corre en el mismo proyecto y entorno.
        'inside' => filled(env('RAILWAY_PRIVATE_DOMAIN'))
            && env('RAILWAY_PROJECT_ID') === env('NEXUS_RAILWAY_PROJECT_ID')
            && env('RAILWAY_ENVIRONMENT_ID') === env('NEXUS_RAILWAY_ENVIRONMENT_ID'),
    ],

    // Precios de Railway (USD) para estimar costos a partir de las métricas de cada servicio.
    'costs' => [
        'vcpu_month' => (float) env('NEXUS_PRICE_VCPU_MONTH', 20),
        'memory_gb_month' => (float) env('NEXUS_PRICE_MEMORY_GB_MONTH', 10),
        'egress_gb' => (float) env('NEXUS_PRICE_EGRESS_GB', 0.05),
        'usd_to_cop' => (float) env('NEXUS_USD_TO_COP', 4000),
    ],

    // Días que se conserva cada tipo de dato antes de depurarlo.
    'retention_days' => [
        'health_checks' => 90,
        'request_metrics' => 90,
        'user_activity' => 180,
        'resource_metrics' => 90,
        'error_events' => 90,
        'login_events' => 365,
        'audit_logs' => (int) env('NEXUS_AUDIT_RETENTION_DAYS', 730),
        'alerts' => 180,
    ],

    // Una sesión se considera activa si tuvo actividad en estos minutos.
    'active_session_minutes' => 15,

];
