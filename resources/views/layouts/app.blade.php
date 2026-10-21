<!DOCTYPE html>
<html lang="es-AR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#312631">
        <title>@yield('title', 'Inicio') · Adara Gestión</title>
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-dvh bg-adara-background font-sans text-adara-ink antialiased">
        <a href="#contenido" class="sr-only z-50 rounded-adara-control bg-adara-primary px-4 py-3 font-semibold text-white focus:not-sr-only focus:fixed focus:top-3 focus:left-3">Saltar al contenido</a>

        <div class="min-h-dvh lg:flex">
            <aside class="hidden shrink-0 flex-col bg-adara-sidebar px-[15px] pt-7 pb-5 text-white lg:sticky lg:top-0 lg:flex lg:h-dvh lg:w-[246px]" aria-label="Barra lateral">
                <div class="flex items-center gap-3 px-3 pb-7">
                    <span class="flex h-10 w-10 items-center justify-center rounded-[12px] bg-[#bf8da3] font-display text-[25px] font-bold leading-none text-white" aria-hidden="true">a.</span>
                    <div>
                        <span class="block font-display text-[25px] leading-none font-bold">adara.</span>
                        <span class="mt-1 block text-[10px] font-semibold tracking-[0.12em] text-[#c7b1bd] uppercase">Gestión de cosmética</span>
                    </div>
                </div>

                @include('partials.navigation')

                <div class="mt-auto border-t border-white/15 px-3 pt-5">
                    <span class="inline-flex items-center gap-2 text-xs text-[#b4d7c4]">
                        <span class="h-2 w-2 rounded-full bg-[#83cba6]" aria-hidden="true"></span>
                        Uso local
                    </span>
                    <p class="mt-2 text-xs leading-5 text-white/65">Tu información se guarda en este equipo.</p>
                </div>
            </aside>

            <div class="min-w-0 flex-1">
                <header class="relative z-20 flex min-h-[74px] items-center justify-between gap-4 border-b border-adara-border bg-adara-surface px-4 sm:px-6 lg:px-[38px]">
                    <div class="flex min-w-0 items-center gap-3">
                        <details class="group lg:hidden">
                            <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-adara-control border border-adara-border px-3 text-sm font-semibold text-adara-ink [&::-webkit-details-marker]:hidden">
                                <i data-lucide="menu" class="h-[18px] w-[18px]"></i>
                                Menú
                            </summary>
                            <div class="absolute top-[66px] left-4 max-h-[calc(100dvh-82px)] w-[min(310px,calc(100vw-32px))] overflow-y-auto rounded-adara-card bg-adara-sidebar p-4 text-white shadow-xl sm:left-6">
                                @include('partials.navigation')
                            </div>
                        </details>
                        <nav aria-label="Ruta actual" class="min-w-0 truncate text-xs text-adara-readable-muted">
                            <span class="hidden sm:inline">@yield('section', 'General') <span aria-hidden="true">/</span> </span><strong class="font-semibold text-adara-ink">@yield('page', 'Inicio')</strong>
                        </nav>
                    </div>
                    <span class="shrink-0 font-display text-lg font-bold text-adara-primary lg:hidden">adara.</span>
                    <span class="hidden text-xs font-semibold text-adara-readable-muted lg:block">Adara Gestión</span>
                </header>

                <main id="contenido" class="mx-auto w-full max-w-[1520px] px-4 py-7 sm:px-6 lg:px-[38px] lg:py-8" tabindex="-1">
                    @yield('content')
                </main>
            </div>
        </div>
        <x-adara.toast />
        @livewireScripts
    </body>
</html>
