<style>
    @media (prefers-reduced-motion: no-preference) {
        .public-catalog-card {
            transition: transform 280ms cubic-bezier(0.2, 0.75, 0.25, 1), border-color 280ms ease, box-shadow 280ms ease;
        }

        .public-catalog-card:focus-within {
            transform: translateY(-5px);
            border-color: color-mix(in srgb, var(--adara-primary) 32%, var(--adara-border));
            box-shadow: 0 24px 56px rgb(84 51 57 / 14%);
        }

        .public-catalog-card__image,
        .public-catalog-card__arrow {
            transition: transform 500ms cubic-bezier(0.2, 0.75, 0.25, 1);
        }

        .public-catalog-card:focus-within .public-catalog-card__image {
            transform: scale(1.04);
        }

        .public-catalog-card:focus-within .public-catalog-card__arrow {
            transform: translateX(4px);
        }
    }

    @media (hover: hover) and (prefers-reduced-motion: no-preference) {
        .public-catalog-card:hover {
            transform: translateY(-5px);
            border-color: color-mix(in srgb, var(--adara-primary) 32%, var(--adara-border));
            box-shadow: 0 24px 56px rgb(84 51 57 / 14%);
        }

        .public-catalog-card:hover .public-catalog-card__image {
            transform: scale(1.04);
        }

        .public-catalog-card:hover .public-catalog-card__arrow {
            transform: translateX(4px);
        }
    }
</style>

