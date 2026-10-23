<!DOCTYPE html>
<html lang="es-AR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#8B4969">
        <title>Catálogo · Adara</title>
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-w-0 bg-adara-background font-sans text-adara-ink antialiased">
        <a href="#contenido" class="sr-only rounded-adara-control bg-adara-primary px-4 py-3 font-semibold text-white focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:not-sr-only">Saltar al contenido</a>
        {{ $slot }}
        @livewireScripts
    </body>
</html>
