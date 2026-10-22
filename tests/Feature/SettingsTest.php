<?php

namespace Tests\Feature;

use App\Actions\Catalog\ChangeMarkupAction;
use App\Actions\Sales\CreateSaleAction;
use App\Livewire\Settings\Index;
use App\Models\ProductVariant;
use App\Services\Pricing\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_screen_uses_approved_defaults_and_saves_business_data(): void
    {
        $this->get(route('settings.index'))->assertOk()->assertSee('Configuración del negocio')->assertSee('Hacia arriba a $100')->assertSee('80 %');
        Livewire::test(Index::class)->set('businessName', 'Adara Cosmética')->set('phone', '11 1234 5678')->set('address', 'Local 2')->call('saveBusiness')->assertHasNoErrors();
        $this->assertDatabaseHas('business_settings', ['id' => 1, 'business_name' => 'Adara Cosmética', 'phone' => '11 1234 5678', 'address' => 'Local 2']);
        Livewire::test(Index::class)->assertSet('businessName', 'Adara Cosmética');
    }

    public function test_global_markup_changes_only_inherited_prices_and_keeps_sale_snapshot(): void
    {
        DB::table('pricing_settings')->where('id', 1)->update(['global_markup_decimal' => '50']);
        $inherited = $this->pricedVariant('15000.00', null);
        $particular = $this->pricedVariant('12000.00', '20');
        DB::table('stock_balances')->where('product_variant_id', $inherited->id)->update(['quantity_on_hand' => 1]);
        $saleId = app(CreateSaleAction::class)->execute([['variant_id' => $inherited->id, 'quantity' => '1']], null, [['method' => 'transfer', 'amount' => '15000', 'commission' => '0']], 'none', '0', (string) Str::uuid());
        $oldSalePrice = DB::table('sale_items')->where('sale_id', $saleId)->value('unit_price_snapshot_cents');

        $changes = app(ChangeMarkupAction::class);
        $review = $changes->preview('global', null, '75');
        $this->assertCount(1, $review['lines']);
        $this->assertSame(1750000, $review['lines'][0]['new_price_cents']);
        $this->assertSame(1500000, $inherited->fresh()->current_price_cents);
        $key = (string) Str::uuid();
        $eventId = $changes->execute('global', null, '75', $key, $review['fingerprint']);
        $this->assertSame($eventId, $changes->execute('global', null, '75', $key, $review['fingerprint']));
        $this->assertSame(1750000, $inherited->fresh()->current_price_cents);
        $this->assertSame(1200000, $particular->fresh()->current_price_cents);
        $this->assertSame('20', $particular->fresh()->markup_override_decimal);
        $this->assertSame($oldSalePrice, DB::table('sale_items')->where('sale_id', $saleId)->value('unit_price_snapshot_cents'));
        $this->assertDatabaseCount('price_change_events', 1);
        $this->assertDatabaseHas('price_change_lines', ['price_change_event_id' => $eventId, 'product_variant_id' => $inherited->id, 'previous_price_cents' => 1500000, 'new_price_cents' => 1750000]);
    }

    public function test_variant_override_can_be_added_then_removed_after_review(): void
    {
        $variant = $this->pricedVariant('18000.00', null);
        $changes = app(ChangeMarkupAction::class);
        $review = $changes->preview('variant', $variant->id, '50');
        $this->assertSame(1500000, $review['lines'][0]['new_price_cents']);
        $changes->execute('variant', $variant->id, '50', (string) Str::uuid(), $review['fingerprint']);
        $this->assertSame('50', $variant->fresh()->markup_override_decimal);
        $this->assertSame(1500000, $variant->fresh()->current_price_cents);
        $reset = $changes->preview('variant', $variant->id, '');
        $changes->execute('variant', $variant->id, '', (string) Str::uuid(), $reset['fingerprint']);
        $this->assertNull($variant->fresh()->markup_override_decimal);
        $this->assertSame(1800000, $variant->fresh()->current_price_cents);
    }

    public function test_stale_preview_is_rejected_without_partial_updates(): void
    {
        $variant = $this->pricedVariant('18000.00', null);
        $changes = app(ChangeMarkupAction::class);
        $review = $changes->preview('global', null, '90');
        DB::table('product_variants')->where('id', $variant->id)->update(['current_unit_cost_10000' => 200000000]);
        $this->expectException(\DomainException::class);
        $changes->execute('global', null, '90', (string) Str::uuid(), $review['fingerprint']);
    }

    public function test_livewire_flow_requires_review_and_valid_markup(): void
    {
        $variant = $this->pricedVariant('18000.00', null);
        Livewire::test(Index::class)->set('globalMarkup', '-1')->call('reviewGlobal')->assertHasErrors(['globalMarkup'])->set('globalMarkup', '100')->call('reviewGlobal')->assertHasNoErrors()->assertSet('pricePreview.count', 1)->call('confirmPriceChange')->assertHasNoErrors();
        $this->assertSame(2000000, $variant->fresh()->current_price_cents);
        $this->assertSame('100', DB::table('pricing_settings')->where('id', 1)->value('global_markup_decimal'));
    }

    private function pricedVariant(string $priceDecimal, ?string $override): ProductVariant
    {
        $variant = ProductVariant::factory()->create(['markup_override_decimal' => $override]);
        DB::table('product_variants')->where('id', $variant->id)->update(['current_unit_cost_10000' => 100000000, 'current_price_cents' => Money::fromDecimal($priceDecimal)->cents]);

        return $variant->fresh();
    }
}
