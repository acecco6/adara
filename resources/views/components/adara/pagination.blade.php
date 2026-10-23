@if ($paginator->hasPages())
    @php
        $pageName = $paginator->getPageName();
        $buttonClass = 'inline-flex min-h-10 min-w-10 items-center justify-center rounded-adara-control border border-adara-border bg-adara-surface px-3 text-sm font-semibold text-adara-ink transition-colors hover:border-adara-primary hover:bg-adara-primary-light hover:text-adara-primary disabled:cursor-wait disabled:opacity-60';
    @endphp

    <nav
        role="navigation"
        aria-label="Paginación"
        class="flex flex-wrap items-center justify-between gap-3"
        x-on:click.capture="if ($event.target.closest('button')) $el.closest('section')?.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' })"
    >
        <p class="text-sm text-adara-readable-muted">
            Mostrando <span class="font-semibold tabular-nums text-adara-ink">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span> de <span class="font-semibold tabular-nums text-adara-ink">{{ $paginator->total() }}</span>
        </p>

        <div class="flex flex-wrap items-center gap-1.5">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" aria-label="Página anterior" class="inline-flex min-h-10 items-center gap-1.5 rounded-adara-control border border-adara-border bg-adara-background px-3 text-sm font-semibold text-adara-readable-muted opacity-60">← <span class="hidden sm:inline">Anterior</span></span>
            @else
                <button type="button" wire:click="previousPage('{{ $pageName }}')" wire:loading.attr="disabled" rel="prev" aria-label="Página anterior" class="{{ $buttonClass }} gap-1.5">← <span class="hidden sm:inline">Anterior</span></button>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span aria-hidden="true" class="px-1 text-adara-readable-muted">{{ $element }}</span>
                @else
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span aria-current="page" aria-label="Página {{ $page }}" class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-adara-control bg-adara-primary px-3 text-sm font-bold tabular-nums text-white">{{ $page }}</span>
                        @else
                            <button type="button" wire:key="paginator-{{ $pageName }}-page-{{ $page }}" wire:click="gotoPage({{ $page }}, '{{ $pageName }}')" wire:loading.attr="disabled" aria-label="Ir a la página {{ $page }}" class="{{ $buttonClass }} tabular-nums">{{ $page }}</button>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <button type="button" wire:click="nextPage('{{ $pageName }}')" wire:loading.attr="disabled" rel="next" aria-label="Página siguiente" class="{{ $buttonClass }} gap-1.5"><span class="hidden sm:inline">Siguiente</span> →</button>
            @else
                <span aria-disabled="true" aria-label="Página siguiente" class="inline-flex min-h-10 items-center gap-1.5 rounded-adara-control border border-adara-border bg-adara-background px-3 text-sm font-semibold text-adara-readable-muted opacity-60"><span class="hidden sm:inline">Siguiente</span> →</span>
            @endif
        </div>
    </nav>
@endif
