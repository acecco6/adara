<?php

namespace App\Livewire\Products;

use App\Models\Category;
use App\Models\ProductGroup;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Services\CatalogService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Detail extends Component
{
    #[Locked]
    public int $groupId;

    public string $name = '';

    public string $description = '';

    public string $categoryId = '';

    /** @var array<int, int|string> */
    public array $supplierIds = [];

    public string $presentation = '';

    public string $sku = '';

    public ?int $editingVariantId = null;

    public string $editPresentation = '';

    public string $editSku = '';

    #[Locked]
    public ?string $pendingAction = null;

    #[Locked]
    public ?int $pendingVariantId = null;

    public function mount(int $groupId): void
    {
        $group = ProductGroup::findOrFail($groupId);
        $this->groupId = $group->id;
        $this->name = $group->name;
        $this->description = $group->description ?? '';
        $this->categoryId = $group->category_id === null ? '' : (string) $group->category_id;
        $this->supplierIds = $group->suppliers()->pluck('suppliers.id')->all();
    }

    public function saveGroup(CatalogService $catalog): void
    {
        $catalog->updateGroup($this->groupId, [
            'name' => $this->name,
            'description' => $this->description,
            'category_id' => $this->categoryId,
            'supplier_ids' => $this->supplierIds,
        ]);
        $this->resetErrorBag();
        $this->dispatch('adara:toast', tone: 'success', message: 'Producto actualizado.');
    }

    public function addVariant(CatalogService $catalog): void
    {
        $catalog->createVariant($this->groupId, ['presentation' => $this->presentation, 'sku' => $this->sku]);
        $this->reset('presentation', 'sku');
        $this->dispatch('adara:toast', tone: 'success', message: 'Presentación agregada.');
    }

    public function startEditing(int $variantId): void
    {
        $variant = $this->variant($variantId);
        $this->editingVariantId = $variant->id;
        $this->editPresentation = $variant->name;
        $this->editSku = $variant->sku;
        $this->resetErrorBag();
    }

    public function saveVariant(CatalogService $catalog): void
    {
        if ($this->editingVariantId === null) {
            return;
        }

        $variant = $this->variant($this->editingVariantId);

        try {
            $catalog->updateVariant($variant->id, ['presentation' => $this->editPresentation, 'sku' => $this->editSku]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field === 'presentation' ? 'editPresentation' : 'editSku', $messages[0]);
            }

            return;
        }

        $this->editingVariantId = null;
        $this->dispatch('adara:toast', tone: 'success', message: 'Presentación actualizada.');
    }

    public function reactivate(int $variantId, CatalogService $catalog): void
    {
        $variant = $this->variant($variantId);
        $catalog->setActive($variant->id, true);
        $this->dispatch('adara:toast', tone: 'success', message: 'Presentación reactivada.');
    }

    public function confirm(string $action, ?int $variantId = null): void
    {
        if (! in_array($action, ['archive', 'delete_variant', 'delete_group'], true)) {
            return;
        }

        if ($action !== 'delete_group') {
            $this->variant($variantId ?? 0);
        }

        $this->pendingAction = $action;
        $this->pendingVariantId = $variantId;
        $this->resetErrorBag('catalog');
    }

    public function cancelConfirmation(): void
    {
        $this->pendingAction = null;
        $this->pendingVariantId = null;
    }

    public function performConfirmed(CatalogService $catalog): void
    {
        try {
            if ($this->pendingAction === 'archive' && $this->pendingVariantId !== null) {
                $catalog->setActive($this->variant($this->pendingVariantId)->id, false);
            } elseif ($this->pendingAction === 'delete_variant' && $this->pendingVariantId !== null) {
                $catalog->deleteUnusedVariant($this->variant($this->pendingVariantId)->id);
            } elseif ($this->pendingAction === 'delete_group') {
                $catalog->deleteEmptyGroup($this->groupId);
                $this->redirectRoute('products.index', navigate: true);

                return;
            } else {
                return;
            }
        } catch (DomainException $exception) {
            $this->addError('catalog', $exception->getMessage());

            return;
        }

        $this->cancelConfirmation();
        $this->dispatch('adara:toast', tone: 'success', message: 'Cambio guardado.');
    }

    public function render(): View
    {
        $group = ProductGroup::with(['category', 'suppliers', 'variants' => fn ($query) => $query->orderBy('id'), 'variants.balance'])
            ->findOrFail($this->groupId);
        $movements = DB::table('stock_movements')
            ->join('product_variants', 'product_variants.id', '=', 'stock_movements.product_variant_id')
            ->join('stock_operations', 'stock_operations.id', '=', 'stock_movements.stock_operation_id')
            ->where('product_variants.product_group_id', $this->groupId)
            ->orderByDesc('stock_movements.id')
            ->limit(10)
            ->get(['stock_movements.id', 'stock_movements.quantity_delta', 'stock_movements.quantity_after', 'stock_operations.reason', 'stock_operations.occurred_at', 'product_variants.name as presentation']);

        $categories = Category::query()->where('is_active', true)
            ->when($group->category_id !== null, fn ($query) => $query->orWhereKey($group->category_id))
            ->orderBy('name')->get();
        $suppliers = Supplier::query()->where('is_active', true)
            ->when($group->suppliers->isNotEmpty(), fn ($query) => $query->orWhereKey($group->suppliers->modelKeys()))
            ->orderBy('name')->get();

        return view('livewire.products.detail', compact('group', 'movements', 'categories', 'suppliers'));
    }

    private function variant(int $variantId): ProductVariant
    {
        return ProductVariant::query()->where('product_group_id', $this->groupId)->findOrFail($variantId);
    }
}
