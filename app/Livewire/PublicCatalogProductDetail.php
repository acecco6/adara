<?php

namespace App\Livewire;

use App\Models\ProductVariant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class PublicCatalogProductDetail extends Component
{
    #[Locked]
    public ?int $variantId = null;

    public string $loadError = '';

    #[On('public-product-detail-reset')]
    public function resetProduct(): void
    {
        $this->variantId = null;
        $this->loadError = '';
    }

    #[On('public-product-detail-load')]
    public function loadProduct(int $variantId): void
    {
        $this->loadError = '';
        $this->variantId = $variantId;

        $this->dispatch('adara:open', id: 'public-product-detail');
    }

    public function render(): View
    {
        abort_unless((bool) DB::table('business_settings')->where('id', 1)->value('public_catalog_enabled'), 404);

        $product = $this->variantId === null ? null : ProductVariant::query()
            ->where('is_active', true)
            ->with(['group.category', 'group.primaryImage'])
            ->find($this->variantId);

        if ($this->variantId !== null && $product === null) {
            $this->variantId = null;
            $this->loadError = 'No pudimos cargar este producto. Puede que ya no esté disponible.';
        }

        return view('livewire.public-catalog-product-detail', compact('product'));
    }
}
