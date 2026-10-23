<div class="min-h-dvh">
    <header class="border-b border-adara-border bg-adara-surface">
        <div class="mx-auto flex max-w-[1440px] items-center justify-between px-4 py-4 sm:px-7 lg:px-12">
            <span class="text-xl font-bold tracking-wide text-adara-primary sm:text-2xl">ADARA</span>
            <a href="{{ route('catalog.public') }}" class="inline-flex min-h-10 items-center text-sm font-semibold text-adara-primary hover:underline">Catálogo</a>
        </div>
    </header>

    <main id="contenido" class="mx-auto max-w-[1100px] px-4 py-7 sm:px-7 sm:py-10 lg:px-12" tabindex="-1">
        <a href="{{ route('catalog.public') }}" class="inline-flex min-h-10 items-center text-sm font-semibold text-adara-primary hover:underline">← Volver al catálogo</a>
        <article class="mt-5 grid overflow-hidden rounded-adara-card border border-adara-border bg-adara-surface md:grid-cols-2">
            <div class="flex aspect-square items-center justify-center bg-adara-primary-light">
                @if ($product->group->primaryImage)
                    <img src="{{ route('catalog.images.show', $product->group->primaryImage) }}" alt="{{ $product->group->name }}" class="h-full w-full object-cover">
                @else
                    <span aria-hidden="true" class="font-display text-7xl text-adara-primary">✦</span>
                @endif
            </div>
            <div class="flex flex-col justify-center gap-4 p-6 sm:p-8">
                <p class="text-xs font-bold tracking-wide text-adara-primary uppercase">{{ $product->group->category?->name ?? 'Cosmética' }}@if ($product->group->subcategory) · {{ $product->group->subcategory->name }}@endif</p>
                <div>
                    <h1 class="font-display text-3xl leading-tight font-semibold">{{ $product->group->name }}</h1>
                    <p class="mt-2 text-sm text-adara-readable-muted">{{ $product->name }}</p>
                </div>
                <p class="text-2xl font-bold tabular-nums">{{ $product->current_price_cents === null ? 'Precio pendiente' : '$ '.number_format(intdiv($product->current_price_cents, 100), 0, ',', '.') }}</p>
                @if ($product->group->description)
                    <div class="border-t border-adara-border pt-4">
                        <h2 class="text-sm font-semibold">Sobre este producto</h2>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-adara-readable-muted">{{ $product->group->description }}</p>
                    </div>
                @endif
            </div>
        </article>
    </main>
</div>
