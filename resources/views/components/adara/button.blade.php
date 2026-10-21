@props(['variant' => 'primary', 'loading' => false])

@php
    $variants = [
        'primary' => 'border-adara-primary bg-adara-primary text-white hover:bg-adara-primary-hover',
        'secondary' => 'border-adara-primary-light bg-adara-primary-light text-adara-primary hover:bg-adara-border',
        'outline' => 'border-adara-border bg-adara-surface text-adara-ink hover:bg-adara-primary-light',
        'ghost' => 'border-transparent bg-transparent text-adara-primary hover:bg-adara-primary-light',
        'danger' => 'border-adara-danger bg-adara-danger text-white hover:bg-adara-danger/90',
    ];
@endphp

<button @if ($loading) disabled aria-busy="true" @endif {{ $attributes->class([
    'inline-flex min-h-11 items-center justify-center gap-2 rounded-adara-control border px-4 py-2.5 text-sm font-semibold transition-colors duration-150 disabled:cursor-not-allowed disabled:opacity-55',
    $variants[$variant] ?? $variants['primary'],
])->merge(['type' => 'button']) }}>
    @if ($loading)
        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity=".25" />
            <path d="M12 3a9 9 0 0 1 9 9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
        </svg>
        <span class="sr-only">Cargando: </span>
    @endif
    {{ $slot }}
</button>
