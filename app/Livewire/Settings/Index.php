<?php

namespace App\Livewire\Settings;

use App\Actions\Catalog\ChangeMarkupAction;
use App\Models\ProductVariant;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RangeException;

class Index extends Component
{
    public string $globalMarkup = '80';

    public string $variantSearch = '';

    #[Locked]
    public ?int $selectedVariantId = null;

    public string $variantMarkup = '';

    public string $businessName = '';

    public string $phone = '';

    public string $address = '';

    public bool $publicCatalogEnabled = false;

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $pricePreview = null;

    #[Locked]
    public ?string $priceChangeKey = null;

    public function mount(): void
    {
        $this->globalMarkup = (string) DB::table('pricing_settings')->where('id', 1)->value('global_markup_decimal');
        $business = DB::table('business_settings')->where('id', 1)->first();
        $this->businessName = $business->business_name;
        $this->phone = $business->phone ?? '';
        $this->address = $business->address ?? '';
        $this->publicCatalogEnabled = (bool) $business->public_catalog_enabled;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['globalMarkup', 'variantMarkup'], true)) {
            $this->pricePreview = null;
            $this->resetValidation();
        }
    }

    public function selectVariant(int $variantId): void
    {
        $variant = ProductVariant::query()->findOrFail($variantId);
        $this->selectedVariantId = $variant->id;
        $this->variantMarkup = $variant->markup_override_decimal ?? '';
        $this->pricePreview = null;
        $this->resetValidation();
    }

    public function reviewGlobal(ChangeMarkupAction $changes): void
    {
        $this->review($changes, 'global');
    }

    public function reviewVariant(ChangeMarkupAction $changes): void
    {
        $this->review($changes, 'variant');
    }

    private function review(ChangeMarkupAction $changes, string $scope): void
    {
        $this->resetValidation();
        $this->pricePreview = null;
        try {
            $review = $changes->preview($scope, $scope === 'variant' ? $this->selectedVariantId : null, $scope === 'global' ? $this->globalMarkup : $this->variantMarkup);
        } catch (DomainException|InvalidArgumentException|RangeException $exception) {
            $this->addError($scope === 'global' ? 'globalMarkup' : 'variantMarkup', $exception->getMessage());

            return;
        }
        $this->pricePreview = [
            'scope' => $scope,
            'previous_markup' => $review['previous_markup'],
            'new_markup' => $review['new_markup'],
            'count' => count($review['lines']),
            'lines' => array_slice($review['lines'], 0, 20),
            'fingerprint' => $review['fingerprint'],
        ];
        $this->priceChangeKey = (string) Str::uuid();
        $this->dispatch('adara:open', id: 'price-change-review');
    }

    public function confirmPriceChange(ChangeMarkupAction $changes): void
    {
        if ($this->pricePreview === null || $this->priceChangeKey === null) {
            $this->addError('globalMarkup', 'Revisá el cambio de precios antes de confirmarlo.');

            return;
        }
        $scope = $this->pricePreview['scope'];
        try {
            $changes->execute($scope, $scope === 'variant' ? $this->selectedVariantId : null, $scope === 'global' ? $this->globalMarkup : $this->variantMarkup, $this->priceChangeKey, $this->pricePreview['fingerprint']);
        } catch (DomainException|InvalidArgumentException|RangeException $exception) {
            $this->pricePreview = null;
            $this->addError($scope === 'global' ? 'globalMarkup' : 'variantMarkup', $exception->getMessage());
            $this->dispatch('adara:close', id: 'price-change-review');

            return;
        }
        $this->pricePreview = null;
        $this->globalMarkup = (string) DB::table('pricing_settings')->where('id', 1)->value('global_markup_decimal');
        if ($this->selectedVariantId !== null) {
            $variant = ProductVariant::query()->find($this->selectedVariantId);
            $this->variantMarkup = $variant === null ? '' : ($variant->markup_override_decimal ?? '');
        }
        $this->dispatch('adara:close', id: 'price-change-review');
        $this->dispatch('adara:toast', tone: 'success', message: 'Recargo y precios actualizados. Las ventas anteriores conservan sus importes.');
    }

    public function saveBusiness(): void
    {
        $this->businessName = trim($this->businessName);
        $this->phone = trim($this->phone);
        $this->address = trim($this->address);
        $this->validate([
            'businessName' => ['required', 'string', 'max:120'],
            'phone' => ['string', 'max:80'],
            'address' => ['string', 'max:200'],
        ]);
        DB::table('business_settings')->where('id', 1)->update([
            'business_name' => $this->businessName,
            'phone' => $this->phone === '' ? null : $this->phone,
            'address' => $this->address === '' ? null : $this->address,
            'updated_at' => now(),
        ]);
        $this->dispatch('adara:toast', tone: 'success', message: 'Datos del negocio guardados.');
    }

    public function togglePublicCatalog(): void
    {
        $enabled = ! (bool) DB::table('business_settings')->where('id', 1)->value('public_catalog_enabled');
        DB::table('business_settings')->where('id', 1)->update([
            'public_catalog_enabled' => $enabled,
            'updated_at' => now(),
        ]);
        $this->publicCatalogEnabled = $enabled;
        $this->dispatch('adara:toast', tone: 'success', message: $enabled ? 'Catálogo público activado.' : 'Catálogo público desactivado.');
    }

    public function render(): View
    {
        $term = trim($this->variantSearch);
        $variants = ProductVariant::query()->with('group:id,name')
            ->when($term !== '', fn ($query) => $query->where(fn ($matches) => $matches->where('sku', 'like', '%'.$term.'%')->orWhere('name', 'like', '%'.$term.'%')->orWhereHas('group', fn ($groups) => $groups->where('name', 'like', '%'.$term.'%'))))
            ->orderBy('sku')->limit(12)->get();
        $selectedVariant = $this->selectedVariantId === null ? null : ProductVariant::query()->with('group:id,name')->find($this->selectedVariantId);
        $recentChanges = DB::table('price_change_events')->orderByDesc('id')->limit(5)->get();

        return view('livewire.settings.index', compact('variants', 'selectedVariant', 'recentChanges'));
    }
}
