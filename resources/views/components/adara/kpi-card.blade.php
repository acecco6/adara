@props(['label', 'value', 'detail', 'tone' => 'plain'])

@php
    $tones = [
        'plain' => 'bg-adara-surface',
        'rose' => 'bg-adara-primary-light',
        'mint' => 'bg-adara-success-bg',
        'amber' => 'bg-adara-warning-bg',
    ];
    $iconTones = [
        'plain' => 'bg-adara-primary-light text-adara-primary',
        'rose' => 'bg-adara-surface/75 text-adara-primary',
        'mint' => 'bg-adara-surface/75 text-adara-success',
        'amber' => 'bg-adara-surface/75 text-adara-warning',
    ];
@endphp

<div {{ $attributes->class(['min-w-0 rounded-adara-card border border-adara-border p-5 shadow-adara-panel', $tones[$tone] ?? $tones['plain']]) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-xs font-semibold text-adara-readable-muted">{{ $label }}</p>
        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {{ $iconTones[$tone] ?? $iconTones['plain'] }}" aria-hidden="true">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="7" width="5" height="9" rx="1"/><rect x="12" y="3" width="5" height="13" rx="1"/></svg>
        </span>
    </div>
    <p class="mt-3 text-[26px] font-extrabold leading-none tracking-tight text-adara-ink tabular-nums">{{ $value }}</p>
    <p class="mt-2 text-[11px] leading-4 text-adara-readable-muted">{{ $detail }}</p>
</div>
