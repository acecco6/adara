<?php

namespace App\Livewire\Products;

use App\Models\Category;
use App\Models\ProductGroup;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Subcategory;
use App\Models\Supplier;
use App\Services\CatalogService;
use App\Services\InitialStockService;
use App\Services\Pricing\DecimalNumber;
use App\Services\ProductImageService;
use App\Services\StockAdjustmentService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RangeException;

class Detail extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $groupId;

    public string $name = '';

    public string $description = '';

    public string $categoryId = '';

    public string $subcategoryId = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $photos = [];

    #[Locked]
    public ?int $photoToDeleteId = null;

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

    #[Locked]
    public ?int $initialVariantId = null;

    #[Locked]
    public string $initialKey = '';

    public string $initialQuantity = '';

    public string $initialCost = '';

    #[Locked]
    public ?int $adjustmentVariantId = null;

    #[Locked]
    public string $adjustmentKey = '';

    public string $adjustmentDirection = 'in';

    public string $adjustmentQuantity = '';

    public string $adjustmentReason = '';

    #[Locked]
    public ?int $minimumVariantId = null;

    public string $minimumQuantity = '';

    public function mount(int $groupId): void
    {
        $group = ProductGroup::findOrFail($groupId);
        $this->groupId = $group->id;
        $this->name = $group->name;
        $this->description = $group->description ?? '';
        $this->categoryId = $group->category_id === null ? '' : (string) $group->category_id;
        $this->subcategoryId = $group->subcategory_id === null ? '' : (string) $group->subcategory_id;
        $this->supplierIds = $group->suppliers()->pluck('suppliers.id')->all();
    }

    public function updatedCategoryId(): void
    {
        $this->subcategoryId = '';
    }

    public function saveGroup(CatalogService $catalog): void
    {
        $catalog->updateGroup($this->groupId, [
            'name' => $this->name,
            'description' => $this->description,
            'category_id' => $this->categoryId,
            'subcategory_id' => $this->subcategoryId,
            'supplier_ids' => $this->supplierIds,
        ]);
        $this->resetErrorBag();
        $this->dispatch('adara:close', id: 'product-edit');
        $this->dispatch('adara:toast', tone: 'success', message: 'Producto actualizado.');
    }

    public function savePhotos(ProductImageService $images): void
    {
        $images->add($this->groupId, $this->photos);
        $this->photos = [];
        $this->resetErrorBag('photos');
        $this->dispatch('adara:toast', tone: 'success', message: 'Fotos guardadas.');
    }

    public function makePrimaryPhoto(int $imageId, ProductImageService $images): void
    {
        $images->makePrimary($this->groupId, $imageId);
        $this->dispatch('adara:toast', tone: 'success', message: 'Portada actualizada.');
    }

    public function openPhotoRemoval(int $imageId): void
    {
        $this->photoToDeleteId = ProductImage::query()->where('product_group_id', $this->groupId)->findOrFail($imageId)->id;
        $this->dispatch('adara:open', id: 'photo-delete-confirm');
    }

    public function cancelPhotoRemoval(): void
    {
        $this->photoToDeleteId = null;
        $this->dispatch('adara:close', id: 'photo-delete-confirm');
    }

    public function removePhoto(ProductImageService $images): void
    {
        if ($this->photoToDeleteId === null) {
            return;
        }

        $images->remove($this->groupId, $this->photoToDeleteId);
        $this->cancelPhotoRemoval();
        $this->dispatch('adara:toast', tone: 'success', message: 'Foto eliminada.');
    }

    public function addVariant(CatalogService $catalog): void
    {
        $catalog->createVariant($this->groupId, ['presentation' => $this->presentation, 'sku' => $this->sku]);
        $this->reset('presentation', 'sku');
        $this->dispatch('adara:close', id: 'product-edit');
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

    public function openInitialStock(int $variantId): void
    {
        $this->variant($variantId);
        $this->initialVariantId = $variantId;
        $this->initialKey = (string) Str::ulid();
        $this->initialQuantity = '';
        $this->initialCost = '';
        $this->resetErrorBag();
        $this->dispatch('adara:open', id: 'initial-stock-form');
    }

    public function saveInitialStock(InitialStockService $stock): void
    {
        if ($this->initialVariantId === null) {
            return;
        }

        try {
            $stock->record($this->variant($this->initialVariantId)->id, $this->initialQuantity, $this->initialCost, $this->initialKey);
        } catch (DomainException|InvalidArgumentException|RangeException $exception) {
            $this->addError('initialStock', $exception->getMessage());

            return;
        }

        $this->initialVariantId = null;
        $this->dispatch('adara:close', id: 'initial-stock-form');
        $this->dispatch('adara:toast', tone: 'success', message: 'Stock inicial registrado.');
    }

    public function openAdjustment(int $variantId): void
    {
        if (! $this->variant($variantId)->is_active) {
            $this->addError('catalog', 'La presentación está archivada.');

            return;
        }
        $this->adjustmentVariantId = $variantId;
        $this->adjustmentKey = (string) Str::ulid();
        $this->adjustmentDirection = 'in';
        $this->adjustmentQuantity = '';
        $this->adjustmentReason = '';
        $this->resetErrorBag();
        $this->dispatch('adara:open', id: 'stock-adjustment-form');
    }

    public function saveAdjustment(StockAdjustmentService $stock): void
    {
        if ($this->adjustmentVariantId === null) {
            return;
        }

        try {
            if (! in_array($this->adjustmentDirection, ['in', 'out'], true)) {
                throw new InvalidArgumentException('Elegí entrada o salida.');
            }

            $quantity = DecimalNumber::parseUnsigned(trim($this->adjustmentQuantity), 0);
            if ($quantity === 0) {
                throw new InvalidArgumentException('Ingresá una cantidad mayor que cero.');
            }

            $variant = $this->variant($this->adjustmentVariantId);
            if (! $variant->is_active) {
                throw new DomainException('La presentación está archivada.');
            }

            $delta = $this->adjustmentDirection === 'out' ? -$quantity : $quantity;
            $stock->adjust($variant->id, $delta, $this->adjustmentReason, $this->adjustmentKey);
        } catch (DomainException|InvalidArgumentException|RangeException $exception) {
            $this->addError('stockAdjustment', $exception->getMessage());

            return;
        }

        $this->adjustmentVariantId = null;
        $this->dispatch('adara:close', id: 'stock-adjustment-form');
        $this->dispatch('adara:toast', tone: 'success', message: 'Ajuste registrado.');
    }

    public function openMinimum(int $variantId): void
    {
        $variant = $this->variant($variantId);
        $this->minimumVariantId = $variantId;
        $this->minimumQuantity = (string) $variant->minimum_stock;
        $this->resetErrorBag();
        $this->dispatch('adara:open', id: 'stock-minimum-form');
    }

    public function saveMinimum(InitialStockService $stock): void
    {
        if ($this->minimumVariantId === null) {
            return;
        }

        try {
            $stock->setMinimum($this->variant($this->minimumVariantId)->id, $this->minimumQuantity);
        } catch (DomainException|InvalidArgumentException|RangeException $exception) {
            $this->addError('stockMinimum', $exception->getMessage());

            return;
        }

        $this->minimumVariantId = null;
        $this->dispatch('adara:close', id: 'stock-minimum-form');
        $this->dispatch('adara:toast', tone: 'success', message: 'Mínimo actualizado.');
    }

    public function render(): View
    {
        $group = ProductGroup::with([
            'category', 'subcategory', 'suppliers',
            'images' => fn ($query) => $query->select('id', 'product_group_id', 'mime_type', 'position', 'is_primary')->orderBy('position')->orderBy('id'),
            'variants' => fn ($query) => $query->orderBy('id'), 'variants.balance',
        ])
            ->findOrFail($this->groupId);
        $movements = DB::table('stock_movements')
            ->join('product_variants', 'product_variants.id', '=', 'stock_movements.product_variant_id')
            ->join('stock_operations', 'stock_operations.id', '=', 'stock_movements.stock_operation_id')
            ->where('product_variants.product_group_id', $this->groupId)
            ->orderByDesc('stock_movements.id')
            ->limit(10)
            ->get(['stock_movements.id', 'stock_movements.quantity_delta', 'stock_movements.quantity_after', 'stock_movements.unit_cost_10000', 'stock_operations.kind', 'stock_operations.reason', 'stock_operations.occurred_at', 'product_variants.name as presentation']);

        $movementVariantIds = DB::table('stock_movements')
            ->whereIn('product_variant_id', $group->variants->modelKeys())
            ->distinct()->pluck('product_variant_id')->mapWithKeys(fn ($id) => [(int) $id => true]);

        $categories = Category::query()->where('is_active', true)
            ->when($group->category_id !== null, fn ($query) => $query->orWhereKey($group->category_id))
            ->orderBy('name')->get();
        $subcategories = Subcategory::query()->where('category_id', $this->categoryId)
            ->where(function ($query) use ($group): void {
                $query->where('is_active', true)
                    ->when($group->subcategory_id !== null, fn ($query) => $query->orWhereKey($group->subcategory_id));
            })->orderBy('name')->get();
        $suppliers = Supplier::query()->where('is_active', true)
            ->when($group->suppliers->isNotEmpty(), fn ($query) => $query->orWhereKey($group->suppliers->modelKeys()))
            ->orderBy('name')->get();

        return view('livewire.products.detail', compact('group', 'movements', 'movementVariantIds', 'categories', 'subcategories', 'suppliers'));
    }

    private function variant(int $variantId): ProductVariant
    {
        return ProductVariant::query()->where('product_group_id', $this->groupId)->findOrFail($variantId);
    }
}
