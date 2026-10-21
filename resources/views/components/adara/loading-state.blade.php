@props(['label' => 'Cargando información'])

<div role="status" aria-live="polite" {{ $attributes->class(['rounded-adara-card border border-adara-border bg-adara-surface p-5']) }}>
    <span class="sr-only">{{ $label }}</span>
    <div class="flex flex-col gap-3" aria-hidden="true">
        <div class="flex items-center justify-between gap-3">
            <div class="adara-skeleton-line h-4 w-1/3 rounded-full"></div>
            <span class="adara-loading-indicator h-2.5 w-2.5 shrink-0 rounded-full bg-adara-primary"></span>
        </div>
        <div class="adara-skeleton-line h-3 w-full rounded-full"></div>
        <div class="adara-skeleton-line h-3 w-4/5 rounded-full"></div>
    </div>
</div>
