<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-adara-primary">Análisis del negocio</p>
            <h1 class="mt-2 font-display text-3xl font-semibold text-adara-ink">Reportes y resultados</h1>
            <p class="mt-2 text-sm text-adara-readable-muted">Entendé cuánto vendés, qué productos funcionan y cuál es tu resultado estimado.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="openExpense" class="rounded-adara-control border border-adara-border bg-adara-surface px-4 py-3 text-sm font-semibold text-adara-ink">Registrar gasto</button>
            <button type="button" wire:click="export" @disabled(! $report) class="rounded-adara-control bg-adara-primary px-4 py-3 text-sm font-semibold text-white disabled:opacity-50">Exportar CSV</button>
        </div>
    </header>

    <section class="flex flex-wrap items-end gap-3 rounded-adara-card border border-adara-border bg-adara-surface p-4 shadow-adara-panel" aria-label="Período del reporte">
        <label class="grid gap-1 text-sm font-semibold">Desde <input type="date" wire:model.live="from" class="rounded-adara-control border border-adara-border px-3 py-2 font-normal"></label>
        <label class="grid gap-1 text-sm font-semibold">Hasta <input type="date" wire:model.live="to" class="rounded-adara-control border border-adara-border px-3 py-2 font-normal"></label>
        <p class="text-xs text-adara-readable-muted">Cada operación se informa en su fecha efectiva.</p>
        @error('to') <p class="w-full text-sm text-red-700">{{ $message }}</p> @enderror
    </section>

    <p wire:loading.delay wire:target="from,to,export" role="status" class="text-sm text-adara-readable-muted">Actualizando reporte…</p>

    @if (session('expenseSaved')) <p role="status" class="rounded-adara-control bg-adara-success-bg p-3 text-sm text-adara-success">{{ session('expenseSaved') }}</p> @endif

    @if ($report)
        @php $ars = fn (int $cents) => ($cents < 0 ? '−' : '').'$ '.number_format(abs($cents) / 100, 2, ',', '.'); @endphp
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([['Ventas netas', $report['net_sales_cents'], $report['sales_count'].' ventas originales'], ['Costo vendido neto', $report['net_cost_cents'], 'Costo histórico congelado'], ['Margen bruto', $report['gross_profit_cents'], 'Ventas menos costo'], ['Margen sobre ventas', null, 'Margen bruto / ventas netas']] as [$label, $value, $hint])
                <section class="rounded-adara-card border border-adara-border bg-adara-surface p-5 shadow-adara-panel">
                    <p class="text-sm text-adara-readable-muted">{{ $label }}</p>
                    <p class="mt-3 font-display text-[26px] font-semibold text-adara-ink">{{ $value === null ? number_format($report['margin_percent'], 1, ',', '.').' %' : $ars($value) }}</p>
                    <p class="mt-1 text-xs text-adara-readable-muted">{{ $hint }}</p>
                </section>
            @endforeach
        </div>

        <section class="rounded-adara-card border border-adara-border bg-adara-surface p-5 shadow-adara-panel">
            <h2 class="font-display text-xl font-semibold">Ventas por categoría</h2>
            <p class="mt-1 text-xs text-adara-readable-muted">Distribución neta según la categoría actual del catálogo. Una categoría cambiada después de vender se verá con su nombre actual.</p>
            @if (array_sum(array_map(fn ($value) => max(0, $value), $report['categories'])) > 0)
                <div class="mt-4 h-56" wire:key="report-categories-{{ md5(json_encode($report['categories'])) }}"><canvas data-adara-chart="doughnut" data-labels='@json(array_keys($report['categories']))' data-values='@json(array_values($report['categories']))' aria-label="Distribución de ventas por categoría" role="img"></canvas></div>
                <ul class="mt-4 grid gap-2 text-sm sm:grid-cols-2">@foreach ($report['categories'] as $category => $amount)<li class="flex justify-between gap-3"><span>{{ $category }}</span><strong>{{ $ars($amount) }}</strong></li>@endforeach</ul>
            @else <p class="mt-4 text-sm text-adara-readable-muted">No hay ventas netas positivas por categoría en este período.</p> @endif
        </section>

        <div class="grid gap-5 lg:grid-cols-2">
            <section class="rounded-adara-card border border-adara-border bg-adara-surface p-5 shadow-adara-panel">
                <h2 class="font-display text-xl font-semibold">Tendencia de ventas netas</h2>
                <p class="mt-1 text-xs text-adara-readable-muted">Incluye ajustes en la fecha de anulación.</p>
                @if (count($report['trend']))
                    <div class="mt-5 h-56" wire:key="report-trend-{{ md5(json_encode($report['trend'])) }}"><canvas data-adara-chart="bar" data-labels='@json(array_keys($report['trend']))' data-values='@json(array_values($report['trend']))' aria-label="Ventas netas por fecha" role="img"></canvas></div>
                    <details class="mt-3 text-xs text-adara-readable-muted"><summary class="cursor-pointer">Ver datos del gráfico</summary><ul class="mt-2 grid grid-cols-2 gap-2">@foreach ($report['trend'] as $date => $amount)<li>{{ $date }}: {{ $ars($amount) }}</li>@endforeach</ul></details>
                @else <p class="mt-6 text-sm text-adara-readable-muted">Todavía no hay ventas ni anulaciones en este período.</p> @endif
            </section>
            <section class="rounded-adara-card border border-adara-border bg-adara-surface p-5 shadow-adara-panel">
                <h2 class="font-display text-xl font-semibold">Ventas y movimientos de dinero</h2>
                <dl class="mt-4 divide-y divide-adara-border text-sm">
                    @foreach ([['Ventas originales', 'gross_sales_cents'], ['Anulaciones', 'cancelled_sales_cents'], ['Descuentos netos', 'discounts_cents'], ['Cobros recibidos', 'collections_cents'], ['Reintegros pagados', 'refunds_paid_cents'], ['Comisiones', 'commissions_cents'], ['Gastos operativos', 'expenses_cents'], ['Resultado estimado', 'estimated_result_cents']] as [$label, $key])
                        <div class="flex justify-between gap-4 py-2"><dt>{{ $label }}</dt><dd class="font-semibold">{{ $ars($report[$key]) }}</dd></div>
                    @endforeach
                </dl>
                <p class="mt-3 text-xs leading-5 text-adara-readable-muted">Resultado estimado = margen bruto − comisiones − gastos. Los cobros y reintegros muestran dinero efectivo y no se suman ni descuentan de nuevo del resultado. No incluye valoración automática de mermas.</p>
            </section>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <section class="rounded-adara-card border border-adara-border bg-adara-surface p-5 shadow-adara-panel">
                <h2 class="font-display text-xl font-semibold">Saldos actuales</h2>
                <p class="mt-2 text-sm">Por cobrar: <strong>{{ $ars($report['receivables_cents']) }}</strong></p>
                <p class="mt-2 text-sm">Reintegros pendientes: <strong>{{ $ars($report['refund_pending_cents']) }}</strong></p>
                <p class="mt-3 text-xs text-adara-readable-muted">Estos saldos son actuales y no cambian con el filtro de fechas.</p>
            </section>
            <section class="rounded-adara-card border border-adara-border bg-adara-surface p-5 shadow-adara-panel">
                <h2 class="font-display text-xl font-semibold">Anulaciones del período</h2>
                <p class="mt-3 font-display text-2xl font-semibold">{{ $report['cancelled_count'] }}</p>
                <p class="mt-1 text-sm text-adara-readable-muted">Ajuste de {{ $ars($report['cancelled_sales_cents']) }} sobre ventas originales.</p>
            </section>
        </div>

        <section class="overflow-hidden rounded-adara-card border border-adara-border bg-adara-surface shadow-adara-panel">
            <div class="p-5"><h2 class="font-display text-xl font-semibold">Rentabilidad por producto</h2><p class="mt-1 text-xs text-adara-readable-muted">Presentaciones separadas, con importes históricos del período y anulaciones efectivas.</p></div>
            <div class="overflow-x-auto"><table class="w-full min-w-[690px] text-left text-sm"><thead class="bg-adara-background text-xs uppercase text-adara-readable-muted"><tr><th class="p-3">Producto</th><th class="p-3">Unidades netas</th><th class="p-3">Ventas</th><th class="p-3">Costo</th><th class="p-3">Margen bruto</th></tr></thead><tbody class="divide-y divide-adara-border">
                @forelse ($report['products'] as $product)
                    <tr><td class="p-3 font-semibold">{{ $product['name'] }} · {{ $product['presentation'] }}<span class="block text-xs font-normal text-adara-readable-muted">{{ $product['sku'] }}</span></td><td class="p-3">{{ $product['units'] }}</td><td class="p-3">{{ $ars($product['sales_cents']) }}</td><td class="p-3">{{ $ars($product['cost_cents']) }}</td><td class="p-3 font-semibold text-adara-success">{{ $ars($product['sales_cents'] - $product['cost_cents']) }}</td></tr>
                @empty <tr><td colspan="5" class="p-6 text-center text-adara-readable-muted">No hay productos vendidos en este período.</td></tr> @endforelse
            </tbody></table></div>
        </section>
    @else
        <p class="rounded-adara-card border border-adara-border bg-adara-surface p-6 text-sm text-adara-readable-muted">Elegí un período válido para consultar el reporte.</p>
    @endif

    <x-adara.modal id="report-expense" title="Registrar gasto" description="No incluyas mercadería ni envío del proveedor ya incorporado al costo." wide>
                <form wire:submit="saveExpense" class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label class="grid gap-1 text-sm font-semibold sm:col-span-2">Concepto<input wire:model="expenseConcept" required maxlength="160" class="rounded-adara-control border border-adara-border p-3"></label>@error('expenseConcept')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                    <label class="grid gap-1 text-sm font-semibold">Categoría<input wire:model="expenseCategory" required maxlength="100" class="rounded-adara-control border border-adara-border p-3"></label>@error('expenseCategory')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                    <label class="grid gap-1 text-sm font-semibold">Importe ARS<input wire:model="expenseAmount" required inputmode="decimal" placeholder="0,00" class="rounded-adara-control border border-adara-border p-3"></label>@error('expenseAmount')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                    <label class="grid gap-1 text-sm font-semibold">Fecha<input type="date" wire:model="expenseDate" required class="rounded-adara-control border border-adara-border p-3"></label>@error('expenseDate')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                    <label class="grid gap-1 text-sm font-semibold sm:col-span-2">Notas opcionales<textarea wire:model="expenseNotes" maxlength="1000" rows="2" class="rounded-adara-control border border-adara-border p-3"></textarea></label>
                    <div class="flex justify-end gap-2 sm:col-span-2"><button type="button" x-on:click="close()" class="rounded-adara-control border border-adara-border px-4 py-3">Cerrar</button><button type="submit" wire:loading.attr="disabled" wire:target="saveExpense" class="rounded-adara-control bg-adara-primary px-4 py-3 font-semibold text-white">Guardar gasto</button></div>
                </form>
    </x-adara.modal>
</div>
