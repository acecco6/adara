<x-adara.modal id="public-product-detail" title="Detalle del producto" wide bare on-close-event="public-product-detail-reset" wire:ignore.self class="!max-w-[1016px]">
    @if ($product)
        <article class="relative grid sm:grid-cols-[48%_52%]">
            <button x-ref="initial" type="button" x-on:click="close()" aria-label="Cerrar detalle" class="absolute top-5 right-5 z-10 flex h-10 w-10 items-center justify-center rounded-full border border-adara-primary bg-adara-surface/90 text-adara-primary">
                <span aria-hidden="true" class="text-xl">×</span>
            </button>
            <div class="relative m-4 flex min-h-[250px] items-center justify-center overflow-hidden rounded-[13px] bg-[#f4dfdc] sm:mr-0 sm:min-h-[560px]">
                <div class="absolute inset-0 bg-cover bg-[position:72%_center] opacity-70" style="background-image: url('{{ asset('images/catalog-hero-botanical.png') }}')" aria-hidden="true"></div>
                @if ($product->group->primaryImage)
                    <img src="{{ route('catalog.images.show', $product->group->primaryImage) }}" alt="{{ $product->group->name }}" class="relative z-10 h-[230px] w-[85%] object-contain mix-blend-multiply sm:h-[410px]">
                @else
                    <span class="relative z-10 rounded-adara-control bg-adara-surface/80 px-8 py-7 text-[#a66178]">Imagen no disponible</span>
                @endif
            </div>
            <div class="flex min-w-0 flex-col px-6 pt-6 pb-6 sm:pr-8 sm:pl-9 sm:pt-15">
                <p class="text-[11px] font-bold tracking-[0.32em] text-adara-primary uppercase">{{ $product->group->category?->name ?? 'Cosmética' }}</p>
                <h2 id="public-product-detail-title" class="mt-3 font-display text-[31px] leading-tight sm:text-[38px]">{{ $product->group->name }}</h2>
                <p class="mt-1 text-base text-adara-readable-muted">{{ $product->name }}</p>
                <div class="mt-5 border-t border-adara-border pt-4"><p class="text-sm font-semibold text-adara-primary">Precio</p><p class="mt-1 font-display text-[26px] text-adara-primary">{{ $product->current_price_cents === null ? 'Precio pendiente' : '$ '.number_format(intdiv($product->current_price_cents, 100), 0, ',', '.') }}</p></div>
                <div class="mt-5 grid grid-cols-2 border-t border-adara-border pt-4"><div><p class="text-sm font-semibold">Categoría</p><p class="mt-2 text-sm text-adara-readable-muted">{{ $product->group->category?->name ?? 'Cosmética' }}</p></div><div class="border-l border-adara-border pl-4"><p class="text-sm font-semibold">Presentación</p><p class="mt-2 text-sm text-adara-readable-muted">{{ $product->name }}</p></div></div>
                <div class="mt-5 border-t border-adara-border pt-4"><h3 class="text-sm font-semibold">Descripción</h3><p class="mt-2 whitespace-pre-line text-sm leading-6 text-adara-readable-muted {{ $product->group->description ? '' : 'italic' }}">{{ $product->group->description ?: 'No hay una descripción disponible para este producto.' }}</p></div>
                <button type="button" x-on:click="close()" class="mt-8 min-h-11 self-end rounded-adara-control border border-adara-primary px-8 text-sm font-semibold text-adara-primary sm:mt-auto">Cerrar</button>
            </div>
        </article>
    @elseif ($loadError !== '')
        <div class="p-8 text-center" role="alert"><p>{{ $loadError }}</p><button x-on:click="close()" class="mt-6 rounded-adara-control border border-adara-primary px-8 py-3 text-adara-primary">Cerrar</button></div>
    @else
        <div class="grid animate-pulse sm:grid-cols-2" aria-label="Cargando detalle del producto" aria-busy="true"><div class="m-4 min-h-[250px] rounded-adara-control bg-[#f4e5e7] sm:min-h-[560px]"></div><div class="space-y-5 p-8"><div class="h-4 w-24 rounded bg-[#f4e5e7]"></div><div class="h-10 w-3/4 rounded bg-[#f4e5e7]"></div><div class="h-20 rounded bg-[#f4e5e7]"></div></div></div>
    @endif
</x-adara.modal>
