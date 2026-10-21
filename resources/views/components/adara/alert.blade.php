@props(['tone' => 'info', 'title' => null])

@php
    $tones = [
        'info' => 'border-adara-border bg-adara-primary-light text-adara-ink',
        'success' => 'border-adara-success/20 bg-adara-success-bg text-adara-success',
        'warning' => 'border-adara-warning/20 bg-adara-warning-bg text-adara-ink',
        'danger' => 'border-adara-danger/20 bg-adara-danger-bg text-adara-danger',
    ];
@endphp

<div role="{{ $tone === 'danger' ? 'alert' : 'status' }}" aria-live="{{ $tone === 'danger' ? 'assertive' : 'polite' }}" {{ $attributes->class(['rounded-adara-control border px-4 py-3 text-sm leading-6', $tones[$tone] ?? $tones['info']]) }}>
    @if ($title)
        <p class="font-bold">{{ $title }}</p>
    @endif
    <div>{{ $slot }}</div>
</div>
