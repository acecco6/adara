@props(['tone' => 'neutral'])

@php
    $tones = [
        'neutral' => 'bg-adara-background text-adara-ink',
        'success' => 'bg-adara-success-bg text-adara-success',
        'warning' => 'bg-adara-warning-bg text-adara-warning',
        'danger' => 'bg-adara-danger-bg text-adara-danger',
    ];
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-full px-3 py-1.5 text-xs font-semibold', $tones[$tone] ?? $tones['neutral']]) }}>{{ $slot }}</span>
