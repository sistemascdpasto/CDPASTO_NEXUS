<?php

return [

    // URL del panel Nexus y API key de esta app (se genera al registrarla en Nexus).
    'url' => env('NEXUS_URL'),
    'key' => env('NEXUS_KEY'),

    // Permite apagar el agente sin desinstalarlo.
    'enabled' => env('NEXUS_ENABLED', true),

    // Segundos mínimos entre envíos al panel.
    'flush_interval' => 60,

    'requests' => [
        'enabled' => true,
        // Rutas que no se miden (patrones de Str::is sobre el path).
        'ignore' => ['nexus/*', 'build/*', 'storage/*', 'favicon.ico', 'up'],
    ],

    'audit' => [
        'enabled' => true,
        // Registrar archivos subidos y descargados (nombre, tamaño, tipo; nunca el contenido).
        'files' => true,
        // Auditar también cambios hechos desde consola (seeders, comandos programados).
        'console' => env('NEXUS_AUDIT_CONSOLE', false),
        // Modelos que no se auditan (FQCN).
        'exclude_models' => [
            // App\Models\Bitacora::class,
        ],
        // Atributos que nunca se envían (se reemplazan por ***).
        'hidden_attributes' => ['password', 'remember_token', 'api_token', 'token', 'secret', 'two_factor_secret', 'two_factor_recovery_codes'],
        // Actualizaciones que solo tocan estos campos se ignoran.
        'ignore_only' => ['updated_at', 'remember_token', 'last_login_at', 'last_activity'],
    ],

    'sessions' => [
        // Minutos de inactividad tras los cuales una sesión deja de contar como activa.
        'active_minutes' => 15,
    ],

    'queue' => [
        // Si un trabajo lleva más de estos minutos esperando, la cola se reporta con falla.
        'max_wait_minutes' => 30,
    ],

    // Campo del formulario de login donde se muestra el aviso de bloqueo (email, identification_number…).
    'login_field' => env('NEXUS_LOGIN_FIELD', 'email'),

    // Atributo del usuario que se muestra como nombre.
    'user_name_attribute' => 'name',

    // Días que se conservan registros pendientes si el panel no responde.
    'buffer_retention_hours' => 48,

];
