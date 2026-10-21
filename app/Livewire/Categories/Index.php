<?php

namespace App\Livewire\Categories;

use App\Models\Category;
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

    #[Locked]
    public ?int $editingId = null;

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

    public function toggleActive(int $categoryId, CatalogReferenceService $references): void
    {
        $category = Category::findOrFail($categoryId);
        $references->setCategoryActive($categoryId, ! $category->is_active);
        $this->dispatch('adara:toast', tone: 'success', message: $category->is_active ? 'Categoría archivada.' : 'Categoría reactivada.');
    }

    public function render(): View
    {
        $categories = Category::query()
            ->withCount('groups')
            ->when(trim($this->search) !== '', fn ($query) => $query->where('name', 'like', '%'.trim($this->search).'%'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);

        return view('livewire.categories.index', ['categories' => $categories]);
    }
}
