<div class="space-y-6">
    @php $ars = fn (int $cents) => '$ '.number_format($cents / 100, 2, ',', '.'); @endphp
    <header class="flex flex-wrap items-start justify-between gap-4"><div><p class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-adara-primary">Operaciones</p><h1 class="mt-2 font-display text-3xl font-semibold">Cobros recibidos</h1><p class="mt-2 text-sm text-adara-readable-muted">Consultá lo que ingresó por fecha y medio de pago. Incluye cobros de ventas nuevas y de deudas.</p></div><a href="{{ route('customers.debts') }}" wire:navigate class="rounded-adara-control border border-adara-border bg-adara-surface px-4 py-3 text-sm font-semibold">Ver clientas con deuda</a></header>

    <section class="flex flex-wrap items-end gap-3 rounded-adara-card border border-adara-border bg-adara-surface p-4 shadow-adara-panel" aria-label="Filtros de cobros">
        <label class="grid gap-1 text-sm font-semibold">Desde<input type="date" wire:model.live="from" class="rounded-adara-control border border-adara-border px-3 py-2 font-normal"></label>
        <label class="grid gap-1 text-sm font-semibold">Hasta<input type="date" wire:model.live="to" class="rounded-adara-control border border-adara-border px-3 py-2 font-normal"></label>
        <label class="grid gap-1 text-sm font-semibold">Medio<select wire:model.live="method" class="rounded-adara-control border border-adara-border bg-adara-surface px-3 py-2 font-normal"><option value="all">Todos</option>@foreach ($methods as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
        <p class="text-xs text-adara-readable-muted">Fechas de Argentina. El importe es lo aplicado a ventas, después del vuelto.</p>
        @error('to')<p class="w-full text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
    </section>
    <p wire:loading.delay wire:target="from,to,method" role="status" class="text-sm text-adara-readable-muted">Actualizando cobros…</p>

    @if ($payments !== null)
        <section class="grid gap-3 sm:grid-cols-3" aria-label="Resumen de cobros">
            <x-adara.kpi-card label="Ingresado en el período" :value="$ars((int) $totals->amount_cents)" detail="Cobros reales aplicados a ventas" tone="rose" />
            <x-adara.kpi-card label="Cantidad de cobros" :value="number_format((int) $totals->count, 0, ',', '.')" detail="Cada pago se cuenta una sola vez" />
            <x-adara.kpi-card label="Comisiones registradas" :value="$ars((int) $totals->commission_cents)" detail="Costo asociado a estos cobros" tone="amber" />
        </section>

        <section class="overflow-hidden rounded-adara-card border border-adara-border bg-adara-surface shadow-adara-panel" aria-label="Historial de cobros">
            <div class="border-b border-adara-border p-5"><h2 class="font-display text-xl font-semibold">Historial por fecha de cobro</h2><p class="mt-1 text-xs text-adara-readable-muted">Una venta anulada conserva aquí su cobro original; el reintegro efectivo se informa por separado en Reportes.</p></div>
            <div class="overflow-x-auto"><table class="w-full min-w-[760px] text-left text-sm"><thead class="bg-adara-background text-xs uppercase text-adara-readable-muted"><tr><th class="p-4">Fecha</th><th class="p-4">Clienta</th><th class="p-4">Venta</th><th class="p-4">Medio</th><th class="p-4">Ingresado</th><th class="p-4">Comisión</th></tr></thead><tbody class="divide-y divide-adara-border">
                @forelse ($payments as $payment)
                    <tr wire:key="payment-{{ $payment->id }}"><td class="p-4 tabular-nums">{{ \Carbon\CarbonImmutable::parse($payment->paid_at, 'UTC')->setTimezone('America/Argentina/Buenos_Aires')->format('d/m/Y H:i') }}</td><td class="p-4">{{ $payment->customer_name ?: 'Consumidor final' }}</td><td class="p-4 font-semibold text-adara-primary">{{ $payment->sale_numbers ?: 'Sin venta vinculada' }}</td><td class="p-4">{{ $methods[$payment->method] ?? $payment->method }}@if ($payment->method === 'cash' && $payment->cash_change_cents > 0)<span class="block text-xs text-adara-readable-muted">Recibido {{ $ars((int) $payment->cash_tendered_cents) }} · Vuelto {{ $ars((int) $payment->cash_change_cents) }}</span>@endif</td><td class="p-4 font-semibold text-adara-success">{{ $ars((int) $payment->amount_cents) }}</td><td class="p-4">{{ $ars((int) $payment->commission_cents) }}</td></tr>
                @empty <tr><td colspan="6" class="p-8 text-center text-adara-readable-muted">No hay cobros para las fechas y el medio elegidos.</td></tr> @endforelse
            </tbody></table></div>
            @if ($payments->hasPages())<div class="border-t border-adara-border p-4">{{ $payments->links() }}</div>@endif
        </section>
    @endif
</div>
