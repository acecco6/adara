<div class="flex flex-col gap-6">
    <header>
        <p class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-adara-primary">Sistema / Preferencias</p>
        <h1 class="mt-2 font-display text-[30px] font-semibold text-adara-ink">Configuración del negocio</h1>
        <p class="mt-1 text-sm text-adara-readable-muted">Ajustá tus datos y revisá cómo se calculan los precios actuales.</p>
    </header>

    <div class="grid items-start gap-5 xl:grid-cols-[1.15fr_0.85fr]">
        <div class="space-y-5">
            <section class="rounded-adara-card border border-adara-border bg-adara-surface p-5 shadow-adara-panel sm:p-6" aria-labelledby="pricing-settings-title">
                <h2 id="pricing-settings-title" class="font-display text-xl font-semibold text-adara-ink">Precios y rentabilidad</h2>
                <p class="mt-1 text-xs leading-5 text-adara-readable-muted">El recargo se aplica sobre el último costo de reposición. Confirmá el cambio después de ver los precios resultantes.</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div><x-adara.input id="global-markup" name="globalMarkup" label="Recargo general (%)" wire:model.live="globalMarkup" inputmode="decimal" hint="Se usa en las presentaciones sin recargo particular. Valor inicial aprobado: 80 %." required /><x-adara.button class="mt-3" wire:click="reviewGlobal" wire:loading.attr="disabled" wire:target="reviewGlobal">Revisar precios</x-adara.button></div>
                    <div class="space-y-3 rounded-adara-control border border-adara-border bg-adara-background p-4 text-sm"><div><span class="block text-xs text-adara-readable-muted">Redondeo comercial</span><strong>Hacia arriba a $100</strong></div><div><span class="block text-xs text-adara-readable-muted">Moneda</span><strong>Peso argentino (ARS)</strong></div><p class="text-xs leading-5 text-adara-readable-muted">Estas reglas aprobadas son fijas. El precio no se edita directamente.</p></div>
                </div>
            </section>

            <section class="rounded-adara-card border border-adara-border bg-adara-surface p-5 shadow-adara-panel sm:p-6" aria-labelledby="variant-markup-title">
                <h2 id="variant-markup-title" class="font-display text-xl font-semibold text-adara-ink">Recargo por presentación</h2>
                <p class="mt-1 text-xs leading-5 text-adara-readable-muted">Un recargo particular tiene prioridad sobre el general. Buscá una presentación para configurarlo.</p>
                <div class="mt-4"><x-adara.input id="variant-search" name="variantSearch" type="search" label="Buscar presentación" wire:model.live.debounce.300ms="variantSearch" placeholder="Código, producto o presentación" /></div>
                <div wire:loading.delay wire:target="variantSearch" role="status" class="mt-2 text-xs text-adara-readable-muted">Buscando presentaciones…</div>
                <div class="mt-3 max-h-52 divide-y divide-adara-border overflow-y-auto rounded-adara-control border border-adara-border" role="list" aria-label="Presentaciones encontradas">
                    @forelse ($variants as $variant)
                        <button type="button" role="listitem" wire:key="settings-variant-{{ $variant->id }}" wire:click="selectVariant({{ $variant->id }})" class="flex min-h-12 w-full items-center justify-between gap-3 px-3 py-2 text-left text-xs hover:bg-adara-primary-light focus-visible:outline-2 focus-visible:outline-adara-primary {{ $selectedVariantId === $variant->id ? 'bg-adara-primary-light' : '' }}"><span><strong class="block text-adara-ink">{{ $variant->group->name }} · {{ $variant->name }}</strong><span class="text-adara-readable-muted">{{ $variant->sku }}</span></span><span class="shrink-0 font-semibold text-adara-primary">{{ $variant->markup_override_decimal === null ? 'General' : $variant->markup_override_decimal.' %' }}</span></button>
                    @empty
                        <p class="p-4 text-xs text-adara-readable-muted">No encontramos presentaciones con esa búsqueda.</p>
                    @endforelse
                </div>
                @if ($selectedVariant)
                    <div class="mt-4 rounded-adara-control border border-adara-border bg-adara-background p-4" wire:key="selected-settings-variant-{{ $selectedVariant->id }}">
                        <p class="text-sm font-bold">{{ $selectedVariant->group->name }} · {{ $selectedVariant->name }}</p>
                        <p class="mt-1 text-xs text-adara-readable-muted">{{ $selectedVariant->sku }} · Precio actual: {{ $selectedVariant->current_price_cents === null ? 'Pendiente de primera recepción' : '$ '.number_format($selectedVariant->current_price_cents / 100, 2, ',', '.') }}</p>
                        <div class="mt-4 max-w-xs"><x-adara.input id="variant-markup" name="variantMarkup" label="Recargo particular" wire:model.live="variantMarkup" inputmode="decimal" placeholder="Vacío: usa el general" hint="Dejá el campo vacío para volver al recargo general." /></div>
                        <x-adara.button class="mt-3" wire:click="reviewVariant" wire:loading.attr="disabled" wire:target="reviewVariant">Revisar precio de esta presentación</x-adara.button>
                    </div>
                @endif
            </section>

            <section class="rounded-adara-card border border-adara-border bg-adara-surface p-5 shadow-adara-panel sm:p-6" aria-labelledby="price-history-title">
                <h2 id="price-history-title" class="font-display text-xl font-semibold">Cambios de recargo recientes</h2>
                <div class="mt-3 divide-y divide-adara-border">
                    @forelse ($recentChanges as $change)
                        <div class="flex flex-wrap justify-between gap-2 py-3 text-xs"><span>{{ $change->scope === 'global' ? 'Recargo general' : 'Presentación #'.$change->product_variant_id }} · {{ $change->previous_markup_decimal === null ? 'General' : $change->previous_markup_decimal.' %' }} → {{ $change->new_markup_decimal === null ? 'General' : $change->new_markup_decimal.' %' }}</span><span class="text-adara-readable-muted">{{ \Illuminate\Support\Carbon::parse($change->created_at, 'UTC')->setTimezone('America/Argentina/Buenos_Aires')->format('d/m/Y H:i') }}</span></div>
                    @empty
                        <p class="py-3 text-xs text-adara-readable-muted">Todavía no cambiaste recargos desde Configuración.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="space-y-5">
            <section class="rounded-adara-card border border-adara-border bg-adara-surface p-5 shadow-adara-panel sm:p-6" aria-labelledby="business-settings-title">
                <h2 id="business-settings-title" class="font-display text-xl font-semibold">Datos de tu negocio</h2>
                <div class="mt-5 space-y-4">
                    <x-adara.input id="business-name" name="businessName" label="Nombre comercial" wire:model="businessName" maxlength="120" required />
                    <x-adara.input id="business-phone" name="phone" label="Teléfono (opcional)" wire:model="phone" maxlength="80" />
                    <x-adara.input id="business-address" name="address" label="Dirección (opcional)" wire:model="address" maxlength="200" />
                    <x-adara.button wire:click="saveBusiness" wire:loading.attr="disabled" wire:target="saveBusiness">Guardar datos</x-adara.button>
                </div>
            </section>
            <section class="rounded-adara-card border border-adara-border bg-adara-surface p-5 shadow-adara-panel sm:p-6" aria-labelledby="price-help-title">
                <h2 id="price-help-title" class="font-display text-xl font-semibold">¿Cómo se calcula un precio?</h2>
                <div class="mt-4 rounded-adara-control bg-adara-primary-light p-4 text-sm leading-6"><strong>Costo de reposición</strong> = último costo recibido, con envío asignado.<br><strong>Precio de catálogo</strong> = costo × (1 + recargo / 100), redondeado hacia arriba a $100.<br><strong>Margen bruto por unidad</strong> = precio − costo histórico.</div>
                <p class="mt-3 text-xs leading-5 text-adara-readable-muted">Los cambios de recargo afectan precios vigentes; las ventas confirmadas conservan el precio y el costo con que se registraron.</p>
            </section>
            <section class="rounded-adara-card border border-adara-border bg-adara-surface p-5 shadow-adara-panel sm:p-6" aria-labelledby="stock-help-title"><h2 id="stock-help-title" class="font-display text-xl font-semibold">Alertas de stock</h2><p class="mt-2 text-xs leading-5 text-adara-readable-muted">El mínimo se define por presentación. El aviso aparece cuando el stock disponible llega a ese mínimo o queda por debajo.</p><a href="{{ route('products.index') }}" wire:navigate class="mt-4 inline-flex min-h-10 items-center font-semibold text-adara-primary hover:underline">Ir a productos →</a></section>
        </div>
    </div>

    <x-adara.modal id="price-change-review" title="Confirmar cambio de recargo" description="Revisá los precios calculados antes de aplicarlos." wide wire:ignore.self>
        @if ($pricePreview)
            <div class="space-y-4 text-sm"><p>{{ $pricePreview['scope'] === 'global' ? 'Recargo general' : 'Recargo particular' }}: <strong>{{ $pricePreview['previous_markup'] ?? 'General' }}{{ $pricePreview['previous_markup'] === null ? '' : ' %' }} → {{ $pricePreview['new_markup'] ?? 'General' }}{{ $pricePreview['new_markup'] === null ? '' : ' %' }}</strong></p><p class="text-xs text-adara-readable-muted">{{ $pricePreview['count'] }} {{ $pricePreview['count'] === 1 ? 'presentación con costo tendrá un precio calculado' : 'presentaciones con costo tendrán precios calculados' }}. Las presentaciones sin costo tomarán el recargo al recibir mercadería.</p>
                @if ($pricePreview['count'] > 0)<div class="max-h-72 overflow-y-auto rounded-adara-control border border-adara-border"><table class="w-full min-w-[460px] text-left text-xs"><thead class="bg-adara-background text-adara-readable-muted"><tr><th class="px-3 py-2">Presentación</th><th class="px-3 py-2 text-right">Anterior</th><th class="px-3 py-2 text-right">Nuevo</th></tr></thead><tbody class="divide-y divide-adara-border">@foreach ($pricePreview['lines'] as $line)<tr wire:key="price-preview-{{ $line['variant_id'] }}"><td class="px-3 py-2"><strong>{{ $line['name'] }}</strong><span class="block text-adara-readable-muted">{{ $line['sku'] }}</span></td><td class="px-3 py-2 text-right tabular-nums">{{ $line['previous_price_cents'] === null ? 'Pendiente' : '$ '.number_format($line['previous_price_cents'] / 100, 2, ',', '.') }}</td><td class="px-3 py-2 text-right font-bold tabular-nums text-adara-primary">$ {{ number_format($line['new_price_cents'] / 100, 2, ',', '.') }}</td></tr>@endforeach</tbody></table></div>@endif
                @if ($pricePreview['count'] > count($pricePreview['lines']))<p class="text-xs text-adara-readable-muted">Mostrando las primeras {{ count($pricePreview['lines']) }} presentaciones.</p>@endif
                <p class="text-xs text-adara-readable-muted">Los precios y costos guardados en ventas anteriores no cambiarán.</p>
            </div>
        @endif
        <x-slot:footer><x-adara.button variant="outline" x-on:click="close()">Volver</x-adara.button>@if ($pricePreview)<x-adara.button wire:click="confirmPriceChange" wire:loading.attr="disabled" wire:target="confirmPriceChange">Confirmar nuevos precios</x-adara.button>@endif</x-slot:footer>
    </x-adara.modal>
</div>
