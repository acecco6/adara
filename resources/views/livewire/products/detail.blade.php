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
            <p class="mt-2 text-sm text-adara-readable-muted">Comienza con stock cero. Cargá el costo real inicial por presentación; el precio se definirá cuando haya un recargo configurado.</p>
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
                            <p class="mt-1 text-sm text-adara-readable-muted">Código {{ $variant->sku }} · Stock {{ $variant->balance?->quantity_on_hand ?? 0 }} · Mínimo {{ $variant->minimum_stock }}</p>
                            <p class="mt-1 text-xs text-adara-readable-muted">Costo vigente: {{ $variant->current_unit_cost_10000 === null ? 'Pendiente' : '$ '.str_replace('.', ',', \App\Services\Pricing\UnitCost::fromTenThousandths($variant->current_unit_cost_10000)->toDecimal()) }}</p>
                            <p class="mt-1 text-xs text-adara-readable-muted">Precio: {{ $variant->current_price_cents === null ? ($variant->current_unit_cost_10000 === null ? 'Pendiente de costo real' : 'Pendiente de definir recargo') : '$ '.str_replace('.', ',', \App\Services\Pricing\Money::fromCents($variant->current_price_cents)->toDecimal()) }}</p>
                        </div>
                        <div class="flex flex-wrap items-start gap-2">
                            @if ($variant->is_active && ($variant->balance?->quantity_on_hand ?? 0) <= $variant->minimum_stock)
                                <x-adara.badge tone="warning">En mínimo o debajo</x-adara.badge>
                            @endif
                            <x-adara.badge :tone="$variant->is_active ? 'success' : 'neutral'">{{ $variant->is_active ? 'Activo' : 'Archivado' }}</x-adara.badge>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-adara.button variant="outline" wire:click="startEditing({{ $variant->id }})">Editar</x-adara.button>
                        @if ($variant->is_active)
                            @if (($variant->balance?->quantity_on_hand ?? 0) === 0 && $variant->current_unit_cost_10000 === null && $variant->current_price_cents === null && ! $movementVariantIds->has($variant->id))
                                <x-adara.button variant="secondary" wire:click="openInitialStock({{ $variant->id }})">Cargar stock inicial</x-adara.button>
                            @endif
                            <x-adara.button variant="outline" wire:click="openAdjustment({{ $variant->id }})">Ajustar stock</x-adara.button>
                            <x-adara.button variant="outline" wire:click="openMinimum({{ $variant->id }})">Configurar mínimo</x-adara.button>
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
                        <span>{{ $movement->presentation }} · {{ $movement->reason ?: 'Movimiento de stock' }} · {{ \Illuminate\Support\Carbon::parse($movement->occurred_at)->timezone('America/Argentina/Buenos_Aires')->format('d/m/Y H:i') }}@if ($movement->kind === 'initial_stock' && $movement->unit_cost_10000 !== null) · costo unitario $ {{ str_replace('.', ',', \App\Services\Pricing\UnitCost::fromTenThousandths((int) $movement->unit_cost_10000)->toDecimal()) }}@endif</span>
                        <span class="font-semibold">{{ $movement->quantity_delta > 0 ? '+' : '' }}{{ $movement->quantity_delta }} · saldo {{ $movement->quantity_after }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <x-adara.modal id="initial-stock-form" title="Cargar stock inicial" description="Registrá las existencias que ya tenés y su costo real por unidad. Esta carga se hace una sola vez por presentación." wire:ignore.self>
        <form id="initial-stock-fields" wire:submit="saveInitialStock" class="grid gap-4">
            <x-adara.input id="initial-quantity" name="initialQuantity" label="Cantidad disponible" type="number" min="1" step="1" inputmode="numeric" wire:model="initialQuantity" required />
            <x-adara.currency-input id="initial-cost" name="initialCost" label="Costo real unitario en ARS" hint="Sin separador de miles; hasta cuatro decimales, por ejemplo 1234,5678." wire:model="initialCost" required />
            <p class="text-sm text-adara-readable-muted">El precio de venta quedará pendiente hasta configurar el recargo.</p>
            @error('initialStock') <p role="alert" class="text-sm text-adara-danger">{{ $message }}</p> @enderror
        </form>
        <x-slot:footer>
            <x-adara.button variant="ghost" x-on:click="close()">Cancelar</x-adara.button>
            <x-adara.button type="submit" form="initial-stock-fields" wire:loading.attr="disabled" wire:target="saveInitialStock">Registrar stock</x-adara.button>
        </x-slot:footer>
    </x-adara.modal>

    <x-adara.modal id="stock-adjustment-form" title="Ajustar stock" description="Cada entrada o salida queda registrada con un motivo." wire:ignore.self>
        <form id="stock-adjustment-fields" wire:submit="saveAdjustment" class="grid gap-4">
            <x-adara.select id="adjustment-direction" name="adjustmentDirection" label="Tipo de ajuste" wire:model="adjustmentDirection" required>
                <option value="in">Entrada</option>
                <option value="out">Salida</option>
            </x-adara.select>
            <x-adara.input id="adjustment-quantity" name="adjustmentQuantity" label="Cantidad" type="number" min="1" step="1" inputmode="numeric" wire:model="adjustmentQuantity" required />
            <x-adara.textarea id="adjustment-reason" name="adjustmentReason" label="Motivo" wire:model="adjustmentReason" required />
            @error('stockAdjustment') <p role="alert" class="text-sm text-adara-danger">{{ $message }}</p> @enderror
        </form>
        <x-slot:footer>
            <x-adara.button variant="ghost" x-on:click="close()">Cancelar</x-adara.button>
            <x-adara.button type="submit" form="stock-adjustment-fields" wire:loading.attr="disabled" wire:target="saveAdjustment">Guardar ajuste</x-adara.button>
        </x-slot:footer>
    </x-adara.modal>

    <x-adara.modal id="stock-minimum-form" title="Configurar mínimo" description="Se mostrará un aviso cuando el saldo sea igual o menor a este número." wire:ignore.self>
        <form id="stock-minimum-fields" wire:submit="saveMinimum" class="grid gap-4">
            <x-adara.input id="minimum-quantity" name="minimumQuantity" label="Stock mínimo" type="number" min="0" step="1" inputmode="numeric" wire:model="minimumQuantity" required />
            @error('stockMinimum') <p role="alert" class="text-sm text-adara-danger">{{ $message }}</p> @enderror
        </form>
        <x-slot:footer>
            <x-adara.button variant="ghost" x-on:click="close()">Cancelar</x-adara.button>
            <x-adara.button type="submit" form="stock-minimum-fields" wire:loading.attr="disabled" wire:target="saveMinimum">Guardar mínimo</x-adara.button>
        </x-slot:footer>
    </x-adara.modal>
</div>
