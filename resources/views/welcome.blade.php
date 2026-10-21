@extends('layouts.app')

@section('title', 'Inicio')

@section('content')
    <div class="flex flex-col gap-7">
        <header class="flex flex-col gap-2">
            <p class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-adara-primary">Tu espacio de trabajo</p>
            <h1 class="font-display text-[30px] leading-tight font-semibold tracking-[-0.03em] text-adara-ink sm:text-[34px]">Bienvenida a Adara</h1>
            <p class="max-w-2xl text-sm leading-6 text-adara-readable-muted">Un lugar para organizar tu negocio con calma y encontrar cada dato cuando lo necesites.</p>
        </header>

        <div class="grid gap-5 lg:grid-cols-3">
            <section class="rounded-adara-card border border-adara-border bg-adara-surface p-6 shadow-adara-panel sm:p-8 lg:col-span-2" aria-labelledby="inicio-vacio-titulo">
                <div class="flex h-12 w-12 items-center justify-center rounded-[14px] bg-adara-primary-light text-adara-primary">
                    <i data-lucide="sparkles" class="h-6 w-6"></i>
                </div>
                <h2 id="inicio-vacio-titulo" class="mt-6 font-display text-2xl font-semibold text-adara-ink">Tu gestión empieza acá</h2>
                <p class="mt-3 max-w-xl text-sm leading-7 text-adara-readable-muted">Cuando cargues productos y registres operaciones, vas a encontrar la información real de tu negocio en este espacio.</p>
                <div class="mt-7 flex flex-wrap gap-3">
                    <span class="inline-flex items-center gap-2 rounded-full bg-adara-success-bg px-3 py-2 text-xs font-semibold text-adara-success">
                        <i data-lucide="hard-drive" class="h-4 w-4"></i>
                        Pensado para uso local
                    </span>
                    <span class="inline-flex items-center gap-2 rounded-full bg-adara-primary-light px-3 py-2 text-xs font-semibold text-adara-primary">
                        <i data-lucide="shield-check" class="h-4 w-4"></i>
                        Datos sin ejemplos ficticios
                    </span>
                </div>
            </section>

            <aside class="rounded-adara-card border border-adara-border bg-adara-surface p-6 shadow-adara-panel" aria-labelledby="proximas-secciones-titulo">
                <p class="text-[11px] font-extrabold uppercase tracking-[0.13em] text-adara-primary">Próximas secciones</p>
                <h2 id="proximas-secciones-titulo" class="mt-2 font-display text-xl font-semibold text-adara-ink">Todo en un mismo lugar</h2>
                <ul class="mt-6 flex flex-col gap-4 text-sm">
                    <li class="border-b border-adara-border pb-4">
                        <a href="{{ route('products.index') }}" wire:navigate class="flex min-h-10 items-center gap-3 rounded-adara-control hover:text-adara-primary">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-adara-control bg-adara-primary-light text-adara-primary"><i data-lucide="package" class="h-[18px] w-[18px]"></i></span>
                        <span>Productos y presentaciones</span>
                        </a>
                    </li>
                    <li class="border-b border-adara-border pb-4">
                        <a href="{{ route('inventory.index') }}" wire:navigate class="flex min-h-10 items-center gap-3 rounded-adara-control hover:text-adara-primary">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-adara-control bg-adara-warning-bg text-adara-warning"><i data-lucide="boxes" class="h-[18px] w-[18px]"></i></span>
                        <span>Stock y movimientos</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('sales.index') }}" wire:navigate class="flex min-h-10 items-center gap-3 rounded-adara-control hover:text-adara-primary">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-adara-control bg-adara-success-bg text-adara-success"><i data-lucide="shopping-cart" class="h-[18px] w-[18px]"></i></span>
                        <span>Ventas y cobros</span>
                        </a>
                    </li>
                </ul>
            </aside>
        </div>
    </div>
@endsection
