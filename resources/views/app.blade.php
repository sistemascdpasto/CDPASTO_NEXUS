<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0b1324">
        <meta name="description" content="Nexus · Centro de control de los sistemas de CD Pasto">

        {{-- Aplica el tema antes de pintar para evitar el destello claro en modo oscuro. --}}
        <script>
            (function () {
                try {
                    var appearance = localStorage.getItem('appearance') || 'system';
                    var dark = appearance === 'dark' || (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                    document.documentElement.classList.toggle('dark', dark);
                } catch (e) {}
            })();
        </script>

        <title inertia>{{ config('app.name', 'Nexus') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" type="image/png" href="/favicon-32.png" sizes="32x32">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|jetbrains-mono:400,500" rel="stylesheet" />

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
