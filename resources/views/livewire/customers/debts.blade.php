<div class="space-y-6">
    @php $ars = fn (int $cents) => '$ '.number_format($cents / 100, 2, ',', '.'); @endphp
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div><p class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-adara-primary">Administración / Clientas</p><h1 class="mt-2 font-display text-3xl font-semibold">Clientas con deuda</h1><p class="mt-2 text-sm text-adara-readable-muted">Consultá los saldos pendientes de cada venta. Cada cobro se registra en la venta elegida.</p></div>
        <a href="{{ route('customers.index') }}" wire:navigate class="rounded-adara-control border border-adara-border bg-adara-surface px-4 py-3 text-sm font-semibold">Volver a clientas</a>
    </header>

    <section class="grid gap-3 sm:grid-cols-3" aria-label="Resumen de deudas">
        <x-adara.kpi-card label="Deuda pendiente total" :value="$ars($totalBalanceCents)" detail="Solo ventas vigentes con saldo" tone="rose" />
        <x-adara.kpi-card label="Clientas con deuda" :value="number_format($totalCustomers, 0, ',', '.')" detail="Incluye clientas archivadas" />
        <x-adara.kpi-card label="Ventas pendientes" :value="number_format($totalSales, 0, ',', '.')" detail="Una clienta puede tener varias" tone="amber" />
    </section>

    <section class="overflow-hidden rounded-adara-card border border-adara-border bg-adara-surface shadow-adara-panel">
        <div class="border-b border-adara-border p-5"><label class="grid max-w-sm gap-1 text-sm font-semibold" for="debts-search">Buscar clienta<input id="debts-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Nombre o teléfono" class="rounded-adara-control border border-adara-border px-3 py-2 font-normal"></label></div>
        <div wire:loading.delay wire:target="search,selectCustomer" role="status" class="px-5 py-2 text-sm text-adara-readable-muted">Actualizando deudas…</div>
        <div class="overflow-x-auto"><table class="w-full min-w-[650px] text-left text-sm" aria-label="Clientas con saldos pendientes"><thead class="bg-adara-background text-xs uppercase text-adara-readable-muted"><tr><th class="p-4">Clienta</th><th class="p-4">Ventas pendientes</th><th class="p-4">Venta más antigua</th><th class="p-4">Deuda total</th><th class="p-4"><span class="sr-only">Detalle</span></th></tr></thead><tbody class="divide-y divide-adara-border">
            @forelse ($customers as $customer)
                <tr wire:key="debt-customer-{{ $customer->id }}"><td class="p-4"><strong>{{ $customer->name }}</strong>@if (! $customer->is_active)<span class="ml-2 text-xs text-adara-readable-muted">Archivada</span>@endif<p class="text-xs text-adara-readable-muted">{{ $customer->phone ?: 'Sin teléfono' }}</p></td><td class="p-4">{{ $customer->sales_count }}</td><td class="p-4">{{ \Carbon\CarbonImmutable::parse($customer->oldest_sale_at, 'UTC')->setTimezone('America/Argentina/Buenos_Aires')->format('d/m/Y') }}</td><td class="p-4 font-semibold text-adara-primary">{{ $ars((int) $customer->balance_cents) }}</td><td class="p-4 text-right"><button type="button" wire:click="selectCustomer({{ $customer->id }})" class="rounded-adara-control border border-adara-border px-3 py-2 font-semibold hover:text-adara-primary">Ver ventas</button></td></tr>
            @empty <tr><td colspan="5" class="p-8 text-center text-adara-readable-muted">{{ trim($search) === '' ? 'No hay clientas con deuda pendiente.' : 'No encontramos clientas con deuda para esa búsqueda.' }}</td></tr> @endforelse
        </tbody></table></div>
        @if ($customers->hasPages())<div class="border-t border-adara-border p-4">{{ $customers->links() }}</div>@endif
    </section>

    @if ($selectedCustomer)
        <section class="rounded-adara-card border border-adara-border bg-adara-surface p-5 shadow-adara-panel" aria-labelledby="customer-sales-title">
            <div class="flex items-center justify-between gap-3"><h2 id="customer-sales-title" class="font-display text-xl font-semibold">Ventas pendientes de {{ $selectedCustomer->name }}</h2><button type="button" wire:click="$set('selectedCustomerId', null)" class="text-sm font-semibold text-adara-primary">Cerrar</button></div>
            @forelse ($selectedSales as $sale)
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-b border-adara-border pb-4 text-sm"><div><strong>{{ $sale->number }}</strong><p class="text-xs text-adara-readable-muted">{{ \Carbon\CarbonImmutable::parse($sale->confirmed_at, 'UTC')->setTimezone('America/Argentina/Buenos_Aires')->format('d/m/Y') }} · Total {{ $ars((int) $sale->total_cents) }} · Cobrado {{ $ars((int) $sale->collected_cents) }}</p></div><div class="flex items-center gap-3"><strong class="text-adara-primary">Debe {{ $ars((int) $sale->balance_cents) }}</strong><a href="{{ route('sales.index', ['cobrar' => $sale->id]) }}" wire:navigate class="rounded-adara-control bg-adara-primary px-3 py-2 font-semibold text-white">Cobrar esta venta</a></div></div>
            @empty <p class="mt-4 text-sm text-adara-readable-muted">Esta clienta ya no tiene ventas pendientes.</p> @endforelse
        </section>
    @endif
</div>
