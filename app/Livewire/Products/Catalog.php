<?php

namespace App\Livewire\Products;

use App\Models\Category;
use App\Models\ProductGroup;
use App\Models\Supplier;
use App\Services\CatalogService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Catalog extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = 'all';

    public string $name = '';

    public string $description = '';

    public string $presentation = '';

    public string $sku = '';

    public string $categoryId = '';

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

    public function save(CatalogService $catalog): void
    {
        $group = $catalog->createProduct([
            'name' => $this->name,
            'description' => $this->description,
            'presentation' => $this->presentation,
            'sku' => $this->sku,
            'category_id' => $this->categoryId,
            'supplier_ids' => $this->supplierIds,
        ]);

        $this->redirectRoute('products.show', ['product' => $group->id], navigate: true);
    }

    public function render(): View
    {
        $query = ProductGroup::query()
            ->with(['category', 'variants.balance'])
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

        if ($this->status === 'active' || $this->status === 'archived') {
            $active = $this->status === 'active';
            $query->whereHas('variants', fn ($variants) => $variants->where('is_active', $active));
        }

        return view('livewire.products.catalog', [
            'groups' => $query->paginate(12),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
