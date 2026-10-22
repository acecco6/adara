@php
    $money = fn (int $cents): string => '$ '.number_format(intdiv($cents, 100), 0, ',', '.').','.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    $unitCost = fn (int $tenThousandths): string => '$ '.number_format(intdiv($tenThousandths, 10000), 0, ',', '.').','.str_pad((string) ($tenThousandths % 10000), 4, '0', STR_PAD_LEFT);
    $statuses = [
        'draft' => 'Borrador',
        'confirmed' => 'Confirmado',
        'partially_received' => 'Recibido parcialmente',
        'received' => 'Recibido',
        'closed_partial' => 'Cerrado parcialmente',
    ];
@endphp

<div class="flex flex-col gap-6">
    @if ($view === 'index')
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-adara-primary">Compras</p>
                <h1 class="mt-2 font-display text-[30px] font-semibold text-adara-ink">Pedidos a proveedor</h1>
                <p class="mt-1 text-[13px] text-adara-readable-muted">Organizá tus pedidos y sabé qué falta recibir.</p>
            </div>
            <x-adara.button wire:click="openCreate">Nuevo pedido</x-adara.button>
        </header>

        <section class="grid gap-3 md:grid-cols-3" aria-label="Resumen de pedidos">
            <x-adara.kpi-card label="Pedidos registrados" :value="number_format($orderCount, 0, ',', '.')" detail="Todos los estados" />
            <x-adara.kpi-card label="Con entregas pendientes" :value="number_format($pendingOrderCount, 0, ',', '.')" detail="Confirmados o parcialmente recibidos" tone="rose" />
            <x-adara.kpi-card label="Recepciones registradas" :value="number_format($receiptCount, 0, ',', '.')" detail="Entregas guardadas en el historial" tone="amber" />
        </section>

        <section class="overflow-hidden rounded-adara-card border border-adara-border bg-adara-surface shadow-adara-panel" aria-labelledby="orders-title">
            <div class="flex flex-wrap items-end justify-between gap-4 border-b border-adara-border p-4 sm:p-5">
                <div>
                    <h2 id="orders-title" class="text-base font-bold">Pedidos registrados</h2>
                    <p class="mt-1 text-xs text-adara-readable-muted">Abrí uno para revisar líneas, entregas y pendientes.</p>
                </div>
                <div class="grid w-full gap-3 sm:w-auto sm:grid-cols-[minmax(0,250px)_180px] sm:items-end"><x-adara.input id="purchase-search" name="search" type="search" label="Buscar pedido o proveedor" wire:model.live.debounce.300ms="search" /><x-adara.select id="purchase-status-filter" name="status" label="Estado" wire:model.live="status"><option value="all">Todos los estados</option><option value="draft">Borrador</option><option value="confirmed">Confirmado</option><option value="partially_received">Recibido parcialmente</option><option value="received">Recibido</option><option value="closed_partial">Cerrado parcialmente</option></x-adara.select></div>
            </div>
            <div wire:loading.delay wire:target="search,status" role="status" class="px-5 py-2 text-sm text-adara-readable-muted">Actualizando pedidos…</div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-left text-xs" aria-label="Pedidos a proveedor">
                    <thead class="bg-adara-background/70 text-[10px] font-semibold uppercase tracking-[0.08em] text-adara-readable-muted"><tr><th scope="col" class="px-5 py-3">Pedido</th><th scope="col" class="px-4 py-3">Fecha</th><th scope="col" class="px-4 py-3">Proveedor</th><th scope="col" class="px-4 py-3">Unidades</th><th scope="col" class="px-4 py-3">Productos</th><th scope="col" class="px-4 py-3">Envío</th><th scope="col" class="px-4 py-3">Total pedido</th><th scope="col" class="px-4 py-3">Estado</th><th scope="col" class="px-4 py-3"><span class="sr-only">Detalle</span></th></tr></thead>
                    <tbody class="divide-y divide-adara-border">
                        @forelse ($orders as $purchase)
                            @php $orderedTotal = $purchase->lines->sum(fn ($line) => $line->quantity_ordered * $line->unit_paid_cents) + $purchase->general_shipping_cents; @endphp
                            <tr wire:key="purchase-order-{{ $purchase->id }}" class="hover:bg-adara-background/50">
                                <td class="px-5 py-3 font-bold text-adara-primary">{{ $purchase->number }}</td>
                                <td class="px-4 py-3 text-adara-readable-muted">{{ $purchase->created_at->timezone('America/Argentina/Buenos_Aires')->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 font-semibold">{{ $purchase->supplier_name_snapshot ?: $purchase->supplier->name }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ $purchase->lines->sum('quantity_ordered') }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ $purchase->lines->count() }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ $money($purchase->general_shipping_cents) }}</td>
                                <td class="px-4 py-3 font-bold tabular-nums">{{ $money($orderedTotal) }}</td>
                                <td class="px-4 py-3"><x-adara.badge :tone="in_array($purchase->status, ['received', 'closed_partial'], true) ? 'success' : ($purchase->status === 'draft' ? 'neutral' : 'warning')">{{ $statuses[$purchase->status] ?? $purchase->status }}</x-adara.badge></td>
                                <td class="px-4 py-3 text-right"><a href="{{ route('purchases.show', $purchase) }}" wire:navigate class="inline-flex min-h-9 items-center rounded-adara-control px-2 font-bold text-adara-primary hover:bg-adara-primary-light" aria-label="Ver pedido {{ $purchase->number }}">Ver →</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-5 py-10 text-center text-adara-readable-muted">{{ $orderCount === 0 ? 'Todavía no hay pedidos. Creá el primero para comenzar.' : 'No hay pedidos con estos filtros. Probá otra búsqueda o estado.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-adara-border px-5 py-3 text-[11px] text-adara-readable-muted"><span>Mostrando {{ $orders->count() }} de {{ $orders->total() }} pedidos</span><span>El envío general se registra una vez por pedido</span></div>
            @if ($orders->hasPages())<div class="border-t border-adara-border px-5 py-4">{{ $orders->links() }}</div>@endif
        </section>
    @elseif ($view === 'receipts')
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div><p class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-adara-primary">Operaciones / Abastecimiento</p><h1 class="mt-2 font-display text-[30px] font-semibold">Recepción de compras</h1><p class="mt-1 text-[13px] text-adara-readable-muted">Cuando llega el pedido, confirmás cantidades y costos reales para actualizar el stock.</p></div>
            <a href="{{ route('purchases.index') }}" wire:navigate class="inline-flex min-h-11 items-center rounded-adara-control bg-adara-primary px-4 text-sm font-semibold text-white hover:bg-adara-primary-hover">+ Registrar recepción</a>
        </header>
        <x-adara.alert tone="warning">Revisá las recepciones recientes abajo. Para registrar una nueva, abrí primero el pedido confirmado correspondiente.</x-adara.alert>
        <section class="grid gap-3 md:grid-cols-3" aria-label="Resumen de recepciones">
            <x-adara.kpi-card label="Entregas registradas" :value="number_format($receiptCount, 0, ',', '.')" detail="Recepciones confirmadas" />
            <x-adara.kpi-card label="Unidades recibidas" :value="number_format($receivedUnits, 0, ',', '.')" detail="Sumadas al stock mediante recepciones" tone="rose" />
            <x-adara.kpi-card label="Envío aplicado" :value="$money($receivedShippingCents)" detail="General asignado más envío adicional" tone="mint" />
        </section>
        <section class="overflow-hidden rounded-adara-card border border-adara-border bg-adara-surface shadow-adara-panel" aria-label="Recepciones registradas">
            <div class="flex items-center justify-between gap-3 border-b border-adara-border px-5 py-4"><h2 class="text-base font-bold">Detalle de mercadería recibida</h2><a href="{{ route('purchases.index') }}" wire:navigate class="text-xs font-bold text-adara-primary hover:underline">Ver pedidos →</a></div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[780px] text-left text-xs">
                    <thead class="bg-adara-background/70 text-[10px] font-semibold uppercase tracking-[0.08em] text-adara-readable-muted"><tr><th scope="col" class="px-5 py-3">Pedido</th><th scope="col" class="px-4 py-3">Producto</th><th scope="col" class="px-4 py-3">Cantidad</th><th scope="col" class="px-4 py-3">Compra / u.</th><th scope="col" class="px-4 py-3">Envío / línea</th><th scope="col" class="px-4 py-3">Costo real / u.</th><th scope="col" class="px-4 py-3">Costo total</th></tr></thead>
                    <tbody class="divide-y divide-adara-border">
                        @forelse ($receipts as $receipt)
                            <tr wire:key="purchase-receipt-line-{{ $receipt->id }}" class="hover:bg-adara-background/50">
                                <td class="px-5 py-3"><a href="{{ route('purchases.show', $receipt->purchase_order_id) }}" wire:navigate class="font-bold text-adara-primary hover:underline">{{ $receipt->number }}</a><span class="mt-0.5 block text-[10px] text-adara-readable-muted">{{ $receipt->supplier_name_snapshot }}</span><span class="mt-0.5 block text-[10px] text-adara-readable-muted">{{ \Illuminate\Support\Carbon::parse($receipt->received_at)->timezone('America/Argentina/Buenos_Aires')->format('d/m/Y') }}</span></td>
                                <td class="px-4 py-3 font-semibold">{{ $receipt->product_name_snapshot }} · {{ $receipt->presentation_snapshot }}<span class="mt-0.5 block text-[10px] font-normal text-adara-readable-muted">{{ $receipt->sku_snapshot }}</span></td>
                                <td class="px-4 py-3 tabular-nums">{{ $receipt->quantity_received }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ $money($receipt->unit_paid_cents) }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ $money($receipt->general_shipping_applied_cents + $receipt->additional_shipping_applied_cents) }}</td>
                                <td class="px-4 py-3 font-semibold tabular-nums">{{ $unitCost((int) $receipt->unit_cost_10000) }}</td>
                                <td class="px-4 py-3 font-bold tabular-nums">{{ $money($receipt->merchandise_paid_cents + $receipt->general_shipping_applied_cents + $receipt->additional_shipping_applied_cents) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-10 text-center text-adara-readable-muted">Todavía no hay recepciones. Abrí un pedido confirmado para registrar la primera.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-adara-border px-5 py-3 text-[11px] text-adara-readable-muted">Mostrando {{ $receipts->count() }} de {{ $receipts->total() }} renglones recibidos</div>
            @if ($receipts->hasPages())<div class="border-t border-adara-border px-5 py-4">{{ $receipts->links() }}</div>@endif
        </section>
    @else
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <a href="{{ route('purchases.index') }}" wire:navigate class="text-sm font-semibold text-adara-primary hover:underline">← Volver a pedidos</a>
                <h1 class="mt-3 font-display text-[30px] font-semibold">{{ $order->number }}</h1>
                <p class="mt-1 text-[13px] text-adara-readable-muted">{{ $order->supplier_name_snapshot ?: $order->supplier->name }} · {{ $order->created_at->timezone('America/Argentina/Buenos_Aires')->format('d/m/Y') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($order->status === 'draft')
                    <x-adara.button variant="outline" wire:click="openEdit">Editar borrador</x-adara.button>
                    <x-adara.button x-on:click="$dispatch('adara:open', { id: 'purchase-confirm' })">Confirmar pedido</x-adara.button>
                @elseif (in_array($order->status, ['confirmed', 'partially_received'], true))
                    <x-adara.button wire:click="openReceipt">Registrar recepción</x-adara.button>
                    @if ($order->status === 'partially_received')
                        <x-adara.button variant="outline" x-on:click="$dispatch('adara:open', { id: 'purchase-close-form' })">Cancelar pendientes</x-adara.button>
                    @endif
                @endif
            </div>
        </header>
        @error('form')<p role="alert" class="text-sm text-adara-danger">{{ $message }}</p>@enderror
        @error('confirm')<p role="alert" class="text-sm text-adara-danger">{{ $message }}</p>@enderror
        @error('receipt')<p role="alert" class="text-sm text-adara-danger">{{ $message }}</p>@enderror
        @error('closure')<p role="alert" class="text-sm text-adara-danger">{{ $message }}</p>@enderror
        <div wire:loading.delay wire:target="confirm,saveReceipt,closePending" role="status" class="text-sm text-adara-readable-muted">Guardando operación…</div>

        @php
            $merchandiseCents = $order->lines->sum(fn ($line) => $line->quantity_ordered * $line->unit_paid_cents);
            $pendingUnits = $order->lines->sum('pending_quantity');
        @endphp
        <section class="grid gap-3 md:grid-cols-3" aria-label="Resumen del pedido">
            <x-adara.kpi-card label="Compra sin envío" :value="$money($merchandiseCents)" detail="Mercadería del pedido original" />
            <x-adara.kpi-card label="Envío general" :value="$money($order->general_shipping_cents)" :detail="$order->shipping_method === 'quantity' ? 'Reparto por cantidad' : 'Reparto por costo pagado'" tone="amber" />
            <x-adara.kpi-card label="Total pedido" :value="$money($merchandiseCents + $order->general_shipping_cents)" :detail="$pendingUnits.' unidades pendientes · '.($statuses[$order->status] ?? $order->status)" tone="rose" />
        </section>

        <section class="overflow-hidden rounded-adara-card border border-adara-border bg-adara-surface shadow-adara-panel" aria-labelledby="purchase-lines-title">
            <div class="border-b border-adara-border p-5 sm:p-6">
                <div class="flex items-center justify-between gap-3"><h2 id="purchase-lines-title" class="text-base font-bold">Productos del pedido</h2><x-adara.badge :tone="in_array($order->status, ['received', 'closed_partial'], true) ? 'success' : 'warning'">{{ $statuses[$order->status] ?? $order->status }}</x-adara.badge></div>
                <p class="mt-1 text-sm text-adara-readable-muted">Envío general: {{ $money($order->general_shipping_cents) }} · Reparto por {{ $order->shipping_method === 'quantity' ? 'cantidad' : 'costo de mercadería' }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead class="bg-adara-background text-[11px] font-semibold uppercase tracking-wide text-adara-readable-muted"><tr><th scope="col" class="px-5 py-3">Producto</th><th scope="col" class="px-5 py-3 text-right">Pedido original</th><th scope="col" class="px-5 py-3 text-right">Excedente autorizado</th><th scope="col" class="px-5 py-3 text-right">Recibido</th><th scope="col" class="px-5 py-3 text-right">Cancelado</th><th scope="col" class="px-5 py-3 text-right">Pendiente</th><th scope="col" class="px-5 py-3 text-right">Costo pedido</th></tr></thead>
                    <tbody class="divide-y divide-adara-border">
                        @foreach ($order->lines as $line)
                            <tr wire:key="purchase-detail-line-{{ $line->id }}">
                                <td class="px-5 py-4"><span class="block font-semibold">{{ $line->product_name_snapshot ?: $line->variant->group->name }} · {{ $line->presentation_snapshot ?: $line->variant->name }}</span><span class="text-xs text-adara-readable-muted">{{ $line->sku_snapshot ?: $line->variant->sku }}</span></td>
                                <td class="px-5 py-4 text-right tabular-nums">{{ $line->quantity_ordered }}</td>
                                <td class="px-5 py-4 text-right tabular-nums">{{ $line->extended_quantity }}</td>
                                <td class="px-5 py-4 text-right tabular-nums">{{ $line->received_quantity }}</td>
                                <td class="px-5 py-4 text-right tabular-nums">{{ $line->canceled_quantity }}</td>
                                <td class="px-5 py-4 text-right font-semibold tabular-nums">{{ $line->pending_quantity }}</td>
                                <td class="px-5 py-4 text-right tabular-nums">{{ $money($line->unit_paid_cents) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-adara-card border border-adara-border bg-adara-surface p-5 shadow-adara-panel sm:p-6" aria-labelledby="purchase-receipts-title">
            <h2 id="purchase-receipts-title" class="font-display text-xl font-semibold">Historial de entregas</h2>
            @forelse ($receiptHistory as $receipt)
                <div wire:key="purchase-history-{{ $receipt->id }}" class="mt-4 border-t border-adara-border pt-4 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div><span class="font-semibold">Recepción REC-{{ str_pad($receipt->id, 6, '0', STR_PAD_LEFT) }}</span><span class="ml-2 text-adara-readable-muted">{{ \Illuminate\Support\Carbon::parse($receipt->received_at)->timezone('America/Argentina/Buenos_Aires')->format('d/m/Y H:i') }}</span></div>
                        <span>Envío general {{ $money($receipt->general_shipping_applied_cents) }} · adicional {{ $money($receipt->additional_shipping_cents) }}</span>
                    </div>
                    @if ($receipt->cost_change_accepted)<p class="mt-1 text-xs text-adara-readable-muted">Cambio de costo confirmado.</p>@endif
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full min-w-[600px] text-left text-xs" aria-label="Detalle de recepción {{ $receipt->id }}">
                            <thead class="text-adara-readable-muted"><tr><th scope="col" class="py-2">Presentación</th><th scope="col" class="py-2 text-right">Recibidas</th><th scope="col" class="py-2 text-right">Costo real/u</th><th scope="col" class="py-2 text-right">Envío asignado</th><th scope="col" class="py-2 text-right">Costo vigente/u</th><th scope="col" class="py-2 text-right">Precio calculado</th></tr></thead>
                            <tbody>
                                @foreach ($receiptLineHistory->get($receipt->id, collect()) as $receivedLine)
                                    <tr wire:key="purchase-history-line-{{ $receivedLine->id }}" class="border-t border-adara-border">
                                        <td class="py-2">{{ $receivedLine->presentation_snapshot }} · {{ $receivedLine->sku_snapshot }}</td>
                                        <td class="py-2 text-right tabular-nums">{{ $receivedLine->quantity_received }}</td>
                                        <td class="py-2 text-right tabular-nums">{{ $money($receivedLine->unit_paid_cents) }}</td>
                                        <td class="py-2 text-right tabular-nums">{{ $money($receivedLine->general_shipping_applied_cents + $receivedLine->additional_shipping_applied_cents) }}</td>
                                        <td class="py-2 text-right tabular-nums">$ {{ str_replace('.', ',', \App\Services\Pricing\DecimalNumber::formatUnsigned($receivedLine->unit_cost_10000, 4)) }}</td>
                                        <td class="py-2 text-right tabular-nums">{{ $money($receivedLine->price_cents) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <p class="mt-3 text-sm text-adara-readable-muted">Todavía no hay entregas registradas. Confirmar el pedido no suma stock.</p>
            @endforelse
            @if ($closure)
                <div class="mt-4 rounded-adara-control bg-adara-background p-4 text-sm">
                    <p class="font-semibold">Pendientes cancelados</p>
                    <p class="mt-1">{{ $closure->reason }}</p>
                    <p class="mt-1 text-adara-readable-muted">Envío general sin asignar: {{ $money($closure->unassigned_general_shipping_cents) }}. Los costos anteriores no cambiaron.</p>
                </div>
            @endif
        </section>
    @endif

    @if ($view !== 'receipts')
        <x-adara.modal id="purchase-order-form" :title="$editingOrderId ? 'Editar borrador' : 'Nuevo pedido'" description="Guardá el pedido antes de confirmar. El stock cambia recién al recibir." wide wire:ignore.self>
            <form id="purchase-order-fields" wire:submit="saveDraft" class="grid gap-4">
                <x-adara.select id="purchase-supplier" name="supplierId" label="Proveedor" wire:model="supplierId" required>
                    <option value="">Elegir proveedor</option>
                    @foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach
                </x-adara.select>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-adara.select id="purchase-shipping-method" name="shippingMethod" label="Repartir envío general por" wire:model="shippingMethod" required>
                        <option value="quantity">Cantidad de unidades</option><option value="merchandise_cost">Costo de mercadería</option>
                    </x-adara.select>
                    <x-adara.input id="purchase-general-shipping" name="generalShipping" label="Envío general (ARS)" inputmode="decimal" wire:model="generalShipping" required />
                </div>
                <div class="flex items-center justify-between gap-3"><h3 class="font-semibold">Presentaciones</h3><x-adara.button variant="ghost" wire:click="addOrderLine">Agregar presentación</x-adara.button></div>
                @foreach ($orderLines as $position => $line)
                    <div wire:key="purchase-form-line-{{ $line['_key'] }}" class="grid gap-3 rounded-adara-control border border-adara-border p-3 sm:grid-cols-[minmax(0,1fr)_92px_120px_auto] sm:items-end">
                        <x-adara.select id="purchase-variant-{{ $position }}" name="orderLines.{{ $position }}.variant_id" label="Presentación" wire:model="orderLines.{{ $position }}.variant_id" required>
                            <option value="">Elegir presentación</option>
                            @foreach ($variants as $variant)<option value="{{ $variant->id }}">{{ $variant->group->name }} · {{ $variant->name }} · {{ $variant->sku }}</option>@endforeach
                        </x-adara.select>
                        <x-adara.input id="purchase-qty-{{ $position }}" name="orderLines.{{ $position }}.quantity" label="Cantidad" inputmode="numeric" wire:model="orderLines.{{ $position }}.quantity" required />
                        <x-adara.input id="purchase-cost-{{ $position }}" name="orderLines.{{ $position }}.unit_paid" label="Costo ARS/u" inputmode="decimal" wire:model="orderLines.{{ $position }}.unit_paid" required />
                        <x-adara.button variant="ghost" wire:click="removeOrderLine({{ $position }})" aria-label="Quitar presentación {{ $position + 1 }}">Quitar</x-adara.button>
                    </div>
                @endforeach
                <x-adara.textarea id="purchase-notes" name="notes" label="Nota interna" wire:model="notes" />
                @error('form')<p role="alert" class="text-sm text-adara-danger">{{ $message }}</p>@enderror
                @error('lines')<p role="alert" class="text-sm text-adara-danger">{{ $message }}</p>@enderror
            </form>
            <x-slot:footer>
                <x-adara.button variant="ghost" x-on:click="close()">Cancelar</x-adara.button>
                <x-adara.button type="submit" form="purchase-order-fields" wire:loading.attr="disabled" wire:target="saveDraft">Guardar borrador</x-adara.button>
            </x-slot:footer>
        </x-adara.modal>
    @endif

    @if ($view === 'detail')
        <x-adara.modal id="purchase-confirm" title="Confirmar pedido" description="Los datos del pedido quedarán fijos. El stock se actualizará cuando registres una recepción." wire:ignore.self>
            <p class="text-sm">¿Confirmar {{ $order->number }}?</p>
            @error('confirm')<p role="alert" class="mt-3 text-sm text-adara-danger">{{ $message }}</p>@enderror
            <x-slot:footer><x-adara.button variant="ghost" x-on:click="close()">Volver</x-adara.button><x-adara.button wire:click="confirm" wire:loading.attr="disabled" wire:target="confirm">Confirmar pedido</x-adara.button></x-slot:footer>
        </x-adara.modal>

        <x-adara.modal id="purchase-receipt-form" title="Registrar recepción" description="Ingresá 0 en una presentación que no llegó. Las cantidades y costos reales actualizan el stock y el precio al guardar." wide wire:ignore.self>
            <form id="purchase-receipt-fields" wire:submit="saveReceipt" class="grid gap-4">
                @foreach ($receiptLines as $position => $line)
                    @php $orderedLine = $order->lines->firstWhere('id', $line['order_line_id']); @endphp
                    <div wire:key="purchase-receive-line-{{ $line['order_line_id'] }}" class="grid gap-3 rounded-adara-control border border-adara-border p-3 sm:grid-cols-[minmax(0,1fr)_100px_130px] sm:items-end">
                        <div><p class="text-sm font-semibold">{{ $orderedLine->product_name_snapshot }} · {{ $orderedLine->presentation_snapshot }}</p><p class="text-xs text-adara-readable-muted">Pendiente: {{ $orderedLine->pending_quantity }} · Pedido: {{ $money($orderedLine->unit_paid_cents) }}/u</p></div>
                        <x-adara.input id="receive-qty-{{ $position }}" name="receiptLines.{{ $position }}.quantity" label="Recibidas" inputmode="numeric" wire:model="receiptLines.{{ $position }}.quantity" />
                        <x-adara.input id="receive-cost-{{ $position }}" name="receiptLines.{{ $position }}.unit_paid" label="Costo real ARS/u" inputmode="decimal" wire:model="receiptLines.{{ $position }}.unit_paid" />
                    </div>
                @endforeach
                <x-adara.input id="receive-additional-shipping" name="additionalShipping" label="Envío adicional de esta entrega (ARS)" inputmode="decimal" wire:model="additionalShipping" />
                <label class="flex items-start gap-3 text-sm"><input type="checkbox" wire:model="acceptCostChange" class="mt-1 accent-adara-primary"><span>Confirmo los costos reales que difieren del pedido.</span></label>
                <label class="flex items-start gap-3 text-sm"><input type="checkbox" wire:model="acceptOverdelivery" class="mt-1 accent-adara-primary"><span>Confirmo cantidades que exceden lo pendiente; el pedido original quedará conservado.</span></label>
                @error('receipt')<p role="alert" class="text-sm text-adara-danger">{{ $message }}</p>@enderror
            </form>
            <x-slot:footer><x-adara.button variant="ghost" x-on:click="close()">Cancelar</x-adara.button><x-adara.button type="submit" form="purchase-receipt-fields" wire:loading.attr="disabled" wire:target="saveReceipt">Confirmar recepción</x-adara.button></x-slot:footer>
        </x-adara.modal>

        <x-adara.modal id="purchase-close-form" title="Cancelar pendientes" description="Las unidades ya recibidas permanecen. El envío general sobrante se registrará aparte y no cambiará costos anteriores." wire:ignore.self>
            <form id="purchase-close-fields" wire:submit="closePending">
                <x-adara.textarea id="purchase-close-reason" name="closureReason" label="Motivo" wire:model="closureReason" required />
                @error('closure')<p role="alert" class="mt-3 text-sm text-adara-danger">{{ $message }}</p>@enderror
            </form>
            <x-slot:footer><x-adara.button variant="ghost" x-on:click="close()">Volver</x-adara.button><x-adara.button type="submit" form="purchase-close-fields" wire:loading.attr="disabled" wire:target="closePending">Cancelar cantidades pendientes</x-adara.button></x-slot:footer>
        </x-adara.modal>
    @endif
</div>
