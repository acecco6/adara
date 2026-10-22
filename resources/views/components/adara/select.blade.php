@props(['id', 'label', 'name' => null, 'hint' => null, 'error' => null, 'required' => false, 'searchable' => false, 'searchPlaceholder' => null])

@php
    $fieldName = $name ?? $id;
    $errorMessage = $error ?: ($errors->first($fieldName) ?: null);
    $describedBy = implode(' ', array_filter([
        $hint ? $id.'-hint' : null,
        $errorMessage ? $id.'-error' : null,
    ]));
@endphp

<div data-adara-select-root class="relative flex min-w-0 flex-col gap-2">
    <label id="{{ $id }}-label" for="{{ $id }}" class="text-sm font-semibold text-adara-ink">
        {{ $label }}
        @if ($required)
            <span class="text-adara-danger" aria-hidden="true">*</span>
            <span class="sr-only">(obligatorio)</span>
        @endif
    </label>
    <select id="{{ $id }}" name="{{ $fieldName }}" data-adara-select-native @required($required) @if ($errorMessage) aria-invalid="true" @endif @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif {{ $attributes->class([
        'block min-h-11 w-full rounded-adara-control border bg-adara-surface px-3 py-2.5 text-sm text-adara-ink disabled:cursor-not-allowed disabled:bg-adara-background disabled:text-adara-readable-muted',
        'border-adara-danger' => $errorMessage,
        'border-adara-border' => ! $errorMessage,
    ]) }}>
        {{ $slot }}
    </select>
    <div data-adara-select-ui class="relative hidden">
        <button id="{{ $id }}-trigger" type="button" data-adara-select-trigger aria-haspopup="listbox" aria-expanded="false" aria-controls="{{ $id }}-listbox" aria-labelledby="{{ $id }}-label {{ $id }}-selected" @if ($errorMessage) aria-invalid="true" @endif @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif class="flex min-h-11 w-full items-center justify-between gap-3 rounded-adara-control border bg-adara-surface px-3 py-2.5 text-left text-sm text-adara-ink transition-colors duration-150 hover:border-adara-primary disabled:cursor-not-allowed disabled:bg-adara-background disabled:text-adara-readable-muted {{ $errorMessage ? 'border-adara-danger' : 'border-adara-border' }}">
            <span id="{{ $id }}-selected" data-adara-select-value class="min-w-0 truncate"></span>
            <svg class="h-4 w-4 shrink-0 text-adara-readable-muted transition-transform duration-150" data-adara-select-chevron viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>
        <div data-adara-select-dropdown class="absolute inset-x-0 top-full z-50 mt-2 hidden overflow-hidden rounded-adara-control border border-adara-border bg-adara-surface shadow-adara-panel">
            @if ($searchable)
                <div class="p-1">
                    <input id="{{ $id }}-search" type="search" data-adara-select-search role="searchbox" aria-label="Buscar en {{ mb_strtolower($label) }}" aria-controls="{{ $id }}-listbox" autocomplete="off" placeholder="{{ $searchPlaceholder ?? 'Buscar por nombre o código' }}" class="block min-h-10 w-full rounded-adara-control border border-adara-border bg-adara-surface px-3 py-2 text-sm text-adara-ink placeholder:text-adara-readable-muted focus:border-adara-primary focus:outline-none focus:ring-2 focus:ring-adara-primary/20">
                </div>
            @endif
            <div id="{{ $id }}-listbox" data-adara-select-listbox role="listbox" aria-labelledby="{{ $id }}-label" class="max-h-60 overflow-y-auto p-1"></div>
            <p data-adara-select-empty role="status" class="hidden px-3 py-2 text-sm text-adara-readable-muted"></p>
        </div>
    </div>
    @if ($hint)
        <p id="{{ $id }}-hint" class="text-xs leading-5 text-adara-readable-muted">{{ $hint }}</p>
    @endif
    @if ($errorMessage)
        <p id="{{ $id }}-error" role="alert" class="text-xs font-medium leading-5 text-adara-danger">{{ $errorMessage }}</p>
    @endif
    <p id="{{ $id }}-validation" data-adara-select-validation role="alert" class="hidden text-xs font-medium leading-5 text-adara-danger">Seleccioná una opción.</p>
</div>
