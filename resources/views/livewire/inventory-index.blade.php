<div class="flex flex-col gap-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-adara-primary">Inventario</p>
            <h1 class="mt-2 font-display text-[30px] font-semibold text-adara-ink">Stock y movimientos</h1>
            <p class="mt-2 text-sm text-adara-readable-muted">Consultá el saldo de cada presentación y sus movimientos registrados.</p>
        </div>
        <a href="{{ route('products.index') }}" wire:navigate class="inline-flex min-h-11 items-center rounded-adara-control border border-adara-border bg-adara-surface px-4 text-sm font-semibold hover:bg-adara-primary-light">Ver productos</a>
    </header>

    <section class="overflow-hidden rounded-adara-card border border-adara-border bg-adara-surface shadow-adara-panel" aria-labelledby="inventory-list-title">
        <div class="border-b border-adara-border p-5 sm:p-6">
            <h2 id="inventory-list-title" class="font-display text-xl font-semibold">Existencias por presentación</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_190px_190px]">
                <x-adara.input id="inventory-search" name="search" type="search" label="Buscar producto o código" placeholder="Producto, presentación o SKU" wire:model.live.debounce.300ms="search" />
                <x-adara.select id="inventory-status" name="status" label="Estado" wire:model.live="status">
                    <option value="active">Activas</option><option value="archived">Archivadas</option><option value="all">Todas</option>
                </x-adara.select>
                <x-adara.select id="inventory-level" name="level" label="Disponibilidad" wire:model.live="level">
                    <option value="all">Todas</option><option value="at_minimum">En mínimo o debajo</option><option value="out">Sin stock</option><option value="available">Con stock</option>
                </x-adara.select>
            </div>
            <div wire:loading.delay wire:target="search,status,level" role="status" class="mt-3 text-sm text-adara-readable-muted">Actualizando existencias…</div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-sm" aria-label="Existencias por presentación">
                <thead class="bg-adara-background text-[11px] font-semibold uppercase tracking-wide text-adara-readable-muted"><tr><th scope="col" class="px-5 py-3">Producto y presentación</th><th scope="col" class="px-5 py-3">Código</th><th scope="col" class="px-5 py-3 text-right">Disponible</th><th scope="col" class="px-5 py-3 text-right">Mínimo</th><th scope="col" class="px-5 py-3">Estado</th><th scope="col" class="px-5 py-3 text-right">Acciones</th></tr></thead>
                <tbody class="divide-y divide-adara-border">
                    @forelse ($variants as $variant)
                        <tr wire:key="inventory-variant-{{ $variant->id }}">
                            <td class="px-5 py-4"><a href="{{ route('products.show', $variant->product_group_id) }}" wire:navigate class="block font-semibold text-adara-ink hover:text-adara-primary hover:underline">{{ $variant->group->name }}</a><span class="text-adara-readable-muted">{{ $variant->name }}</span></td>
                            <td class="px-5 py-4 text-adara-readable-muted">{{ $variant->sku }}</td>
                            <td class="px-5 py-4 text-right font-semibold tabular-nums">{{ $variant->available }}</td>
                            <td class="px-5 py-4 text-right tabular-nums">{{ $variant->minimum_stock }}</td>
                            <td class="px-5 py-4"><div class="flex flex-wrap gap-1">
                                @if ($variant->is_active && $variant->available <= $variant->minimum_stock)<x-adara.badge tone="warning">En mínimo o debajo</x-adara.badge>@endif
                                @unless ($variant->is_active)<x-adara.badge>Archivada</x-adara.badge>@endunless
                            </div></td>
                            <td class="px-5 py-4"><div class="flex justify-end gap-1">
                                <x-adara.button variant="ghost" wire:click="showHistory({{ $variant->id }})">Movimientos</x-adara.button>
                                @if ($variant->is_active)<x-adara.button variant="outline" wire:click="openAdjustment({{ $variant->id }})">Ajustar</x-adara.button>@endif
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center"><p class="font-display text-lg font-semibold">No hay presentaciones para mostrar</p><p class="mt-2 text-sm text-adara-readable-muted">Cambiá los filtros o agregá una presentación desde Productos.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($variants->hasPages())<div class="border-t border-adara-border px-5 py-4">{{ $variants->links() }}</div>@endif
    </section>

    <section id="inventory-history" tabindex="-1" x-on:adara:inventory-history.window="$el.focus(); $el.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' })" class="overflow-hidden rounded-adara-card border border-adara-border bg-adara-surface shadow-adara-panel" aria-labelledby="inventory-history-title">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-adara-border p-5 sm:p-6">
            <div><h2 id="inventory-history-title" class="font-display text-xl font-semibold">Historial de movimientos</h2><p class="mt-1 text-sm text-adara-readable-muted">{{ $historyVariant ? $historyVariant->group->name.' · '.$historyVariant->name : 'Movimientos recientes de todas las presentaciones' }}</p></div>
            @if ($historyVariant)<x-adara.button variant="ghost" wire:click="clearHistory">Ver todos</x-adara.button>@endif
        </div>
        <div wire:loading.delay wire:target="showHistory,clearHistory,saveAdjustment" role="status" class="px-5 py-2 text-sm text-adara-readable-muted">Actualizando historial…</div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[680px] text-left text-sm" aria-label="Historial de movimientos de stock">
                <thead class="bg-adara-background text-[11px] font-semibold uppercase tracking-wide text-adara-readable-muted"><tr><th scope="col" class="px-5 py-3">Fecha</th><th scope="col" class="px-5 py-3">Presentación</th><th scope="col" class="px-5 py-3">Motivo</th><th scope="col" class="px-5 py-3 text-right">Cambio</th><th scope="col" class="px-5 py-3 text-right">Saldo</th></tr></thead>
                <tbody class="divide-y divide-adara-border">
                    @forelse ($movements as $movement)
                        <tr wire:key="inventory-movement-{{ $movement->id }}">
                            <td class="whitespace-nowrap px-5 py-4 text-adara-readable-muted">{{ \Illuminate\Support\Carbon::parse($movement->occurred_at)->timezone('America/Argentina/Buenos_Aires')->format('d/m/Y H:i') }}</td>
                            <td class="px-5 py-4"><span class="block font-semibold">{{ $movement->product_name }} · {{ $movement->presentation }}</span><span class="text-xs text-adara-readable-muted">{{ $movement->sku }}</span></td>
                            <td class="px-5 py-4 text-adara-readable-muted">{{ $movement->reason ?: 'Movimiento de stock' }}@if ($movement->kind === 'initial_stock' && $movement->unit_cost_10000 !== null)<span class="block text-xs">Costo unitario: $ {{ str_replace('.', ',', \App\Services\Pricing\UnitCost::fromTenThousandths((int) $movement->unit_cost_10000)->toDecimal()) }}</span>@endif</td>
                            <td class="px-5 py-4 text-right font-semibold tabular-nums">{{ $movement->quantity_delta > 0 ? '+' : '' }}{{ $movement->quantity_delta }}</td>
                            <td class="px-5 py-4 text-right tabular-nums">{{ $movement->quantity_after }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-sm text-adara-readable-muted">Todavía no hay movimientos para mostrar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($movements->hasPages())<div class="border-t border-adara-border px-5 py-4">{{ $movements->links() }}</div>@endif
    </section>

    <x-adara.modal id="inventory-adjustment-form" title="Ajustar stock" description="Registrá la cantidad y el motivo. El saldo no puede quedar negativo." wire:ignore.self>
        <form id="inventory-adjustment-fields" wire:submit="saveAdjustment" class="grid gap-4">
            @if ($adjustmentVariant)<p class="text-sm font-semibold">{{ $adjustmentVariant->group->name }} · {{ $adjustmentVariant->name }} · {{ $adjustmentVariant->sku }}</p>@endif
            <x-adara.select id="inventory-adjustment-direction" name="adjustmentDirection" label="Tipo de ajuste" wire:model="adjustmentDirection" required><option value="in">Entrada</option><option value="out">Salida</option></x-adara.select>
            <x-adara.input id="inventory-adjustment-quantity" name="adjustmentQuantity" label="Cantidad" type="number" min="1" step="1" inputmode="numeric" wire:model="adjustmentQuantity" required />
            <x-adara.textarea id="inventory-adjustment-reason" name="adjustmentReason" label="Motivo" maxlength="1000" wire:model="adjustmentReason" required />
            @error('adjustment') <p role="alert" class="text-sm text-adara-danger">{{ $message }}</p> @enderror
        </form>
        <x-slot:footer>
            <x-adara.button variant="ghost" x-on:click="close()">Cancelar</x-adara.button>
            <x-adara.button type="submit" form="inventory-adjustment-fields" wire:loading.attr="disabled" wire:target="saveAdjustment">Guardar ajuste</x-adara.button>
        </x-slot:footer>
    </x-adara.modal>
</div>
