@props(['id', 'label', 'name' => null, 'hint' => null, 'error' => null, 'required' => false])

@php
    $fieldName = $name ?? $id;
    $errorMessage = $error ?: ($errors->first($fieldName) ?: null);
    $describedBy = implode(' ', array_filter([
        $hint ? $id.'-hint' : null,
        $errorMessage ? $id.'-error' : null,
    ]));
@endphp

<div class="flex min-w-0 flex-col gap-2">
    <label for="{{ $id }}" class="text-sm font-semibold text-adara-ink">
        {{ $label }}
        @if ($required)
            <span class="text-adara-danger" aria-hidden="true">*</span>
            <span class="sr-only">(obligatorio)</span>
        @endif
    </label>
    <textarea id="{{ $id }}" name="{{ $fieldName }}" @required($required) @if ($errorMessage) aria-invalid="true" @endif @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif {{ $attributes->class([
        'block min-h-28 w-full resize-y rounded-adara-control border bg-adara-surface px-3 py-2.5 text-sm text-adara-ink placeholder:text-adara-readable-muted disabled:cursor-not-allowed disabled:bg-adara-background disabled:text-adara-readable-muted',
        'border-adara-danger' => $errorMessage,
        'border-adara-border' => ! $errorMessage,
    ]) }}>{{ $slot }}</textarea>
    @if ($hint)
        <p id="{{ $id }}-hint" class="text-xs leading-5 text-adara-readable-muted">{{ $hint }}</p>
    @endif
    @if ($errorMessage)
        <p id="{{ $id }}-error" role="alert" class="text-xs font-medium leading-5 text-adara-danger">{{ $errorMessage }}</p>
    @endif
</div>