<div class="min-h-dvh bg-[linear-gradient(135deg,#fffaf7_0%,#f7f0ed_58%,#fffaf7_100%)]">
    <header class="border-b border-adara-border/70 bg-adara-surface/95">
        <div class="mx-auto flex h-[72px] max-w-[1540px] items-center justify-between px-5 sm:px-8 lg:px-12">
            <span class="text-[26px] font-semibold tracking-[0.18em] text-adara-primary sm:text-[30px]">ADARA</span>
            <span aria-current="page" class="border-b-2 border-adara-primary pb-1 text-base font-semibold text-adara-primary">Catálogo</span>
        </div>
    </header>

    <main id="contenido" tabindex="-1">
        <section class="relative isolate overflow-hidden bg-[#f7e9e6]" aria-labelledby="catalog-title">
            <div class="absolute inset-0 -z-10 bg-cover bg-[position:70%_center] opacity-80 sm:bg-center" style="background-image: url('{{ asset('images/catalog-hero-botanical.png') }}')" aria-hidden="true"></div>
            <div class="absolute inset-0 -z-10 bg-gradient-to-r from-[#fffaf7]/70 via-[#fffaf7]/30 to-transparent" aria-hidden="true"></div>
            <div class="mx-auto max-w-[1540px] px-5 pt-10 pb-17 sm:px-8 sm:pt-11 sm:pb-19 lg:px-12 lg:pt-10 lg:pb-16">
                <p class="text-[11px] font-bold tracking-[0.33em] text-adara-primary uppercase">Belleza y cuidado personal</p>
                <h1 id="catalog-title" class="mt-4 max-w-[850px] font-display text-[34px] leading-[1.12] font-medium tracking-tight text-[#201925] sm:text-[46px] lg:text-[54px]">Descubrí nuestros productos</h1>
                <p class="mt-2 text-base text-adara-readable-muted sm:text-lg">Explorá el catálogo de Adara y encontrá lo que buscás.</p>
            </div>
        </section>

        <div class="relative mx-auto -mt-9 max-w-[1540px] px-5 pb-16 sm:px-8 lg:px-12">
            <div class="relative shadow-[0_10px_28px_rgb(85_45_58/10%)]">
                <label for="public-search" class="sr-only">Buscar productos por nombre</label>
                <svg class="pointer-events-none absolute top-1/2 left-5 h-6 w-6 -translate-y-1/2 text-adara-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.75" cy="10.75" r="7.25"/><path d="m16.3 16.3 5 5"/></svg>
                <input id="public-search" type="search" wire:model.live.debounce.300ms="search" maxlength="100" placeholder="Buscar productos..." class="h-[62px] w-full rounded-[13px] border border-adara-primary bg-adara-surface/95 pr-5 pl-15 text-base text-adara-ink placeholder:text-adara-readable-muted focus:border-adara-primary focus:outline-none focus:ring-2 focus:ring-adara-primary/20">
            </div>

            <div class="mt-8 grid items-start gap-6 lg:grid-cols-[270px_minmax(0,1fr)] xl:grid-cols-[300px_minmax(0,1fr)] xl:gap-8">
                <aside class="hidden rounded-[16px] border border-adara-border/80 bg-adara-surface/75 p-6 shadow-[0_16px_45px_rgb(81_47_55/5%)] lg:block" aria-label="Filtros de productos">
                    <div class="flex items-center gap-3">
                        <svg class="h-7 w-7 text-adara-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M3 5h18M3 12h18M3 19h18"/><circle cx="9" cy="5" r="2" fill="white"/><circle cx="16" cy="12" r="2" fill="white"/><circle cx="8" cy="19" r="2" fill="white"/></svg>
                        <h2 class="font-display text-[28px] leading-tight text-[#201925]">Filtros</h2>
                    </div>
                    <div class="mt-5 space-y-6 border-t border-adara-border pt-5">
                        <x-adara.select id="public-category" name="selectedCategory" label="Categoría" wire:model.live="selectedCategory">
                                <option value="">Todas las categorías</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                        </x-adara.select>
                        <div>
                            <x-adara.select id="public-subcategory" name="selectedSubcategory" label="Subcategoría" :disabled="$selectedCategory === ''" wire:model.live="selectedSubcategory">
                                <option value="">Todas las subcategorías</option>
                                @foreach ($subcategories as $subcategory)
                                    <option value="{{ $subcategory->id }}">{{ $subcategory->name }}</option>
                                @endforeach
                            </x-adara.select>
                            <p class="mt-3 text-sm leading-5 text-adara-readable-muted">Elegí una categoría para acotar las subcategorías.</p>
                        </div>
                    </div>
                    <div class="mt-7 border-t border-adara-border pt-6">
                        <button type="button" wire:click="clearFilters" class="flex min-h-12 w-full items-center justify-center gap-3 rounded-adara-control border border-adara-primary px-4 text-sm font-semibold text-adara-primary transition-colors hover:bg-adara-primary-light">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11a9 9 0 1 1 2.5 7"/><path d="M3 17v-6h6"/></svg>
                            Limpiar filtros
                        </button>
                    </div>
                </aside>

                <section aria-label="Resultados del catálogo" class="min-w-0">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h2 class="font-display text-[27px] leading-tight text-[#201925] sm:text-[32px]">Todos los productos</h2>
                            <p class="mt-1 text-sm text-adara-readable-muted">{{ $products->total() }} {{ $products->total() === 1 ? 'producto' : 'productos' }}</p>
                        </div>
                        <div class="hidden items-center gap-3 sm:flex">
                            <label for="public-sort" class="whitespace-nowrap text-sm text-adara-ink">Ordenar por:</label>
                            <select id="public-sort" wire:model.live="sort" class="min-h-11 w-[205px] rounded-adara-control border border-adara-border bg-adara-surface px-4 text-sm text-adara-ink focus:border-adara-primary focus:outline-none focus:ring-2 focus:ring-adara-primary/20">
                                <option value="">Destacados</option>
                                <option value="price_asc">Precio: menor a mayor</option>
                                <option value="price_desc">Precio: mayor a menor</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-5 flex gap-3 sm:hidden">
                        <details wire:ignore.self class="group relative min-w-0 flex-1">
                            <summary class="flex min-h-11 cursor-pointer list-none items-center justify-center gap-2 rounded-adara-control border border-adara-primary bg-adara-surface px-3 text-sm font-semibold text-adara-primary [&::-webkit-details-marker]:hidden">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M3 5h18M3 12h18M3 19h18"/><circle cx="9" cy="5" r="2" fill="white"/><circle cx="16" cy="12" r="2" fill="white"/><circle cx="8" cy="19" r="2" fill="white"/></svg>
                                Filtros
                            </summary>
                            <div class="absolute left-0 z-20 mt-2 w-[min(354px,calc(100vw-40px))] space-y-4 rounded-adara-card border border-adara-border bg-adara-surface p-5 shadow-xl">
                                <h3 class="font-display text-xl">Filtrar productos</h3>
                                <x-adara.select id="public-category-mobile" name="selectedCategory" label="Categoría" wire:model.live="selectedCategory">
                                        <option value="">Todas las categorías</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                                        @endforeach
                                </x-adara.select>
                                <x-adara.select id="public-subcategory-mobile" name="selectedSubcategory" label="Subcategoría" :disabled="$selectedCategory === ''" wire:model.live="selectedSubcategory">
                                        <option value="">Todas las subcategorías</option>
                                        @foreach ($subcategories as $subcategory)
                                            <option value="{{ $subcategory->id }}">{{ $subcategory->name }}</option>
                                        @endforeach
                                </x-adara.select>
                                <button type="button" wire:click="clearFilters" class="min-h-11 w-full rounded-adara-control border border-adara-primary px-3 text-sm font-semibold text-adara-primary">Limpiar filtros</button>
                            </div>
                        </details>
                        <div class="min-w-0 flex-1">
                            <label for="public-sort-mobile" class="sr-only">Ordenar por precio</label>
                            <select id="public-sort-mobile" wire:model.live="sort" class="min-h-11 w-full rounded-adara-control border border-adara-border bg-adara-surface px-2 text-sm text-adara-ink">
                                <option value="">Destacados</option>
                                <option value="price_asc">Menor a mayor</option>
                                <option value="price_desc">Mayor a menor</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-5 hidden sm:block lg:hidden">
                        <details wire:ignore.self class="relative">
                            <summary class="inline-flex min-h-11 cursor-pointer list-none items-center rounded-adara-control border border-adara-primary bg-adara-surface px-5 text-sm font-semibold text-adara-primary [&::-webkit-details-marker]:hidden">Filtros</summary>
                            <div class="absolute left-0 z-20 mt-2 w-[354px] space-y-4 rounded-adara-card border border-adara-border bg-adara-surface p-5 shadow-xl">
                                <x-adara.select id="public-category-tablet" name="selectedCategory" label="Categoría" wire:model.live="selectedCategory">
                                        <option value="">Todas las categorías</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                                        @endforeach
                                </x-adara.select>
                                <x-adara.select id="public-subcategory-tablet" name="selectedSubcategory" label="Subcategoría" :disabled="$selectedCategory === ''" wire:model.live="selectedSubcategory">
                                        <option value="">Todas las subcategorías</option>
                                        @foreach ($subcategories as $subcategory)
                                            <option value="{{ $subcategory->id }}">{{ $subcategory->name }}</option>
                                        @endforeach
                                </x-adara.select>
                                <button type="button" wire:click="clearFilters" class="min-h-11 w-full rounded-adara-control border border-adara-primary px-3 text-sm font-semibold text-adara-primary">Limpiar filtros</button>
                            </div>
                        </details>
                    </div>

                    <p wire:loading.delay wire:target="search,selectedCategory,selectedSubcategory,sort,clearFilters" role="status" class="mt-4 text-sm text-adara-readable-muted">Actualizando productos…</p>
                    @if ($products->isEmpty())
                        <div class="mt-5 rounded-adara-card border border-adara-border bg-adara-surface px-6 py-12 text-center shadow-adara-panel">
                            <h3 class="font-display text-xl font-semibold">No encontramos productos</h3>
                            <p class="mt-2 text-sm text-adara-readable-muted">Probá con otro nombre o cambiá los filtros.</p>
                            <button type="button" wire:click="clearFilters" class="mt-5 min-h-11 rounded-adara-control bg-adara-primary px-5 text-sm font-semibold text-white">Limpiar filtros</button>
                        </div>
                    @else
                        <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3" role="list">
                            @foreach ($products as $product)
                                <article wire:key="public-product-{{ $product->id }}" role="listitem" class="group public-catalog-card flex min-w-0 flex-col overflow-hidden rounded-[18px] border border-adara-border/80 bg-adara-surface shadow-[0_16px_40px_rgb(84_51_57/8%)]">
                                    <div class="p-4 pb-0">
                                        @if ($product->group->primaryImage)
                                            <div class="flex h-[190px] items-center justify-center overflow-hidden rounded-[9px] bg-white sm:h-[205px]">
                                                <img src="{{ route('catalog.images.show', $product->group->primaryImage) }}" alt="" class="public-catalog-card__image h-full w-full object-contain" loading="lazy">
                                            </div>
                                        @else
                                            <div class="flex h-[190px] flex-col items-center justify-center gap-2 rounded-[9px] bg-gradient-to-br from-[#f9eeee] to-[#f4e5e7] text-[#a66178] sm:h-[205px]">
                                                <svg class="public-catalog-card__image h-16 w-20" viewBox="0 0 80 64" fill="none" stroke="currentColor" stroke-width="1.15" aria-hidden="true"><path d="M23 20c0-4 34-4 34 0v4H23zM20 24h40v27c0 3-3 5-6 5H26c-3 0-6-2-6-5zM18 19c0-6 44-6 44 0"/><path d="M64 16c8-4 10-10 9-13-8 1-12 6-9 13ZM66 17c-1-5-5-8-9-9 0 5 3 9 9 9Z"/></svg>
                                                <span class="text-sm">Imagen no disponible</span>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex flex-1 flex-col px-5 pt-4 pb-5">
                                        <p class="self-start rounded-full bg-[#f6dedf] px-3 py-1 text-[11px] font-bold text-adara-primary uppercase">{{ $product->group->category?->name ?? 'Cosmética' }}</p>
                                        <h3 class="mt-2 text-[18px] leading-snug font-semibold text-[#201925]">{{ $product->group->name }}</h3>
                                        <p class="mt-1 text-sm text-adara-readable-muted">{{ $product->name !== $product->group->name ? $product->name : 'Unidad' }}</p>
                                        <p class="mt-5 flex items-center gap-2 text-[17px] font-semibold tabular-nums {{ $product->current_price_cents === null ? 'text-[#987b7d]' : 'text-[#201925]' }}">
                                            <svg class="h-5 w-5 shrink-0 text-adara-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 4h10l8 8-8 8-10-10z"/><circle cx="8" cy="8" r="1"/></svg>
                                            {{ $product->current_price_cents === null ? 'Precio pendiente' : '$ '.number_format(intdiv($product->current_price_cents, 100), 0, ',', '.') }}
                                        </p>
                                        <div class="mt-auto pt-4">
                                            <div class="border-t border-adara-border pt-4">
                                                <button type="button" x-on:click="window.dispatchEvent(new CustomEvent('adara:open', { detail: { id: 'public-product-detail' } })); Livewire.dispatch('public-product-detail-load', { variantId: {{ $product->id }} })" aria-label="Ver detalle de {{ $product->group->name }}" class="inline-flex min-h-8 items-center gap-2 text-sm font-semibold text-adara-primary hover:underline">Ver detalle <span aria-hidden="true" class="public-catalog-card__arrow text-xl leading-none">→</span></button>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                        @if ($products->hasPages())
                            <div class="mt-8">{{ $products->links('components.adara.pagination', data: ['scrollTo' => false]) }}</div>
                        @endif
                    @endif
                </section>
            </div>
        </div>

        <livewire:public-catalog-product-detail />
        {{-- modal moved to isolated detail component --}}
        {{--
        <x-adara.modal id="public-product-detail" title="Detalle del producto" wide bare wire:ignore.self class="!max-w-[1016px]">
            @if ($selectedProduct)
                <article class="relative grid sm:grid-cols-[48%_52%]">
                    <button x-ref="initial" type="button" x-on:click="close()" aria-label="Cerrar detalle" class="absolute top-5 right-5 z-10 flex h-10 w-10 items-center justify-center rounded-full border border-adara-primary bg-adara-surface/90 text-adara-primary transition-colors hover:bg-adara-primary-light">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M5 5 19 19M19 5 5 19"/></svg>
                    </button>
                    <div class="relative m-4 flex min-h-[250px] items-center justify-center overflow-hidden rounded-[13px] bg-[#f4dfdc] sm:mr-0 sm:min-h-[560px]">
                        <div class="absolute inset-0 bg-cover bg-[position:72%_center] opacity-70" style="background-image: url('{{ asset('images/catalog-hero-botanical.png') }}')" aria-hidden="true"></div>
                        @if ($selectedProduct->group->primaryImage)
                            <img src="{{ route('catalog.images.show', $selectedProduct->group->primaryImage) }}" alt="{{ $selectedProduct->group->name }}" class="relative z-10 h-[230px] w-[85%] object-contain mix-blend-multiply sm:h-[410px]">
                        @else
                            <div class="relative z-10 flex flex-col items-center gap-3 rounded-adara-control bg-adara-surface/80 px-8 py-7 text-[#a66178]">
                                <svg class="h-20 w-24" viewBox="0 0 80 64" fill="none" stroke="currentColor" stroke-width="1.15" aria-hidden="true"><path d="M23 20c0-4 34-4 34 0v4H23zM20 24h40v27c0 3-3 5-6 5H26c-3 0-6-2-6-5zM18 19c0-6 44-6 44 0"/><path d="M64 16c8-4 10-10 9-13-8 1-12 6-9 13ZM66 17c-1-5-5-8-9-9 0 5 3 9 9 9Z"/></svg>
                                <span class="text-sm">Imagen no disponible</span>
                            </div>
                        @endif
                    </div>
                    <div class="flex min-w-0 flex-col px-6 pt-6 pb-6 sm:pr-8 sm:pl-9 sm:pt-15">
                        <p class="text-[11px] font-bold tracking-[0.32em] text-adara-primary uppercase">{{ $selectedProduct->group->category?->name ?? 'Cosmética' }}</p>
                        <h2 id="public-product-detail-title" class="mt-3 font-display text-[31px] leading-tight text-[#201925] sm:text-[38px]">{{ $selectedProduct->group->name }}</h2>
                        <p class="mt-1 text-base text-adara-readable-muted">{{ $selectedProduct->name }}</p>
                        <div class="mt-5 border-t border-adara-border pt-4">
                            <p class="text-sm font-semibold text-adara-primary">Precio</p>
                            <p class="mt-1 font-display text-[26px] leading-tight tabular-nums text-adara-primary sm:text-[30px]">{{ $selectedProduct->current_price_cents === null ? 'Precio pendiente' : '$ '.number_format(intdiv($selectedProduct->current_price_cents, 100), 0, ',', '.') }}</p>
                        </div>
                        <div class="mt-5 grid grid-cols-2 border-t border-adara-border pt-4">
                            <div class="min-w-0 pr-4">
                                <p class="text-sm font-semibold">Categoría</p>
                                <p class="mt-2 text-sm text-adara-readable-muted">{{ $selectedProduct->group->category?->name ?? 'Cosmética' }}</p>
                            </div>
                            <div class="min-w-0 border-l border-adara-border pl-4">
                                <p class="text-sm font-semibold">Presentación</p>
                                <p class="mt-2 text-sm text-adara-readable-muted">{{ $selectedProduct->name }}</p>
                            </div>
                        </div>
                        <div class="mt-5 border-t border-adara-border pt-4">
                            <h3 class="text-sm font-semibold">Descripción</h3>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-adara-readable-muted {{ $selectedProduct->group->description ? '' : 'italic' }}">{{ $selectedProduct->group->description ?: 'No hay una descripción disponible para este producto.' }}</p>
                        </div>
                        <button type="button" x-on:click="close()" class="mt-8 min-h-11 self-end rounded-adara-control border border-adara-primary px-8 text-sm font-semibold text-adara-primary hover:bg-adara-primary-light sm:mt-auto">Cerrar</button>
                    </div>
                </article>
            @endif
        </x-adara.modal>
        --}}
    </main>
</div>
