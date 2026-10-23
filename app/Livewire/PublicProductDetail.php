<?php

namespace App\Livewire;

use App\Models\ProductVariant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PublicProductDetail extends Component
{
    #[Locked]
    public int $variantId;

    public function mount(ProductVariant $variant): void
    {
        $this->variantId = $variant->id;
    }

    public function render(): View
    {
        abort_unless((bool) DB::table('business_settings')->where('id', 1)->value('public_catalog_enabled'), 404);

        $product = ProductVariant::query()
            ->where('is_active', true)
            ->with(['group.category', 'group.subcategory', 'group.primaryImage'])
            ->findOrFail($this->variantId);

        return view('livewire.public-product-detail', compact('product'))
            ->layout('layouts.public');
    }
}
