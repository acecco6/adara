<div class="flex flex-col gap-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-adara-primary">Operaciones / Ventas</p>
            <h1 class="mt-2 font-display text-[30px] font-semibold leading-tight text-adara-ink">Historial de ventas</h1>
            <p class="mt-1 text-[13px] leading-5 text-adara-readable-muted">Cada venta registrada, con sus productos, cobros y margen bruto histórico.</p>
        </div>
        <a href="{{ route('sales.create') }}" wire:navigate class="inline-flex min-h-11 items-center justify-center rounded-adara-control bg-adara-primary px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-adara-primary-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-adara-primary">Nueva venta</a>
    </header>

    <section class="grid gap-3 md:grid-cols-3" aria-label="Resumen de ventas del período">
        <x-adara.kpi-card label="Ventas vigentes del período" :value="'$ '.number_format($salesCents / 100, 2, ',', '.')" :detail="$salesCount.' '.($salesCount === 1 ? 'venta vigente' : 'ventas vigentes')" tone="rose" />
        <x-adara.kpi-card label="Margen bruto de productos" :value="'$ '.number_format($grossMarginCents / 100, 2, ',', '.')" detail="Ventas menos costo histórico, antes de comisiones" tone="mint" />
        <x-adara.kpi-card label="Productos vendidos" :value="number_format($unitsSold, 0, ',', '.').' '.($unitsSold === 1 ? 'unidad' : 'unidades')" detail="Unidades de las ventas del período" />
    </section>

    <section class="overflow-hidden rounded-adara-card border border-adara-border bg-adara-surface shadow-adara-panel" aria-label="Listado de ventas">
        <div class="flex flex-wrap items-end justify-between gap-3 border-b border-adara-border p-4 sm:p-5">
            <div class="flex w-full flex-wrap items-end gap-3 sm:w-auto">
                <div class="min-w-[230px] flex-1 sm:w-[300px] sm:flex-none">
                    <x-adara.input id="sales-search" name="search" type="search" label="Buscar venta" wire:model.live.debounce.300ms="search" placeholder="Número, clienta o producto..." />
                </div>
                <div class="w-full sm:w-[180px]">
                    <x-adara.select id="sales-period" name="period" label="Período" wire:model.live="period">
                        <option value="last30">Últimos 30 días</option>
                        <option value="month">Este mes</option>
                        <option value="all">Todo el período</option>
                    </x-adara.select>
                </div>
            </div>
            <x-adara.button variant="outline" disabled title="La exportación estará disponible en reportes">Exportar</x-adara.button>
        </div>

        <div wire:loading.delay wire:target="search,period" role="status" class="px-5 py-2 text-xs text-adara-readable-muted">Actualizando ventas…</div>

        @if ($sales->isEmpty())
            <div class="p-5">
                <x-adara.empty-state :title="trim($search) === '' ? 'Aún no hay ventas en este período' : 'No encontramos ventas'" :description="trim($search) === '' ? 'Las ventas confirmadas aparecerán aquí cuando las registres.' : 'Probá otro número, nombre o período.'">
                    @if (trim($search) === '')
                        <a href="{{ route('sales.create') }}" wire:navigate class="inline-flex min-h-10 items-center rounded-adara-control bg-adara-primary px-4 text-sm font-semibold text-white">Registrar venta</a>
                    @endif
                </x-adara.empty-state>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1010px] text-left text-xs" aria-label="Ventas registradas">
                    <thead class="bg-adara-background/70 text-[10px] font-semibold uppercase tracking-[0.08em] text-adara-readable-muted">
                        <tr>
                            <th scope="col" class="px-4 py-3 sm:px-5">Número</th>
                            <th scope="col" class="px-4 py-3">Fecha</th>
                            <th scope="col" class="px-4 py-3">Cliente / estado</th>
                            <th scope="col" class="px-4 py-3">Productos</th>
                            <th scope="col" class="px-4 py-3">Cobrado</th>
                            <th scope="col" class="px-4 py-3">Total venta</th>
                            <th scope="col" class="px-4 py-3">Margen bruto</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Detalle</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-adara-border">
                        @foreach ($sales as $sale)
                            @php
                                $collected = (int) $sale->collected_cents;
                                $balance = $sale->total_cents - $collected;
                            @endphp
                            <tr wire:key="sale-{{ $sale->id }}" class="hover:bg-adara-background/50">
                                <td class="px-4 py-3 font-bold text-adara-primary sm:px-5">{{ $sale->number }}</td>
                                <td class="px-4 py-3 tabular-nums text-adara-readable-muted">{{ $sale->confirmed_at->setTimezone($displayTimezone)->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-adara-ink">{{ $sale->customer_name_snapshot ?: 'Consumidor final' }}@if ($sale->status === 'cancelled')<span class="mt-1 block text-[10px] font-bold text-adara-danger">Anulada</span>@endif</td>
                                <td class="px-4 py-3 text-adara-readable-muted">{{ (int) $sale->units_sold }} {{ (int) $sale->units_sold === 1 ? 'unidad' : 'unidades' }}</td>
                                <td class="px-4 py-3"><span class="block font-semibold tabular-nums text-adara-ink">$ {{ number_format($collected / 100, 2, ',', '.') }}</span><span class="mt-0.5 block text-[10px] text-adara-readable-muted">@forelse ($paymentMethodsBySaleId[$sale->id] ?? [] as $method){{ $paymentMethodLabels[$method] ?? $method }}@unless ($loop->last) + @endunless @empty Sin cobros @endforelse @if ($sale->status === 'confirmed' && $balance > 0) · Saldo $ {{ number_format($balance / 100, 2, ',', '.') }} @endif</span></td>
                                <td class="px-4 py-3 font-bold tabular-nums text-adara-ink">$ {{ number_format($sale->total_cents / 100, 2, ',', '.') }}</td>
                                <td class="px-4 py-3 font-bold tabular-nums {{ $sale->total_cents < $sale->cost_total_snapshot_cents ? 'text-adara-danger' : 'text-adara-success' }}">$ {{ number_format(($sale->total_cents - $sale->cost_total_snapshot_cents) / 100, 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">@if ($sale->status === 'confirmed' && $balance > 0 && $sale->customer_id)<button type="button" wire:click="openCollection({{ $sale->id }})" class="inline-flex min-h-9 items-center rounded-adara-control px-3 font-bold text-adara-primary hover:bg-adara-primary-light" aria-label="Cobrar saldo de {{ $sale->number }}">Cobrar</button>@endif<button type="button" wire:click="openDetail({{ $sale->id }})" class="inline-flex min-h-9 items-center rounded-adara-control px-3 font-bold text-adara-primary hover:bg-adara-primary-light" aria-label="Ver venta {{ $sale->number }}">Ver →</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-adara-border px-5 py-3 text-[11px] text-adara-readable-muted"><span>Mostrando {{ $sales->count() }} de {{ $sales->total() }} ventas</span><span>Importes históricos registrados en Adara</span></div>
        @if ($sales->hasPages())<div class="border-t border-adara-border px-5 py-4">{{ $sales->links('components.adara.pagination', data: ['scrollTo' => false]) }}</div>@endif
    </section>

    <x-adara.modal id="sale-detail" :title="$selectedSale ? 'Detalle de la venta '.$selectedSale->number : 'Detalle de la venta'" description="Comprobante interno de Adara Gestión · no fiscal" wide wire:ignore.self>
        @if ($selectedSale)
            @php
                $detailCollected = (int) $selectedPayments->sum('amount_cents');
                $detailBalance = $selectedSale->total_cents - $detailCollected;
            @endphp
            <div class="space-y-5 text-sm text-adara-ink">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><strong class="font-display text-lg">ADARA</strong><p class="mt-1 text-xs text-adara-readable-muted">{{ $selectedSale->confirmed_at->setTimezone($displayTimezone)->format('d/m/Y H:i') }} · {{ $selectedSale->customer_name_snapshot ?: 'Consumidor final' }}</p></div>
                    @if ($selectedSale->status === 'cancelled')<x-adara.badge tone="danger">Anulada</x-adara.badge>@else<x-adara.badge tone="success">Confirmada</x-adara.badge>@endif
                </div>

                <div class="overflow-x-auto rounded-adara-control border border-adara-border">
                    <table class="w-full min-w-[510px] text-left text-xs" aria-label="Artículos de la venta">
                        <thead class="bg-adara-background text-[10px] font-semibold uppercase tracking-wide text-adara-readable-muted"><tr><th scope="col" class="px-3 py-2">Producto y presentación</th><th scope="col" class="px-3 py-2">Cantidad</th><th scope="col" class="px-3 py-2">Precio / u.</th><th scope="col" class="px-3 py-2 text-right">Importe neto</th></tr></thead>
                        <tbody class="divide-y divide-adara-border">
                            @foreach ($selectedSale->items as $item)
                                <tr wire:key="sale-detail-item-{{ $item->id }}"><td class="px-3 py-3"><strong class="block text-adara-ink">{{ $item->product_name_snapshot }} · {{ $item->presentation_snapshot }}</strong><span class="text-[10px] text-adara-readable-muted">{{ $item->sku_snapshot }}</span></td><td class="px-3 py-3 tabular-nums">{{ $item->quantity }}</td><td class="px-3 py-3 tabular-nums">$ {{ number_format($item->unit_price_snapshot_cents / 100, 2, ',', '.') }}</td><td class="px-3 py-3 text-right font-semibold tabular-nums">$ {{ number_format($item->net_cents / 100, 2, ',', '.') }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <dl class="space-y-2 border-t border-adara-border pt-4 text-xs">
                    <div class="flex justify-between gap-3"><dt class="text-adara-readable-muted">Subtotal</dt><dd class="tabular-nums">$ {{ number_format($selectedSale->subtotal_cents / 100, 2, ',', '.') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-adara-readable-muted">Descuento general</dt><dd class="tabular-nums">− $ {{ number_format($selectedSale->discount_cents / 100, 2, ',', '.') }}</dd></div>
                    <div class="flex justify-between gap-3 border-t border-adara-border pt-3 text-base"><dt class="font-bold">Total de la venta</dt><dd class="font-extrabold tabular-nums">$ {{ number_format($selectedSale->total_cents / 100, 2, ',', '.') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-adara-readable-muted">Costo histórico de productos</dt><dd class="tabular-nums">$ {{ number_format($selectedSale->cost_total_snapshot_cents / 100, 2, ',', '.') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-adara-readable-muted">Margen bruto de productos</dt><dd class="font-bold tabular-nums {{ $selectedSale->total_cents < $selectedSale->cost_total_snapshot_cents ? 'text-adara-danger' : 'text-adara-success' }}">$ {{ number_format(($selectedSale->total_cents - $selectedSale->cost_total_snapshot_cents) / 100, 2, ',', '.') }}</dd></div>
                </dl>

                <div class="rounded-adara-control bg-adara-background p-4">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-adara-readable-muted">Cobros de esta venta</h3>
                    @forelse ($selectedPayments as $payment)
                        <div class="mt-2 flex justify-between gap-3 text-xs"><span>{{ $paymentMethodLabels[$payment->method] ?? $payment->method }} · {{ \Illuminate\Support\Carbon::parse($payment->paid_at, 'UTC')->setTimezone($displayTimezone)->format('d/m/Y') }}</span><strong class="tabular-nums">$ {{ number_format($payment->amount_cents / 100, 2, ',', '.') }}</strong></div>
                    @empty
                        <p class="mt-2 text-xs text-adara-readable-muted">Aún no hay cobros registrados.</p>
                    @endforelse
                    <div class="mt-3 flex justify-between gap-3 border-t border-adara-border pt-3 text-xs"><span>Cobrado: $ {{ number_format($detailCollected / 100, 2, ',', '.') }}</span><strong>Saldo pendiente: $ {{ number_format(($selectedSale->status === 'cancelled' ? 0 : $detailBalance) / 100, 2, ',', '.') }}</strong></div>
                </div>
                @if ($selectedCancellation)
                    <div class="rounded-adara-control border border-adara-border bg-adara-background p-4 text-xs">
                        <h3 class="font-bold text-adara-danger">Anulación registrada</h3>
                        <p class="mt-1 text-adara-readable-muted">{{ \Illuminate\Support\Carbon::parse($selectedCancellation->cancelled_at, 'UTC')->setTimezone($displayTimezone)->format('d/m/Y H:i') }}</p>
                        <p class="mt-2">{{ $selectedCancellation->reason }}</p>
                        <dl class="mt-3 space-y-1"><div class="flex justify-between gap-3"><dt>Deuda cancelada</dt><dd class="tabular-nums">$ {{ number_format($selectedCancellation->debt_relieved_cents / 100, 2, ',', '.') }}</dd></div><div class="flex justify-between gap-3"><dt>Reintegrado</dt><dd class="tabular-nums">$ {{ number_format($selectedRefunds->sum('amount_cents') / 100, 2, ',', '.') }}</dd></div><div class="flex justify-between gap-3 border-t border-adara-border pt-2 font-bold"><dt>Reintegro pendiente</dt><dd class="tabular-nums">$ {{ number_format($selectedRefundRemainingCents / 100, 2, ',', '.') }}</dd></div></dl>
                        @if ($selectedRefunds->isNotEmpty())<h4 class="mt-4 font-bold">Reintegros realizados</h4><ul class="mt-2 space-y-1">@foreach ($selectedRefunds as $refund)<li class="flex justify-between gap-3"><span>{{ \Illuminate\Support\Carbon::parse($refund->paid_at, 'UTC')->setTimezone($displayTimezone)->format('d/m/Y H:i') }} · {{ $paymentMethodLabels[$refund->method] ?? $refund->method }}</span><strong class="tabular-nums">$ {{ number_format($refund->amount_cents / 100, 2, ',', '.') }}</strong></li>@endforeach</ul>@endif
                        <p class="mt-3 text-adara-readable-muted">Los cobros originales siguen en el historial. Registrá cada devolución de dinero cuando realmente la entregues.</p>
                    </div>
                @endif
                <p class="text-[11px] leading-5 text-adara-readable-muted">El margen bruto usa el costo guardado al vender. Las comisiones de cobro no están descontadas de ese valor.</p>
            </div>
        @endif
        <x-slot:footer>@if ($selectedSale && $selectedSale->status === 'confirmed')<x-adara.button variant="outline" wire:click="openCancellation({{ $selectedSale->id }})">Anular venta</x-adara.button>@if ($selectedSale->customer_id && $selectedSale->total_cents > (int) $selectedPayments->sum('amount_cents'))<x-adara.button wire:click="openCollection({{ $selectedSale->id }})">Cobrar saldo</x-adara.button>@endif @elseif ($selectedSale && $selectedRefundRemainingCents > 0)<x-adara.button wire:click="openRefund({{ $selectedSale->id }})">Registrar reintegro</x-adara.button>@endif<x-adara.button variant="outline" x-on:click="close()">Cerrar</x-adara.button></x-slot:footer>
    </x-adara.modal>

    <x-adara.modal id="sale-refund" title="Registrar reintegro" description="Registrá el dinero que efectivamente devolviste a la clienta." wide wire:ignore.self>
        @if ($refundSuccess)
            <div role="status" class="rounded-adara-control bg-adara-success-bg p-4 text-sm text-adara-success">{{ $refundSuccess }}</div>
        @elseif ($refundSaleId)
            <div class="space-y-4 text-sm">
                <p class="text-adara-readable-muted">Podés reintegrar el importe completo o una parte. Elegí el medio que usaste, aunque sea distinto del cobro original.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-adara.input id="refund-amount" name="refundAmount" label="Importe devuelto en ARS" wire:model.live.debounce.250ms="refundAmount" inputmode="decimal" required />
                    <x-adara.select id="refund-method" name="refundMethod" label="Medio de reintegro" wire:model.live="refundMethod"><option value="cash">Efectivo</option><option value="transfer">Transferencia</option><option value="mercado_pago">Mercado Pago</option><option value="card">Tarjeta</option></x-adara.select>
                    <x-adara.input id="refund-paid-at" name="refundPaidAt" label="Fecha y hora de entrega" type="datetime-local" wire:model.live="refundPaidAt" required />
                </div>
                @error('refundAmount')<p role="alert" class="text-xs text-adara-danger">{{ $message }}</p>@enderror
                @if ($refundPreview)<div class="rounded-adara-control border border-adara-border bg-adara-background p-4" role="status"><h3 class="font-bold">Revisión del reintegro</h3><p class="mt-2">Se registra una salida real de $ {{ number_format($refundPreview['amount_cents'] / 100, 2, ',', '.') }} por {{ $paymentMethodLabels[$refundMethod] ?? $refundMethod }}.</p><p class="mt-1">Pendiente después de este reintegro: $ {{ number_format($refundPreview['remaining_cents'] / 100, 2, ',', '.') }}</p></div>@endif
            </div>
        @endif
        <x-slot:footer><x-adara.button variant="outline" x-on:click="close()">Cerrar</x-adara.button>@if ($refundSaleId && ! $refundSuccess)@if ($refundPreview)<x-adara.button wire:click="confirmRefund" wire:loading.attr="disabled" wire:target="confirmRefund">Confirmar dinero entregado</x-adara.button>@else<x-adara.button wire:click="reviewRefund" wire:loading.attr="disabled" wire:target="reviewRefund">Revisar reintegro</x-adara.button>@endif @endif</x-slot:footer>
    </x-adara.modal>

    <x-adara.modal id="sale-cancellation" title="Anular venta" description="La venta conservará su historial y las unidades aptas volverán al stock." wide wire:ignore.self>
        @if ($cancellationSuccess)
            <div role="status" class="rounded-adara-control bg-adara-background p-5 text-adara-success">{{ $cancellationSuccess }}</div>
        @elseif ($cancellationSale)
            <div class="space-y-4 text-sm">
                <p class="text-adara-readable-muted">{{ $cancellationSale->number }} · {{ $cancellationSale->customer_name_snapshot ?: 'Consumidor final' }}</p>
                <x-adara.input id="cancellation-reason" name="cancellationReason" label="Motivo de anulación" wire:model.live="cancellationReason" maxlength="500" required />
                <fieldset class="space-y-3"><legend class="font-bold">Estado de las unidades</legend><p class="text-xs text-adara-readable-muted">Indicá cuántas unidades de cada presentación pueden volver a venderse. Para las demás, explicá el motivo.</p>
                    @foreach ($cancellationSale->items as $item)
                        <div wire:key="cancellation-item-{{ $item->id }}" class="rounded-adara-control border border-adara-border p-3"><p class="font-semibold">{{ $item->product_name_snapshot }} · {{ $item->presentation_snapshot }} ({{ $item->quantity }} vendidas)</p><div class="mt-2 grid gap-3 sm:grid-cols-2"><x-adara.input id="cancellation-usable-{{ $item->id }}" name="cancellationItems.{{ $item->id }}.usable" label="Unidades aptas para stock" type="number" min="0" :max="$item->quantity" wire:model.live="cancellationItems.{{ $item->id }}.usable" /><x-adara.input id="cancellation-unusable-reason-{{ $item->id }}" name="cancellationItems.{{ $item->id }}.reason" label="Motivo de unidades no aptas" wire:model.live="cancellationItems.{{ $item->id }}.reason" placeholder="Solo si hay unidades no aptas" /></div></div>
                    @endforeach
                </fieldset>
                <label class="flex items-start gap-2"><input type="checkbox" wire:model.live="cancellationInspected" class="mt-1 accent-adara-primary"><span>Revisé el estado de todas las unidades y confirmé cuáles pueden volver al stock.</span></label>@error('cancellationInspected')<p class="text-xs text-adara-danger">{{ $message }}</p>@enderror
                @error('cancellationReason')<p class="text-xs text-adara-danger" role="alert">{{ $message }}</p>@enderror
                @if ($cancellationPreview)<div class="rounded-adara-control border border-adara-border bg-adara-background p-4"><h3 class="font-bold">Revisión de la anulación</h3><p class="mt-2">{{ $cancellationPreview['usable_units'] }} unidades volverán al stock.</p><p>Deuda cancelada: $ {{ number_format($cancellationPreview['debt_relieved_cents'] / 100, 2, ',', '.') }}</p><p>Reintegro pendiente de registrar: $ {{ number_format($cancellationPreview['refund_owed_cents'] / 100, 2, ',', '.') }}</p></div>@endif
            </div>
        @endif
        <x-slot:footer><x-adara.button variant="outline" x-on:click="close()">Cerrar</x-adara.button>@if ($cancellationSale && ! $cancellationSuccess)@if ($cancellationPreview)<x-adara.button wire:click="confirmCancellation" wire:loading.attr="disabled" wire:target="confirmCancellation">Confirmar anulación</x-adara.button>@else<x-adara.button wire:click="reviewCancellation" wire:loading.attr="disabled" wire:target="reviewCancellation">Revisar anulación</x-adara.button>@endif @endif</x-slot:footer>
    </x-adara.modal>

    <x-adara.modal id="sale-collection" title="Registrar cobro" description="Este cobro se aplica solo a la venta seleccionada." wide wire:ignore.self>
        @if ($collectionSuccess)
            <div role="status" class="rounded-adara-control bg-adara-background p-5 text-adara-success">{{ $collectionSuccess }}</div>
        @elseif ($collectionSaleId)
            <div class="space-y-4">
                <p class="text-sm text-adara-readable-muted">{{ $collectionSale?->number }} · {{ $collectionSale?->customer_name_snapshot }}. Las otras ventas de esta clienta conservan su propio saldo.</p>
                <p class="text-xs text-adara-readable-muted">Fecha del cobro: {{ now()->setTimezone($displayTimezone)->format('d/m/Y H:i') }}</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-adara.select id="collection-method" name="collectionMethod" label="Medio de pago" wire:model.live="collectionMethod"><option value="cash">Efectivo</option><option value="transfer">Transferencia</option><option value="mercado_pago">Mercado Pago</option><option value="card">Tarjeta</option></x-adara.select>
                    @if ($collectionMethod === 'cash')
                        <x-adara.input id="collection-tendered" name="collectionTendered" label="Efectivo recibido en ARS" wire:model.live.debounce.250ms="collectionTendered" inputmode="decimal" placeholder="Ej. 20000" required />
                    @else
                        <x-adara.input id="collection-amount" name="collectionAmount" label="Importe a aplicar en ARS" wire:model.live.debounce.250ms="collectionAmount" inputmode="decimal" required />
                        <x-adara.input id="collection-commission" name="collectionCommission" label="Comisión real en ARS" wire:model.live="collectionCommission" inputmode="decimal" hint="Ingresá el importe efectivo de la comisión. Si no corresponde, dejá 0." />
                    @endif
                </div>
                @if ($collectionMethod === 'cash')
                    <div class="space-y-2 rounded-adara-control border border-adara-border bg-adara-background px-4 py-3 text-sm" role="status" aria-live="polite">
                        <div class="flex justify-between gap-3"><span class="text-adara-readable-muted">Saldo de esta venta</span><strong class="tabular-nums">$ {{ number_format(($collectionBalanceCents ?? 0) / 100, 2, ',', '.') }}</strong></div>
                        <div class="flex justify-between gap-3"><span class="text-adara-readable-muted">Se aplica a esta venta</span><strong class="tabular-nums">{{ $cashAmounts === null ? 'Ingresá el efectivo recibido' : '$ '.number_format($cashAmounts['applied_cents'] / 100, 2, ',', '.') }}</strong></div>
                        <div class="flex justify-between gap-3 border-t border-adara-border pt-2"><span class="font-semibold">Vuelto a entregar</span><strong class="tabular-nums text-adara-primary">{{ $cashAmounts === null ? '—' : '$ '.number_format($cashAmounts['change_cents'] / 100, 2, ',', '.') }}</strong></div>
                    </div>
                @endif
                @if ($collectionPreview)
                    <div class="rounded-adara-control border border-adara-border bg-adara-background p-4 text-sm">
                        <h3 class="font-bold">Revisión del cobro</h3>
                        <p class="mt-1">Total aplicado: $ {{ number_format($collectionPreview['amount_cents'] / 100, 2, ',', '.') }} · Comisión: $ {{ number_format($collectionPreview['commission_cents'] / 100, 2, ',', '.') }}</p>
                        @if ($collectionPreview['cash_change_cents'] !== null)<p>Vuelto: $ {{ number_format($collectionPreview['cash_change_cents'] / 100, 2, ',', '.') }}</p>@endif
                        <ul class="mt-3 space-y-1 border-t border-adara-border pt-3">@foreach ($collectionPreview['allocations'] as $allocation)<li class="flex justify-between"><span>{{ $allocation['number'] }}</span><strong>$ {{ number_format($allocation['amount_cents'] / 100, 2, ',', '.') }}</strong></li>@endforeach</ul>
                    </div>
                @endif
            </div>
        @endif
        <x-slot:footer>
            <x-adara.button variant="outline" x-on:click="close()">Cerrar</x-adara.button>
            @if ($collectionSaleId && ! $collectionSuccess)
                @if ($collectionPreview)<x-adara.button wire:click="confirmCollection" wire:loading.attr="disabled" wire:target="confirmCollection">{{ $collectionPreview['cash_change_cents'] > 0 ? 'Confirmar cobro y vuelto' : 'Confirmar cobro' }}</x-adara.button>@else<x-adara.button wire:click="reviewCollection" wire:loading.attr="disabled" wire:target="reviewCollection">Revisar cobro</x-adara.button>@endif
            @endif
        </x-slot:footer>
    </x-adara.modal>
</div>
