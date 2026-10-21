<div class="flex flex-col gap-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-adara-primary">Producto</p>
            <h1 class="mt-2 font-display text-[30px] font-semibold text-adara-ink">{{ $group->name }}</h1>
            <p class="mt-2 text-sm text-adara-readable-muted">Cada presentación tiene código, stock, costo y precio independientes.</p>
        </div>
        <a href="{{ route('products.index') }}" wire:navigate class="inline-flex min-h-11 items-center rounded-adara-control border border-adara-border bg-adara-surface px-4 text-sm font-semibold hover:bg-adara-primary-light">Volver a productos</a>
    </header>

    @error('catalog') <x-adara.alert tone="danger">{{ $message }}</x-adara.alert> @enderror

    @if ($pendingAction)
        <section class="rounded-adara-card border border-adara-warning bg-adara-warning-bg p-5" role="region" aria-labelledby="confirm-title" aria-describedby="confirm-description" tabindex="-1" x-init="$el.focus()">
            <h2 id="confirm-title" class="font-display text-lg font-semibold">Confirmar cambio</h2>
            <p id="confirm-description" class="mt-2 text-sm leading-6">
                @if ($pendingAction === 'archive')
                    Se archivará la presentación. El historial se conservará y podrás reactivarla.
                @elseif ($pendingAction === 'delete_variant')
                    La presentación se eliminará definitivamente solo si tiene stock cero y ningún historial.
                @else
                    Se eliminará el producto vacío. Esta acción no afecta presentaciones ni movimientos.
                @endif
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
                <x-adara.button :variant="$pendingAction === 'archive' ? 'outline' : 'danger'" wire:click="performConfirmed" wire:loading.attr="disabled" wire:target="performConfirmed">Confirmar</x-adara.button>
                <x-adara.button variant="ghost" wire:click="cancelConfirmation">Cancelar</x-adara.button>
                <span wire:loading wire:target="performConfirmed" role="status" class="self-center text-sm">Guardando…</span>
            </div>
        </section>
    @endif

    <div class="grid gap-5 xl:grid-cols-2">
        <section class="rounded-adara-card border border-adara-border bg-adara-surface p-5 shadow-adara-panel sm:p-6" aria-labelledby="group-title">
            <h2 id="group-title" class="font-display text-xl font-semibold">Datos del producto</h2>
            <p class="mt-2 text-sm text-adara-readable-muted">Categoría: {{ $group->category?->name ?? 'Sin categoría' }}</p>
            <p class="mt-1 text-sm text-adara-readable-muted">Proveedores: {{ $group->suppliers->pluck('name')->join(', ') ?: 'Sin proveedores' }}</p>
            <form wire:submit="saveGroup" class="mt-5 flex flex-col gap-5">
                <x-adara.input id="group-name" name="name" label="Nombre" wire:model="name" required />
                <x-adara.textarea id="group-description" name="description" label="Descripción" wire:model="description" />
                <x-adara.select id="group-category" name="category_id" label="Categoría" wire:model="categoryId">
                    <option value="">Sin categoría</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}{{ $category->is_active ? '' : ' (archivada)' }}</option>
                    @endforeach
                </x-adara.select>
                <fieldset>
                    <legend class="text-sm font-semibold text-adara-ink">Proveedores</legend>
                    @if ($suppliers->isEmpty())
                        <p class="mt-2 text-sm text-adara-readable-muted">Todavía no hay proveedores activos. <a href="{{ route('suppliers.index') }}" wire:navigate class="font-semibold text-adara-primary underline">Agregar proveedor</a></p>
                    @else
                        <div class="mt-2 grid max-h-36 gap-2 overflow-y-auto rounded-adara-control border border-adara-border p-3">
                            @foreach ($suppliers as $supplier)
                                <label wire:key="detail-supplier-{{ $supplier->id }}" class="flex min-h-9 items-center gap-2 text-sm"><input type="checkbox" value="{{ $supplier->id }}" wire:model="supplierIds" class="accent-adara-primary">{{ $supplier->name }}{{ $supplier->is_active ? '' : ' (archivado)' }}</label>
                            @endforeach
                        </div>
                    @endif
                    @if ($errors->first('supplier_ids') || $errors->first('supplier_ids.*')) <p role="alert" class="mt-2 text-xs text-adara-danger">{{ $errors->first('supplier_ids') ?: $errors->first('supplier_ids.*') }}</p> @endif
                </fieldset>
                <div><x-adara.button type="submit" wire:loading.attr="disabled" wire:target="saveGroup">Guardar cambios</x-adara.button></div>
            </form>
        </section>
        <section class="rounded-adara-card border border-adara-border bg-adara-surface p-5 shadow-adara-panel sm:p-6" aria-labelledby="new-variant-title">
            <h2 id="new-variant-title" class="font-display text-xl font-semibold">Agregar presentación</h2>
            <p class="mt-2 text-sm text-adara-readable-muted">Comienza con stock cero. El costo y precio se definirán al cargar existencias o recibir compras.</p>
            <form wire:submit="addVariant" class="mt-5 flex flex-col gap-5">
                <x-adara.input id="new-presentation" name="presentation" label="Presentación" hint="Por ejemplo, 100 ml o unidad." wire:model="presentation" required />
                <x-adara.input id="new-sku" name="sku" label="Código SKU" hint="Opcional: se genera automáticamente." wire:model="sku" />
                <div><x-adara.button type="submit" wire:loading.attr="disabled" wire:target="addVariant">Agregar presentación</x-adara.button></div>
            </form>
        </section>
    </div>

    <section class="rounded-adara-card border border-adara-border bg-adara-surface p-5 shadow-adara-panel sm:p-6" aria-labelledby="variants-title">
        <h2 id="variants-title" class="font-display text-xl font-semibold">Presentaciones</h2>
        <div class="mt-5 flex flex-col gap-4">
            @forelse ($group->variants as $variant)
                <article wire:key="variant-{{ $variant->id }}" class="rounded-adara-control border border-adara-border bg-adara-background p-4">
                    <div class="flex flex-wrap justify-between gap-3">
                        <div>
                            <h3 class="font-semibold">{{ $variant->name }}</h3>
                            <p class="mt-1 text-sm text-adara-readable-muted">Código {{ $variant->sku }} · Stock {{ $variant->balance?->quantity_on_hand ?? 0 }}</p>
                            <p class="mt-1 text-xs text-adara-readable-muted">Precio: {{ $variant->current_price_cents === null ? 'Pendiente de costo real' : '$ '.str_replace('.', ',', \App\Services\Pricing\Money::fromCents($variant->current_price_cents)->toDecimal()) }}</p>
                        </div>
                        <x-adara.badge :tone="$variant->is_active ? 'success' : 'neutral'">{{ $variant->is_active ? 'Activo' : 'Archivado' }}</x-adara.badge>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-adara.button variant="outline" wire:click="startEditing({{ $variant->id }})">Editar</x-adara.button>
                        @if ($variant->is_active)
                            <x-adara.button variant="outline" wire:click="confirm('archive', {{ $variant->id }})">Archivar</x-adara.button>
                        @else
                            <x-adara.button variant="secondary" wire:click="reactivate({{ $variant->id }})">Reactivar</x-adara.button>
                        @endif
                        <x-adara.button variant="ghost" wire:click="confirm('delete_variant', {{ $variant->id }})">Eliminar sin historial</x-adara.button>
                    </div>
                    @if ($editingVariantId === $variant->id)
                        <form wire:submit="saveVariant" class="mt-4 grid gap-4 border-t border-adara-border pt-4 sm:grid-cols-2">
                            <x-adara.input id="edit-presentation" name="editPresentation" label="Presentación" wire:model="editPresentation" required />
                            <x-adara.input id="edit-sku" name="editSku" label="Código SKU" wire:model="editSku" required />
                            <div class="flex flex-wrap gap-2 sm:col-span-2">
                                <x-adara.button type="submit" wire:loading.attr="disabled" wire:target="saveVariant">Guardar presentación</x-adara.button>
                                <x-adara.button variant="ghost" wire:click="$set('editingVariantId', null)">Cancelar</x-adara.button>
                            </div>
                        </form>
                    @endif
                </article>
            @empty
                <p class="text-sm text-adara-readable-muted">Este producto no tiene presentaciones.</p>
                <div><x-adara.button variant="danger" wire:click="confirm('delete_group')">Eliminar producto vacío</x-adara.button></div>
            @endforelse
        </div>
    </section>

    <section class="rounded-adara-card border border-adara-border bg-adara-surface p-5 shadow-adara-panel sm:p-6" aria-labelledby="movements-title">
        <h2 id="movements-title" class="font-display text-xl font-semibold">Últimos movimientos</h2>
        @if ($movements->isEmpty())
            <p class="mt-3 text-sm text-adara-readable-muted">Todavía no hay movimientos de stock.</p>
        @else
            <ul class="mt-4 divide-y divide-adara-border">
                @foreach ($movements as $movement)
                    <li wire:key="movement-{{ $movement->id }}" class="flex flex-wrap justify-between gap-2 py-3 text-sm">
                        <span>{{ $movement->presentation }} · {{ $movement->reason ?: 'Movimiento de stock' }} · {{ \Illuminate\Support\Carbon::parse($movement->occurred_at)->timezone('America/Argentina/Buenos_Aires')->format('d/m/Y H:i') }}</span>
                        <span class="font-semibold">{{ $movement->quantity_delta > 0 ? '+' : '' }}{{ $movement->quantity_delta }} · saldo {{ $movement->quantity_after }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
