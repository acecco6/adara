@extends('layouts.app')

@section('title', $title)
@section('section', $section)
@section('page', $title)

@section('content')
    <div class="flex flex-col gap-7">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-adara-primary">Adara Gestión</p>
                <h1 class="mt-2 font-display text-[30px] font-semibold leading-tight text-adara-ink">{{ $title }}</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-adara-readable-muted">{{ $summary }}</p>
            </div>
            <x-adara.badge tone="warning" class="self-start">En preparación</x-adara.badge>
        </header>

        @if (($pageKind ?? null) === 'list')
            <section class="overflow-hidden rounded-adara-card border border-adara-border bg-adara-surface shadow-adara-panel" aria-label="Listado de {{ $title }}">
                <div class="flex flex-col gap-4 border-b border-adara-border p-5 sm:flex-row sm:items-end sm:justify-between sm:p-6">
                    <div class="w-full max-w-sm">
                        <label for="busqueda-seccion" class="block text-sm font-semibold text-adara-ink">Buscar</label>
                        <input id="busqueda-seccion" type="search" placeholder="Disponible próximamente" disabled class="mt-2 min-h-11 w-full rounded-adara-control border border-adara-border bg-adara-background px-3 text-sm text-adara-readable-muted disabled:cursor-not-allowed" />
                    </div>
                    <x-adara.button disabled>{{ $actionLabel }}</x-adara.button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[600px] text-left" aria-label="{{ $title }}">
                        <thead class="bg-adara-background text-[11px] font-semibold uppercase tracking-wide text-adara-readable-muted">
                            <tr>
                                @foreach ($columns as $column)
                                    <th scope="col" class="px-5 py-3">{{ $column }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="{{ count($columns) }}" class="px-5 py-10 text-center">
                                    <p class="font-display text-lg font-semibold text-adara-ink">Listado en preparación</p>
                                    <p class="mt-2 text-sm text-adara-readable-muted">Todavía no muestra datos ni permite registrar operaciones.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        @elseif (($pageKind ?? null) === 'detail')
            <div class="grid gap-5 lg:grid-cols-2">
                <section class="rounded-adara-card border border-adara-border bg-adara-surface p-6 shadow-adara-panel" aria-labelledby="resumen-titulo">
                    <h2 id="resumen-titulo" class="font-display text-xl font-semibold">Resumen</h2>
                    <p class="mt-4 text-sm leading-6 text-adara-readable-muted">Esta ficha está en preparación. No se muestran datos de ningún registro.</p>
                </section>
                <section class="rounded-adara-card border border-adara-border bg-adara-surface p-6 shadow-adara-panel" aria-labelledby="historial-titulo">
                    <h2 id="historial-titulo" class="font-display text-xl font-semibold">Historial</h2>
                    <p class="mt-4 text-sm leading-6 text-adara-readable-muted">El historial estará disponible al implementar esta sección.</p>
                </section>
            </div>
        @else
            <section class="rounded-adara-card border border-adara-border bg-adara-surface p-5 shadow-adara-panel sm:p-7" aria-label="Estado de la sección">
                <x-adara.empty-state title="Esta sección está en preparación" description="Todavía no muestra datos ni permite registrar operaciones. Estará disponible cuando se implemente su función." />
            </section>
        @endif

        <div>
            <a href="{{ route($backRoute ?? 'home') }}" wire:navigate class="inline-flex min-h-11 items-center justify-center rounded-adara-control border border-adara-border bg-adara-surface px-4 py-2.5 text-sm font-semibold text-adara-ink transition-colors duration-150 hover:bg-adara-primary-light">
                {{ $backLabel ?? 'Volver al inicio' }}
            </a>
        </div>
    </div>
@endsection
