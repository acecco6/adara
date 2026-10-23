<!DOCTYPE html>
<html lang="es-AR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#312631">
        <title>@yield('title', 'Iniciar sesión') · Adara Gestión</title>
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-dvh bg-adara-background font-sans text-adara-ink antialiased">
        <a href="#contenido" class="sr-only z-50 rounded-adara-control bg-adara-primary px-4 py-3 font-semibold text-white focus:not-sr-only focus:fixed focus:top-3 focus:left-3">Saltar al contenido</a>
        <main id="contenido" class="flex min-h-dvh items-center justify-center px-4 py-10" tabindex="-1">
            @yield('content')
        </main>
    </body>
</html>
