@props(['title', 'description'])

<div {{ $attributes->class(['flex flex-col items-center rounded-adara-card border border-dashed border-adara-border bg-adara-surface px-6 py-10 text-center']) }}>
    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-adara-primary-light text-adara-primary" aria-hidden="true">
        <i data-lucide="inbox" class="h-6 w-6"></i>
    </span>
    <h3 class="mt-4 font-display text-xl font-semibold text-adara-ink">{{ $title }}</h3>
    <p class="mt-2 max-w-md text-sm leading-6 text-adara-readable-muted">{{ $description }}</p>
    @if (trim((string) $slot))
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
