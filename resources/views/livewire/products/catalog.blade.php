<div class="flex flex-col gap-6">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-adara-primary">Catálogo</p>
            <h1 class="mt-2 font-display text-[30px] font-semibold leading-tight text-adara-ink">Productos</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-adara-readable-muted">Organizá cada producto y sus presentaciones. El stock inicial y los precios se cargarán en sus próximos pasos.</p>
        </div>
        <x-adara.button x-on:click="$dispatch('adara:open', { id: 'nuevo-producto' })" aria-haspopup="dialog" aria-controls="nuevo-producto">Agregar producto</x-adara.button>
    </header>

    <x-adara.modal id="nuevo-producto" title="Nuevo producto" description="Empezá con una presentación. Después podrás agregar otras en su detalle." :wide="true" wire:ignore.self>
        <form id="nuevo-producto-form" wire:submit="save" class="grid gap-5 md:grid-cols-2">
            <x-adara.input id="product-name" name="name" label="Nombre del producto" wire:model="name" required />
            <x-adara.input id="product-presentation" name="presentation" label="Primera presentación" hint="Por ejemplo, 50 ml o unidad." wire:model="presentation" required />
            <x-adara.select id="product-category" name="category_id" label="Categoría" wire:model="categoryId">
                <option value="">Sin categoría</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </x-adara.select>
            <x-adara.input id="product-sku" name="sku" label="Código SKU" hint="Dejalo vacío para generar uno automáticamente." wire:model="sku" />
            <x-adara.textarea id="product-description" name="description" label="Descripción" wire:model="description" />
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
        <div class="grid gap-4 border-b border-adara-border p-5 sm:grid-cols-[minmax(0,1fr)_220px] sm:p-6">
            <x-adara.input id="product-search" name="search" type="search" label="Buscar producto o código" wire:model.live.debounce.300ms="search" placeholder="Nombre, presentación o SKU" />
            <x-adara.select id="product-status" name="status" label="Estado" wire:model.live="status">
                <option value="all">Todos</option>
                <option value="active">Con presentaciones activas</option>
                <option value="archived">Con presentaciones archivadas</option>
            </x-adara.select>
        </div>
        <div wire:loading.delay wire:target="search,status" role="status" class="px-5 py-2 text-sm text-adara-readable-muted">Actualizando listado…</div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[620px] text-left text-sm" aria-label="Productos">
                <thead class="bg-adara-background text-[11px] font-semibold uppercase tracking-wide text-adara-readable-muted">
                    <tr>
                        <th scope="col" class="px-5 py-3">Producto</th>
                        <th scope="col" class="px-5 py-3">Presentaciones</th>
                        <th scope="col" class="px-5 py-3">Categoría</th>
                        <th scope="col" class="px-5 py-3">Estado</th>
                        <th scope="col" class="px-5 py-3">Detalle</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-adara-border">
                    @forelse ($groups as $group)
                        <tr wire:key="product-{{ $group->id }}">
                            <td class="px-5 py-4 font-semibold text-adara-ink">{{ $group->name }}</td>
                            <td class="px-5 py-4 text-adara-readable-muted">{{ $group->variants_count }} · {{ $group->variants->pluck('name')->join(', ') }}</td>
                            <td class="px-5 py-4 text-adara-readable-muted">{{ $group->category?->name ?? 'Sin categoría' }}</td>
                            <td class="px-5 py-4">
                                <x-adara.badge :tone="$group->variants->contains('is_active', true) ? 'success' : 'neutral'">{{ $group->variants_count === 0 ? 'Sin presentaciones' : ($group->variants->contains('is_active', true) ? 'Activo' : 'Archivado') }}</x-adara.badge>
                            </td>
                            <td class="px-5 py-4 text-right"><a href="{{ route('products.show', $group) }}" wire:navigate class="inline-flex min-h-10 items-center rounded-adara-control px-3 font-semibold text-adara-primary hover:bg-adara-primary-light">Ver detalle</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center">
                            <p class="font-display text-lg font-semibold text-adara-ink">No hay productos para mostrar</p>
                            <p class="mt-2 text-sm text-adara-readable-muted">Agregá el primero o cambiá los filtros de búsqueda.</p>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($groups->hasPages())
            <div class="border-t border-adara-border px-5 py-4">{{ $groups->links() }}</div>
        @endif
    </section>
</div>
