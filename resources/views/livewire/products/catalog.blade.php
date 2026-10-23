<div class="flex flex-col gap-6">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-adara-primary">Catálogo</p>
            <h1 class="mt-2 font-display text-[30px] font-semibold leading-tight text-adara-ink">Productos</h1>
            <p class="mt-1 max-w-2xl text-[13px] leading-5 text-adara-readable-muted">Precios y disponibilidad de todo lo que vendés, en un solo lugar.</p>
        </div>
        <x-adara.button x-on:click="$dispatch('adara:open', { id: 'nuevo-producto' })" aria-haspopup="dialog" aria-controls="nuevo-producto">Agregar producto</x-adara.button>
    </header>

    <section class="grid gap-3 md:grid-cols-3" aria-label="Resumen del catálogo">
        <x-adara.kpi-card label="Productos activos" :value="number_format($activeProducts, 0, ',', '.')" detail="Con alguna presentación activa" />
        <x-adara.kpi-card label="Presentaciones activas" :value="number_format($activePresentations, 0, ',', '.')" detail="Códigos disponibles en el catálogo" tone="rose" />
        <x-adara.kpi-card label="Con stock" :value="number_format($presentationsInStock, 0, ',', '.')" detail="Presentaciones activas con unidades" tone="mint" />
    </section>

    <x-adara.modal id="nuevo-producto" title="Nuevo producto" description="Empezá con una presentación. Después podrás agregar otras en su detalle." :wide="true" wire:ignore.self>
        <form id="nuevo-producto-form" wire:submit="save" class="grid gap-5 md:grid-cols-2">
            <x-adara.input id="product-name" name="name" label="Nombre del producto" wire:model="name" required />
            <x-adara.input id="product-presentation" name="presentation" label="Primera presentación" hint="Por ejemplo, 50 ml o unidad." wire:model="presentation" required />
            <x-adara.select id="product-category" name="category_id" label="Categoría" wire:model.live="categoryId">
                <option value="">Sin categoría</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </x-adara.select>
            <x-adara.select id="product-subcategory" name="subcategory_id" label="Subcategoría" wire:model="subcategoryId" :disabled="$categoryId === ''">
                <option value="">Sin subcategoría</option>
                @foreach ($subcategories->where('category_id', (int) $categoryId) as $subcategory)
                    <option value="{{ $subcategory->id }}">{{ $subcategory->name }}</option>
                @endforeach
            </x-adara.select>
            <x-adara.input id="product-sku" name="sku" label="Código SKU" hint="Dejalo vacío para generar uno automáticamente." wire:model="sku" />
            <x-adara.textarea id="product-description" name="description" label="Descripción" wire:model="description" />
            <div class="md:col-span-2"><label for="product-photos" class="block text-sm font-semibold text-adara-ink">Fotos del producto</label><input id="product-photos" type="file" wire:model="photos" accept="image/jpeg,image/png,image/webp" multiple class="mt-2 block w-full rounded-adara-control border border-adara-border bg-adara-surface p-3 text-sm file:mr-3 file:rounded-adara-control file:border-0 file:bg-adara-primary-light file:px-3 file:py-2 file:font-semibold file:text-adara-primary"><p class="mt-1 text-xs text-adara-readable-muted">Hasta 5 fotos JPG, PNG o WebP de 2 MB cada una. La primera será la portada. Adara guarda la versión que ocupa menos espacio.</p><span wire:loading wire:target="photos" role="status" class="mt-1 block text-sm text-adara-readable-muted">Cargando fotos…</span>@error('photos') <p role="alert" class="mt-1 text-xs text-adara-danger">{{ $message }}</p> @enderror @error('photos.*') <p role="alert" class="mt-1 text-xs text-adara-danger">{{ $message }}</p> @enderror</div>
            <fieldset class="md:col-span-2">
                <legend class="text-sm font-semibold text-adara-ink">Proveedores</legend>
                @if ($suppliers->isEmpty())
                    <p class="mt-2 text-sm text-adara-readable-muted">Todavía no hay proveedores activos. <a href="{{ route('suppliers.index') }}" wire:navigate class="font-semibold text-adara-primary underline">Agregar proveedor</a></p>
                @else
                    <div class="mt-2 grid max-h-32 gap-2 overflow-y-auto rounded-adara-control border border-adara-border p-3 sm:grid-cols-2">
                        @foreach ($suppliers as $supplier)
                            <label wire:key="supplier-option-{{ $supplier->id }}" class="flex min-h-9 items-center gap-2 text-sm"><input type="checkbox" value="{{ $supplier->id }}" wire:model="supplierIds" class="accent-adara-primary">{{ $supplier->name }}</label>
                        @endforeach
                    </div>
                @endif
                @if ($errors->first('supplier_ids') || $errors->first('supplier_ids.*')) <p role="alert" class="mt-2 text-xs text-adara-danger">{{ $errors->first('supplier_ids') ?: $errors->first('supplier_ids.*') }}</p> @endif
            </fieldset>
        </form>
        <x-slot:footer>
            <span wire:loading wire:target="save" role="status" class="mr-auto self-center text-sm text-adara-readable-muted">Guardando…</span>
            <x-adara.button variant="outline" x-on:click="close()">Cancelar</x-adara.button>
            <x-adara.button type="submit" form="nuevo-producto-form" wire:loading.attr="disabled" wire:target="save">Guardar producto</x-adara.button>
        </x-slot:footer>
    </x-adara.modal>

    <section class="overflow-hidden rounded-adara-card border border-adara-border bg-adara-surface shadow-adara-panel" aria-label="Listado de productos">
        <div class="grid gap-3 border-b border-adara-border p-4 sm:grid-cols-2 sm:items-end sm:p-5 xl:grid-cols-[minmax(0,1fr)_170px_170px_170px]">
            <x-adara.input id="product-search" name="search" type="search" label="Buscar producto o código" wire:model.live.debounce.300ms="search" placeholder="Buscar por nombre o código..." />
            <x-adara.select id="product-category-filter" name="selectedCategory" label="Categoría" wire:model.live="selectedCategory">
                <option value="">Todas</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </x-adara.select>
            <x-adara.select id="product-subcategory-filter" name="selectedSubcategory" label="Subcategoría" wire:model.live="selectedSubcategory" :disabled="$selectedCategory === ''">
                <option value="">Todas</option>
                @foreach ($subcategories->where('category_id', (int) $selectedCategory) as $subcategory)
                    <option value="{{ $subcategory->id }}">{{ $subcategory->name }}</option>
                @endforeach
            </x-adara.select>
            <x-adara.select id="product-status" name="status" label="Estado" wire:model.live="status">
                <option value="all">Todos</option>
                <option value="active">Con presentaciones activas</option>
                <option value="archived">Con presentaciones archivadas</option>
            </x-adara.select>
        </div>
        <div wire:loading.delay wire:target="search,status,selectedCategory,selectedSubcategory" role="status" class="px-5 py-2 text-sm text-adara-readable-muted">Actualizando listado…</div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[840px] text-left text-xs" aria-label="Productos">
                <thead class="bg-adara-background/70 text-[10px] font-semibold uppercase tracking-[0.08em] text-adara-readable-muted">
                    <tr>
                        <th scope="col" class="px-4 py-3 sm:px-5">Producto</th>
                        <th scope="col" class="px-4 py-3">Presentación</th>
                        <th scope="col" class="px-4 py-3">Categoría</th>
                        <th scope="col" class="px-4 py-3">Precio</th>
                        <th scope="col" class="px-4 py-3">Stock</th>
                        <th scope="col" class="px-4 py-3">Estado</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">Detalle</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-adara-border">
                    @forelse ($groups as $group)
                        @forelse ($group->variants as $variant)
                            @php
                                $stock = $variant->balance?->quantity_on_hand ?? 0;
                                $minimum = $variant->minimum_stock;
                            @endphp
                            <tr wire:key="product-variant-{{ $variant->id }}" class="hover:bg-adara-background/50">
                                <td class="px-4 py-3 sm:px-5"><a href="{{ route('products.show', $group) }}" wire:navigate class="flex items-center gap-3 font-bold text-adara-ink hover:text-adara-primary">@if ($group->primaryImage)<img src="{{ route('products.images.show', $group->primaryImage) }}" alt="" class="h-9 w-9 shrink-0 rounded-xl object-cover">@else<span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-adara-primary-light text-adara-primary" aria-hidden="true">✦</span>@endif<span class="min-w-0 truncate">{{ $group->name }}</span></a></td>
                                <td class="px-4 py-3"><span class="block font-semibold text-adara-ink">{{ $variant->name }}</span><span class="mt-0.5 block text-[10px] text-adara-readable-muted">{{ $variant->sku }}</span></td>
                                <td class="px-4 py-3 text-adara-readable-muted">{{ $group->category?->name ?? 'Sin categoría' }}@if ($group->subcategory)<span class="block text-[10px]">{{ $group->subcategory->name }}</span>@endif</td>
                                <td class="px-4 py-3 font-bold tabular-nums text-adara-ink">{{ $variant->current_price_cents === null ? 'Pendiente' : '$ '.number_format($variant->current_price_cents / 100, 2, ',', '.') }}</td>
                                <td class="px-4 py-3"><strong class="tabular-nums">{{ $stock }} u.</strong><span class="mt-1 block h-1 w-16 overflow-hidden rounded-full bg-adara-border"><span class="block h-full rounded-full {{ $stock <= $minimum ? 'bg-adara-warning' : 'bg-adara-success' }}" style="width: {{ min(100, $minimum > 0 ? (int) round($stock * 100 / max(1, $minimum * 2)) : ($stock > 0 ? 100 : 0)) }}%"></span></span></td>
                                <td class="px-4 py-3"><x-adara.badge :tone="! $variant->is_active ? 'neutral' : ($stock <= $minimum ? 'warning' : 'success')">{{ ! $variant->is_active ? 'Archivado' : ($stock <= $minimum ? 'Stock bajo' : 'Disponible') }}</x-adara.badge></td>
                                <td class="px-4 py-3 text-right"><a href="{{ route('products.show', $group) }}" wire:navigate class="inline-flex min-h-9 items-center rounded-adara-control px-2 font-bold text-adara-primary hover:bg-adara-primary-light" aria-label="Ver {{ $group->name }}, {{ $variant->name }}">Ver →</a></td>
                            </tr>
                        @empty
                            <tr wire:key="product-empty-{{ $group->id }}" class="hover:bg-adara-background/50">
                                <td class="px-4 py-3 font-bold text-adara-ink sm:px-5"><a href="{{ route('products.show', $group) }}" wire:navigate class="hover:text-adara-primary">{{ $group->name }}</a></td>
                                <td class="px-4 py-3 text-adara-readable-muted">Sin presentaciones</td>
                                <td class="px-4 py-3 text-adara-readable-muted">{{ $group->category?->name ?? 'Sin categoría' }}</td>
                                <td class="px-4 py-3 text-adara-readable-muted">Pendiente</td>
                                <td class="px-4 py-3 text-adara-readable-muted">—</td>
                                <td class="px-4 py-3"><x-adara.badge tone="neutral">Sin presentación</x-adara.badge></td>
                                <td class="px-4 py-3 text-right"><a href="{{ route('products.show', $group) }}" wire:navigate class="inline-flex min-h-9 items-center rounded-adara-control px-2 font-bold text-adara-primary hover:bg-adara-primary-light" aria-label="Ver {{ $group->name }}">Ver →</a></td>
                            </tr>
                        @endforelse
                    @empty
                        <tr><td colspan="7" class="px-5 py-10 text-center">
                            <p class="font-display text-lg font-semibold text-adara-ink">No hay productos para mostrar</p>
                            <p class="mt-2 text-sm text-adara-readable-muted">Agregá el primero o cambiá los filtros de búsqueda.</p>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-adara-border px-5 py-3 text-[11px] text-adara-readable-muted"><span>Mostrando {{ $groups->count() }} de {{ $groups->total() }} productos · {{ $groups->getCollection()->sum(fn ($group) => $group->variants->count()) }} presentaciones en esta página</span><span>Abrí una presentación para ver el producto</span></div>
        @if ($groups->hasPages())<div class="border-t border-adara-border px-5 py-4">{{ $groups->links('components.adara.pagination', data: ['scrollTo' => false]) }}</div>@endif
    </section>
</div>
