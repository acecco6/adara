<?php

namespace App\Livewire\Products;

use App\Models\Category;
use App\Models\ProductGroup;
use App\Models\ProductVariant;
use App\Models\Subcategory;
use App\Models\Supplier;
use App\Services\CatalogService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Catalog extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public string $status = 'all';

    public string $selectedCategory = '';

    public string $selectedSubcategory = '';

    public string $name = '';

    public string $description = '';

    public string $presentation = '';

    public string $sku = '';

    public string $categoryId = '';

    public string $subcategoryId = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $photos = [];

    /** @var array<int, int|string> */
    public array $supplierIds = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
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

    public function updatedCategoryId(): void
    {
        $this->subcategoryId = '';
    }

    public function save(CatalogService $catalog): void
    {
        $group = $catalog->createProduct([
            'name' => $this->name,
            'description' => $this->description,
            'presentation' => $this->presentation,
            'sku' => $this->sku,
            'category_id' => $this->categoryId,
            'subcategory_id' => $this->subcategoryId,
            'supplier_ids' => $this->supplierIds,
        ], $this->photos);

        $this->redirectRoute('products.show', ['product' => $group->id], navigate: true);
    }

    public function render(): View
    {
        $activeStatus = match ($this->status) {
            'active' => true,
            'archived' => false,
            default => null,
        };

        $query = ProductGroup::query()
            ->with([
                'category',
                'subcategory',
                'variants' => fn ($variants) => $variants
                    ->with('balance')
                    ->when($activeStatus !== null, fn ($variants) => $variants->where('is_active', $activeStatus)),
                'primaryImage' => fn ($images) => $images->select('id', 'product_group_id', 'mime_type'),
            ])
            ->withCount('variants')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $search = trim($this->search);

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhereHas('variants', fn ($variants) => $variants->where('name', 'like', '%'.$search.'%')->orWhere('sku', 'like', '%'.$search.'%'));
            });
        }

        if ($activeStatus !== null) {
            $query->whereHas('variants', fn ($variants) => $variants->where('is_active', $activeStatus));
        }

        if ($this->selectedCategory !== '') {
            $query->where('category_id', $this->selectedCategory);
        }

        if ($this->selectedSubcategory !== '') {
            $query->where('subcategory_id', $this->selectedSubcategory);
        }

        return view('livewire.products.catalog', [
            'groups' => $query->paginate(12),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
            'subcategories' => Subcategory::query()->where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'activeProducts' => ProductGroup::query()->whereHas('variants', fn ($variants) => $variants->where('is_active', true))->count(),
            'activePresentations' => ProductVariant::query()->where('is_active', true)->count(),
            'presentationsInStock' => ProductVariant::query()->where('is_active', true)->whereHas('balance', fn ($balances) => $balances->where('quantity_on_hand', '>', 0))->count(),
        ]);
    }
}
