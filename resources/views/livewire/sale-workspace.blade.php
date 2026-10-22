<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="mb-2 text-[10px] font-extrabold uppercase tracking-[0.16em] text-adara-primary">Operaciones / Ventas</p>
            <h1 class="font-display text-[30px] font-semibold leading-tight tracking-tight text-adara-ink">Registrar nueva venta</h1>
            <p class="mt-1 text-[13px] leading-5 text-adara-readable-muted">Elegí productos, ajustá cantidades y confirmá. El stock se descontará al finalizar.</p>
        </div>
        @if ($confirmedSaleId === null)
            <x-adara.button variant="outline" class="border-adara-danger/25 text-adara-danger" x-on:click="$dispatch('adara:open', { id: 'sale-discard' })" aria-haspopup="dialog" aria-controls="sale-discard">
                <span aria-hidden="true">×</span> Cancelar venta
            </x-adara.button>
        @else
            <x-adara.button wire:click="startAnotherSale">Nueva venta</x-adara.button>
        @endif
    </header>

    @if ($message)
        <p role="alert" class="rounded-adara-control border border-adara-danger/25 bg-adara-danger-bg px-4 py-3 text-sm font-medium text-adara-danger">{{ $message }}</p>
    @endif

        <div class="grid items-start gap-[18px] lg:grid-cols-[minmax(0,1fr)_300px] min-[1366px]:grid-cols-[minmax(0,1fr)_350px]" @if ($confirmedSaleId !== null) inert aria-hidden="true" @endif>
            <section class="min-w-0" aria-labelledby="sale-products-title">
                <div class="mb-[18px] rounded-adara-card border border-adara-border bg-adara-surface p-[22px] shadow-adara-panel">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h2 id="sale-products-title" class="text-base font-bold text-adara-ink">Seleccionar productos</h2>
                            <p class="mt-1 text-xs text-adara-readable-muted">Solo aparecen artículos en stock</p>
                        </div>
                        <span class="rounded-full bg-adara-success-bg px-2.5 py-1 text-[11px] font-bold text-adara-success">Disponible</span>
                    </div>
                    <label for="sale-search" class="sr-only">Buscar por nombre o código</label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-adara-readable-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/></svg>
                        <input id="sale-search" type="search" wire:model.live.debounce.250ms="search" autocomplete="off" placeholder="Buscar un producto por nombre o código..." class="min-h-11 w-full rounded-adara-control border border-adara-border bg-adara-surface py-2.5 pl-10 pr-3 text-sm text-adara-ink placeholder:text-adara-readable-muted">
                    </div>
                    <p wire:loading.delay wire:target="search" role="status" class="mt-2 text-xs text-adara-readable-muted">Buscando productos...</p>
                </div>

                @if ($results->isEmpty())
                    <x-adara.empty-state :title="trim($search) === '' ? 'No hay productos disponibles' : 'No encontramos productos'" :description="trim($search) === '' ? 'Cuando haya presentaciones con stock y precio, aparecerán aquí.' : 'Probá buscar con otro nombre o código.'" />
                @else
                    <div class="grid gap-[13px] sm:grid-cols-2 min-[1366px]:grid-cols-3" aria-label="Productos disponibles">
                        @foreach ($results as $variant)
                            @php
                                $tones = ['bg-adara-primary-light text-adara-primary', 'bg-adara-success-bg text-adara-success', 'bg-adara-warning-bg text-adara-warning', 'bg-[#e9ecf5] text-adara-primary'];
                                $tone = $tones[$loop->index % count($tones)];
                            @endphp
                            <button type="button" wire:key="sale-result-{{ $variant->id }}" wire:click="addVariant({{ $variant->id }})" class="group min-w-0 rounded-adara-card border border-adara-border bg-adara-surface p-4 text-left shadow-adara-panel transition duration-150 hover:-translate-y-0.5 hover:border-adara-primary/35" aria-label="Agregar {{ $variant->product_name }} {{ $variant->name }}">
                                <span class="mb-[13px] flex h-[94px] items-center justify-center rounded-[13px] {{ $tone }}" aria-hidden="true">
                                    <svg class="h-11 w-11" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M15 14h18v25H15z" stroke-linejoin="round"/><path d="M20 8h8v6h-8zM19 23h10v7H19z" stroke-linejoin="round"/><path d="M15 18h18"/></svg>
                                </span>
                                <span class="flex items-start justify-between gap-2">
                                    <span class="min-w-0 truncate text-xs font-bold text-adara-ink">{{ $variant->product_name }}</span>
                                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ (int) $variant->quantity_on_hand <= 4 ? 'bg-adara-warning-bg text-adara-warning' : 'bg-adara-success-bg text-adara-success' }}">{{ (int) $variant->quantity_on_hand }} u.</span>
                                </span>
                                <span class="mt-1 block truncate text-[11px] text-adara-readable-muted">{{ $variant->category_name ?: $variant->name }} · {{ $variant->sku }}</span>
                                <span class="mt-[13px] flex items-center justify-between gap-2">
                                    <strong class="text-base text-adara-ink">$ {{ number_format((int) $variant->current_price_cents / 100, 2, ',', '.') }}</strong>
                                    <span class="rounded-lg bg-adara-primary-light px-2.5 py-1.5 text-xs font-bold text-adara-primary group-hover:bg-adara-primary group-hover:text-white">+ Agregar</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </section>

            <aside class="min-w-0 rounded-adara-card border border-adara-border bg-adara-surface p-[22px] shadow-adara-panel lg:sticky lg:top-24 lg:self-start" aria-labelledby="sale-summary-title">
                <div class="mb-4 flex items-center justify-between gap-2">
                    <h2 id="sale-summary-title" class="text-base font-bold text-adara-ink">Resumen de venta</h2>
                    @php $unitCount = array_sum(array_map(fn ($line) => (int) $line['quantity'], $cart)); @endphp
                    <span class="rounded-full bg-adara-primary-light px-2.5 py-1 text-[11px] font-bold text-adara-primary">{{ $unitCount }} {{ $unitCount === 1 ? 'unidad' : 'unidades' }}</span>
                </div>

                <x-adara.select id="sale-customer" label="Cliente" wire:model.change="customerId">
                    <option value="">Consumidor final</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                    @endforeach
                </x-adara.select>

                <div class="my-[19px] border-t border-adara-border"></div>
                @if ($cart === [])
                    <div class="flex min-h-[156px] flex-col items-center justify-center gap-2 text-center">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-adara-primary-light text-adara-primary" aria-hidden="true">＋</span>
                        <strong class="text-sm text-adara-ink">Tu venta está vacía</strong>
                        <p class="text-xs text-adara-readable-muted">Elegí un producto de la izquierda para comenzar.</p>
                    </div>
                @else
                    <ul class="divide-y divide-adara-border" aria-label="Productos del carrito">
                        @foreach ($cart as $position => $line)
                            @php $variant = $selected->get($line['variant_id']); $quotedItem = $quote['items'][$position] ?? null; @endphp
                            <li wire:key="sale-cart-{{ $line['variant_id'] }}" class="flex justify-between gap-3 py-3">
                                <div class="min-w-0">
                                    <strong class="block truncate text-xs text-adara-ink">{{ $variant?->product_name ?? 'Presentación no disponible' }} · {{ $variant?->name }}</strong>
                                    <p class="mt-1 text-[11px] text-adara-readable-muted">{{ $variant?->sku }} · {{ $variant?->current_price_cents === null ? 'Sin precio' : '$ '.number_format((int) $variant->current_price_cents / 100, 2, ',', '.') }} / u.</p>
                                    <div class="mt-2 flex items-center gap-1.5">
                                        <button type="button" wire:click="decreaseQuantity({{ $line['variant_id'] }})" class="flex h-7 w-7 items-center justify-center rounded-lg border border-adara-border bg-adara-surface font-bold text-adara-ink" aria-label="Restar una unidad de {{ $variant?->name }}">−</button>
                                        <label for="sale-quantity-{{ $line['variant_id'] }}" class="sr-only">Cantidad de {{ $variant?->name }}</label>
                                        <input id="sale-quantity-{{ $line['variant_id'] }}" type="number" min="1" step="1" wire:model.blur="cart.{{ $position }}.quantity" class="h-7 w-10 rounded-lg border border-adara-border bg-adara-surface text-center text-xs font-bold text-adara-ink">
                                        <button type="button" wire:click="addVariant({{ $line['variant_id'] }})" class="flex h-7 w-7 items-center justify-center rounded-lg border border-adara-border bg-adara-surface font-bold text-adara-ink" aria-label="Sumar una unidad de {{ $variant?->name }}">+</button>
                                    </div>
                                </div>
                                <strong class="shrink-0 text-xs text-adara-ink">{{ $quotedItem ? '$ '.number_format($quotedItem['gross'] / 100, 2, ',', '.') : '—' }}</strong>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($quoteError)<p role="alert" class="mt-2 text-xs font-semibold text-adara-danger">{{ $quoteError }}</p>@endif

                <div class="my-[19px] border-t border-adara-border"></div>
                <dl class="space-y-3 text-xs text-adara-readable-muted">
                    <div class="flex justify-between gap-2"><dt>Subtotal</dt><dd class="font-bold text-adara-ink">{{ $quote ? '$ '.number_format($quote['subtotal'] / 100, 2, ',', '.') : '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt>Descuentos</dt><dd>{{ $quote ? '$ '.number_format($quote['discount'] / 100, 2, ',', '.') : '$ 0,00' }}</dd></div>
                </dl>
                <details class="mt-3 text-xs text-adara-primary" @if ($discountType !== 'none') open @endif>
                    <summary class="cursor-pointer font-semibold">Aplicar descuento general</summary>
                    <div class="mt-3 grid gap-3">
                        <x-adara.select id="sale-discount-type" label="Tipo de descuento" wire:model.change="discountType">
                            <option value="none">Sin descuento</option><option value="fixed">Importe fijo</option><option value="percentage">Porcentaje</option>
                        </x-adara.select>
                        @if ($discountType !== 'none')<x-adara.input id="sale-discount-value" label="Valor del descuento" inputmode="decimal" wire:model.blur="discountValue" />@endif
                    </div>
                </details>
                <div class="my-[19px] border-t border-adara-border"></div>
                <div class="flex items-center justify-between gap-2"><strong class="text-sm text-adara-ink">Total a cobrar</strong><strong class="text-[27px] font-extrabold tracking-tight text-adara-ink">{{ $quote ? '$ '.number_format($quote['total'] / 100, 2, ',', '.') : '$ 0,00' }}</strong></div>

                <div class="mt-5 space-y-3" aria-labelledby="sale-payments-title">
                    <div class="flex items-center justify-between gap-2"><h3 id="sale-payments-title" class="text-xs font-bold text-adara-ink">Medio de pago</h3><button type="button" wire:click="addPayment" class="text-xs font-bold text-adara-primary hover:underline">+ Agregar cobro</button></div>
                    @if ($payments === [])<p class="text-xs text-adara-readable-muted">Agregá un cobro o elegí una clienta para dejar saldo pendiente.</p>@endif
                    @foreach ($payments as $position => $payment)
                        <div wire:key="sale-payment-{{ $position }}" class="space-y-3 rounded-adara-control border border-adara-border bg-adara-background/50 p-3">
                            <div class="flex items-center justify-between"><strong class="text-xs text-adara-ink">Cobro {{ $position + 1 }}</strong><button type="button" wire:click="removePayment({{ $position }})" class="text-xs font-semibold text-adara-danger">Quitar</button></div>
                            <x-adara.select id="sale-payment-method-{{ $position }}" label="Medio" wire:model.change="payments.{{ $position }}.method">
                                <option value="cash">Efectivo</option><option value="transfer">Transferencia</option><option value="mercado_pago">Mercado Pago</option><option value="card">Tarjeta</option>
                            </x-adara.select>
                            <div class="flex items-end gap-2"><div class="min-w-0 flex-1"><x-adara.input id="sale-payment-amount-{{ $position }}" label="Importe" inputmode="decimal" wire:model.blur="payments.{{ $position }}.amount" /></div><button type="button" wire:click="fillRemaining({{ $position }})" class="mb-2 text-xs font-bold text-adara-primary">Restante</button></div>
                            @if ($payment['method'] === 'cash')
                                <x-adara.input id="sale-payment-tendered-{{ $position }}" label="Efectivo entregado" inputmode="decimal" wire:model.blur="payments.{{ $position }}.cash_tendered" />
                                @if (isset($cashChanges[$position]) && $cashChanges[$position] > 0)
                                    <label class="flex items-start gap-2 text-xs text-adara-ink"><input type="checkbox" wire:model.change="payments.{{ $position }}.change_confirmed" class="mt-0.5 accent-adara-primary">Confirmo que entregué $ {{ number_format($cashChanges[$position] / 100, 2, ',', '.') }} de vuelto</label>
                                @elseif (isset($cashChanges[$position]) && $cashChanges[$position] < 0)
                                    <p role="alert" class="text-xs font-semibold text-adara-danger">El efectivo entregado no alcanza.</p>
                                @endif
                            @else
                                <x-adara.input id="sale-payment-commission-{{ $position }}" label="Comisión real" inputmode="decimal" wire:model.blur="payments.{{ $position }}.commission" />
                            @endif
                        </div>
                    @endforeach
                    @if ($paymentError)<p role="alert" class="text-xs font-semibold text-adara-danger">{{ $paymentError }}</p>@endif
                    @if ($quote)
                        @php $balance = $quote['total'] - $paid; @endphp
                        <p class="text-xs text-adara-readable-muted">Cobrado: $ {{ number_format($paid / 100, 2, ',', '.') }} · Saldo: $ {{ number_format(max(0, $balance) / 100, 2, ',', '.') }}</p>
                        @if ($balance < 0)<p role="alert" class="text-xs font-semibold text-adara-danger">Los cobros superan el total.</p>@endif
                        @if ($balance > 0 && $customerId === '')<p role="alert" class="text-xs font-semibold text-adara-danger">Para dejar saldo pendiente, elegí una clienta.</p>@endif
                    @endif
                </div>
                @if ($quote && $quote['below_cost'])<p role="alert" class="mt-3 rounded-adara-control bg-adara-danger-bg p-3 text-xs font-semibold text-adara-danger">El total queda debajo del costo de reposición y requiere confirmación adicional.</p>@endif
                <x-adara.button class="mt-[17px] w-full" wire:click="reviewSale" wire:loading.attr="disabled" wire:target="reviewSale" :disabled="$cart === [] || $quote === null">Confirmar venta</x-adara.button>
            </aside>
        </div>
    <x-adara.modal id="sale-discard" title="¿Descartar esta venta?" description="No se registrará ninguna operación.">
        <p class="text-sm leading-6 text-adara-readable-muted">Se perderá el carrito que tenés armado. Los productos y el stock no se modificarán.</p>
        <x-slot:footer><x-adara.button variant="ghost" x-on:click="close()">Volver</x-adara.button><x-adara.button variant="danger" wire:click="discardSale">Descartar venta</x-adara.button></x-slot:footer>
    </x-adara.modal>

    @if ($confirmedSaleId === null)
        <x-adara.modal id="sale-confirmation" title="Confirmar esta venta" description="Última revisión antes de registrar el cobro." wide class="!max-w-[630px]">
            @if ($review !== [])
                <div class="space-y-3 text-sm text-adara-ink">
                    @php $reviewUnitCount = array_sum(array_column($review['quote']['items'], 'quantity')); @endphp
                    <div class="flex justify-between"><span class="text-adara-readable-muted">Productos</span><strong>{{ $reviewUnitCount }} {{ $reviewUnitCount === 1 ? 'unidad' : 'unidades' }}</strong></div>
                    <div class="border-t border-adara-border"></div>
                    @foreach ($review['quote']['items'] as $item)
                        <div wire:key="sale-review-item-{{ $item['variant_id'] }}" class="flex justify-between gap-3"><span>{{ $item['product_name'] }} · {{ $item['presentation'] }} × {{ $item['quantity'] }}</span><strong class="shrink-0">$ {{ number_format(($item['gross'] - $item['discount']) / 100, 2, ',', '.') }}</strong></div>
                    @endforeach
                    <div class="border-t border-adara-border"></div>
                    @if ($review['quote']['discount'] > 0)<div class="flex justify-between"><span>Descuento</span><span>− $ {{ number_format($review['quote']['discount'] / 100, 2, ',', '.') }}</span></div>@endif
                    <div class="flex items-center justify-between"><strong>Total a cobrar</strong><strong class="text-[26px] font-extrabold">$ {{ number_format($review['quote']['total'] / 100, 2, ',', '.') }}</strong></div>
                    <div class="rounded-xl border border-adara-border bg-adara-primary-light/50 p-4 text-xs leading-5 text-adara-primary">Al guardar esta venta, el stock se descuenta y el costo de cada artículo queda fijado en esta operación.</div>
                    <p class="text-xs text-adara-readable-muted">Cliente: {{ $review['customer_name'] ?? 'Consumidor final' }} · Cobrado: $ {{ number_format($review['paid_cents'] / 100, 2, ',', '.') }} · Saldo: $ {{ number_format(($review['quote']['total'] - $review['paid_cents']) / 100, 2, ',', '.') }}</p>
                    @foreach ($review['payments'] as $payment)
                        <p wire:key="sale-review-payment-{{ $loop->index }}" class="text-xs text-adara-readable-muted">{{ ['cash' => 'Efectivo', 'transfer' => 'Transferencia', 'mercado_pago' => 'Mercado Pago', 'card' => 'Tarjeta'][$payment['method']] ?? $payment['method'] }}: $ {{ number_format($payment['amount_cents'] / 100, 2, ',', '.') }}@if ($payment['method'] === 'cash' && $payment['cash_change_cents'] > 0) · Vuelto: $ {{ number_format($payment['cash_change_cents'] / 100, 2, ',', '.') }}@endif</p>
                    @endforeach
                    @if ($review['quote']['below_cost'])<label class="flex items-start gap-3 rounded-adara-control border border-adara-danger p-3 text-adara-danger"><input type="checkbox" wire:model.change="acceptBelowCost" class="mt-1 accent-adara-primary"><span>Confirmo que esta venta queda debajo del costo de reposición.</span></label>@endif
                    @if ($message)<p role="alert" class="font-semibold text-adara-danger">{{ $message }}</p>@endif
                </div>
            @endif
            <x-slot:footer><x-adara.button variant="ghost" x-on:click="close()">Cancelar</x-adara.button><x-adara.button wire:click="confirmSale" wire:loading.attr="disabled" wire:target="confirmSale" :disabled="$review === [] || ($review['quote']['below_cost'] && ! $acceptBelowCost)"><span wire:loading.remove wire:target="confirmSale">Registrar venta</span><span wire:loading wire:target="confirmSale">Guardando...</span></x-adara.button></x-slot:footer>
        </x-adara.modal>
    @endif

    @if ($confirmedSaleId !== null)
        <x-adara.modal id="sale-success" title="¡Venta registrada!" description="Todo está listo.">
            <div class="flex min-h-40 flex-col items-center justify-center gap-3 text-center"><span class="flex h-12 w-12 items-center justify-center rounded-full bg-adara-success-bg text-2xl text-adara-success" aria-hidden="true">✓</span><strong class="text-base text-adara-ink">Se guardó correctamente</strong><p class="text-sm text-adara-readable-muted">{{ $confirmedSaleNumber }} · El stock se actualizó y la venta quedó registrada.</p></div>
            <x-slot:footer><x-adara.button variant="ghost" wire:click="startAnotherSale">Nueva venta</x-adara.button><a href="{{ route('sales.index') }}" class="inline-flex min-h-11 items-center rounded-adara-control bg-adara-primary px-4 text-sm font-semibold text-white">Ver ventas</a></x-slot:footer>
        </x-adara.modal>
    @endif
</div>
