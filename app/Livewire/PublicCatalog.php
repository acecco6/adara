<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\ProductVariant;
use App\Models\Subcategory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class PublicCatalog extends Component
{
    use WithPagination;

    public string $search = '';

    public string $selectedCategory = '';

    public string $selectedSubcategory = '';

    public string $sort = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedCategory(): void
    {
        $this->selectedSubcategory = '';
        $this->resetPage();
    }

    public function updatedSelectedSubcategory(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->selectedCategory = '';
        $this->selectedSubcategory = '';
        $this->sort = '';
        $this->resetPage();
    }

    public function openProductDetail(int $variantId): void
    {
        $this->dispatch('public-product-detail-load', variantId: $variantId);
    }

    public function render(): View
    {
        abort_unless((bool) DB::table('business_settings')->where('id', 1)->value('public_catalog_enabled'), 404);

        $search = mb_substr(trim($this->search), 0, 100);
        $categoryId = ctype_digit($this->selectedCategory) ? (int) $this->selectedCategory : null;
        $subcategoryId = ctype_digit($this->selectedSubcategory) ? (int) $this->selectedSubcategory : null;

        $query = ProductVariant::query()
            ->select(['id', 'product_group_id', 'name', 'current_price_cents'])
            ->where('is_active', true)
            ->with([
                'group:id,name,category_id,subcategory_id',
                'group.category:id,name',
                'group.subcategory:id,name',
                'group.primaryImage:id,product_group_id,mime_type',
            ])
            ->when($search !== '', fn ($variants) => $variants->whereHas('group', fn ($groups) => $groups->where('name', 'like', '%'.$search.'%')))
            ->when($this->selectedCategory !== '', fn ($variants) => $variants->whereHas('group', fn ($groups) => $groups->where('category_id', $categoryId)))
            ->when($this->selectedSubcategory !== '', fn ($variants) => $variants->whereHas('group', fn ($groups) => $groups->where('subcategory_id', $subcategoryId)));

        if (in_array($this->sort, ['price_asc', 'price_desc'], true)) {
            $query->orderByRaw('current_price_cents IS NULL')
                ->orderBy('current_price_cents', $this->sort === 'price_asc' ? 'asc' : 'desc')
                ->orderBy('id');
        } else {
            $query->orderByDesc('id');
        }

        $products = $query->paginate(12);
        $categories = Category::query()
            ->whereHas('groups.variants', fn ($variants) => $variants->where('is_active', true))
            ->orderBy('name')->get(['id', 'name']);
        $subcategories = $categoryId === null ? collect() : Subcategory::query()
            ->where('category_id', $categoryId)
            ->whereHas('groups.variants', fn ($variants) => $variants->where('is_active', true))
            ->orderBy('name')->get(['id', 'name']);

        return view('livewire.public-catalog', compact('products', 'categories', 'subcategories'))
            ->layout('layouts.public');
    }
}
