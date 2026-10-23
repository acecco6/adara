<?php

namespace App\Livewire\Categories;

use App\Models\Category;
use App\Models\Subcategory;
use App\Services\CatalogReferenceService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $name = '';

    public string $subcategoryName = '';

    public string $subcategoryCategoryId = '';

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $editingSubcategoryId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function startCreating(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->resetErrorBag();
        $this->dispatch('adara:open', id: 'category-form');
    }

    public function startEditing(int $categoryId): void
    {
        $category = Category::findOrFail($categoryId);
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->resetErrorBag();
        $this->dispatch('adara:open', id: 'category-form');
    }

    public function save(CatalogReferenceService $references): void
    {
        $references->saveCategory($this->editingId, ['name' => $this->name]);
        $this->resetErrorBag();
        $this->dispatch('adara:close', id: 'category-form');
        $this->dispatch('adara:toast', tone: 'success', message: 'Categoría guardada.');
    }

    public function startCreatingSubcategory(?int $categoryId = null): void
    {
        if ($categoryId !== null) {
            Category::findOrFail($categoryId);
        }

        $this->editingSubcategoryId = null;
        $this->subcategoryCategoryId = $categoryId === null ? '' : (string) $categoryId;
        $this->subcategoryName = '';
        $this->resetErrorBag();
        $this->dispatch('adara:open', id: 'subcategory-form');
    }

    public function startEditingSubcategory(int $subcategoryId): void
    {
        $subcategory = Subcategory::findOrFail($subcategoryId);
        $this->editingSubcategoryId = $subcategory->id;
        $this->subcategoryCategoryId = (string) $subcategory->category_id;
        $this->subcategoryName = $subcategory->name;
        $this->resetErrorBag();
        $this->dispatch('adara:open', id: 'subcategory-form');
    }

    public function saveSubcategory(CatalogReferenceService $references): void
    {
        $references->saveSubcategory($this->editingSubcategoryId, [
            'category_id' => $this->subcategoryCategoryId,
            'name' => $this->subcategoryName,
        ]);
        $this->resetErrorBag();
        $this->dispatch('adara:close', id: 'subcategory-form');
        $this->dispatch('adara:toast', tone: 'success', message: 'Subcategoría guardada.');
    }

    public function toggleSubcategoryActive(int $subcategoryId, CatalogReferenceService $references): void
    {
        $subcategory = Subcategory::findOrFail($subcategoryId);
        $references->setSubcategoryActive($subcategoryId, ! $subcategory->is_active);
        $this->dispatch('adara:toast', tone: 'success', message: $subcategory->is_active ? 'Subcategoría archivada.' : 'Subcategoría reactivada.');
    }

    public function toggleActive(int $categoryId, CatalogReferenceService $references): void
    {
        $category = Category::findOrFail($categoryId);
        $references->setCategoryActive($categoryId, ! $category->is_active);
        $this->dispatch('adara:toast', tone: 'success', message: $category->is_active ? 'Categoría archivada.' : 'Categoría reactivada.');
    }

    public function render(): View
    {
        $categories = Category::query()
            ->with(['subcategories' => fn ($query) => $query->orderBy('name')->orderBy('id')])
            ->withCount('groups')
            ->when(trim($this->search) !== '', fn ($query) => $query->where(function ($query): void {
                $search = '%'.trim($this->search).'%';
                $query->where('name', 'like', $search)->orWhereHas('subcategories', fn ($subcategories) => $subcategories->where('name', 'like', $search));
            }))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);

        $availableCategories = Category::query()->where('is_active', true)
            ->when($this->editingSubcategoryId !== null, fn ($query) => $query->orWhereKey(Subcategory::find($this->editingSubcategoryId)?->category_id))
            ->orderBy('name')->get();

        return view('livewire.categories.index', compact('categories', 'availableCategories'));
    }
}
