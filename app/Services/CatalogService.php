<?php

namespace App\Services;

use App\Models\Category;
use App\Models\ProductGroup;
use App\Models\ProductVariant;
use App\Models\Subcategory;
use App\Models\Supplier;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogService
{
    public function __construct(private ProductImageService $productImages) {}

    /**
     * @param  array{name: string, description?: ?string, presentation: string, sku?: ?string, category_id?: ?string, subcategory_id?: ?string, supplier_ids?: array<int, int|string>}  $input
     * @param  array<int, UploadedFile>  $images
     */
    public function createProduct(array $input, array $images = []): ProductGroup
    {
        return DB::transaction(function () use ($input, $images): ProductGroup {
            [$categoryId, $subcategoryId, $supplierIds] = $this->validatedRelations($input);
            $group = ProductGroup::create($this->validatedGroup($input) + [
                'category_id' => $categoryId,
                'subcategory_id' => $subcategoryId,
            ]);
            $group->suppliers()->sync($supplierIds);
            $this->createVariant($group->id, $input);
            $this->productImages->add($group->id, $images);

            return $group;
        });
    }

    /**
     * @param  array{presentation: string, sku?: ?string}  $input
     */
    public function createVariant(int $groupId, array $input): ProductVariant
    {
        ProductGroup::findOrFail($groupId);
        $data = $this->validatedVariant($input);

        return ProductVariant::create([
            'product_group_id' => $groupId,
            'name' => $data['presentation'],
            'sku' => $data['sku'],
            'is_active' => true,
            'minimum_stock' => 0,
        ]);
    }

    /**
     * @param  array{name: string, description?: ?string, category_id?: ?string, subcategory_id?: ?string, supplier_ids?: array<int, int|string>}  $input
     */
    public function updateGroup(int $groupId, array $input): ProductGroup
    {
        return DB::transaction(function () use ($groupId, $input): ProductGroup {
            $group = ProductGroup::findOrFail($groupId);
            [$categoryId, $subcategoryId, $supplierIds] = $this->validatedRelations($input, $group);
            $group->update($this->validatedGroup($input) + [
                'category_id' => $categoryId,
                'subcategory_id' => $subcategoryId,
            ]);
            $group->suppliers()->sync($supplierIds);

            return $group;
        });
    }

    /**
     * @param  array{presentation: string, sku: string}  $input
     */
    public function updateVariant(int $variantId, array $input): ProductVariant
    {
        $variant = ProductVariant::findOrFail($variantId);
        $data = $this->validatedVariant($input, $variantId);
        $variant->update(['name' => $data['presentation'], 'sku' => $data['sku']]);

        return $variant;
    }

    public function setActive(int $variantId, bool $active): void
    {
        DB::transaction(function () use ($variantId, $active): void {
            $query = ProductVariant::query()->whereKey($variantId);

            if (! $active) {
                $query->whereHas('balance', fn ($balance) => $balance->where('quantity_on_hand', 0));
            }

            if ($query->update(['is_active' => $active]) !== 1) {
                throw new DomainException('Solo podés archivar una presentación existente con stock cero.');
            }
        });
    }

    public function deleteUnusedVariant(int $variantId): void
    {
        DB::transaction(function () use ($variantId): void {
            $variant = ProductVariant::findOrFail($variantId);
            $hasOperationalReferences = DB::table('stock_movements')->where('product_variant_id', $variantId)->exists()
                || DB::table('purchase_order_lines')->where('product_variant_id', $variantId)->exists()
                || DB::table('purchase_receipt_lines')->where('product_variant_id', $variantId)->exists()
                || DB::table('sale_items')->where('product_variant_id', $variantId)->exists()
                || DB::table('price_change_events')->where('product_variant_id', $variantId)->exists()
                || DB::table('price_change_lines')->where('product_variant_id', $variantId)->exists();

            if ($variant->balance()->value('quantity_on_hand') !== 0 || $hasOperationalReferences) {
                throw new DomainException('La presentación tiene stock o está vinculada a operaciones y no se puede eliminar.');
            }

            $variant->delete();
        });
    }

    public function deleteEmptyGroup(int $groupId): void
    {
        DB::transaction(function () use ($groupId): void {
            $group = ProductGroup::findOrFail($groupId);

            if ($group->variants()->exists()) {
                throw new DomainException('El producto todavía tiene presentaciones.');
            }

            $group->delete();
        });
    }

    /**
     * @param  array{name: string, description?: ?string}  $input
     * @return array{name: string, description: ?string}
     */
    private function validatedGroup(array $input): array
    {
        $data = Validator::make([
            'name' => trim($input['name']),
            'description' => isset($input['description']) ? trim($input['description']) : null,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        return ['name' => $data['name'], 'description' => $data['description'] ?? null];
    }

    /**
     * @param  array{category_id?: ?string, subcategory_id?: ?string, supplier_ids?: array<int, int|string>}  $input
     * @return array{?int, ?int, array<int, int>}
     */
    private function validatedRelations(array $input, ?ProductGroup $group = null): array
    {
        $selectedCategoryId = $input['category_id'] ?? null;
        $data = Validator::make([
            'category_id' => $selectedCategoryId === '' ? null : $selectedCategoryId,
            'subcategory_id' => ($input['subcategory_id'] ?? '') === '' ? null : $input['subcategory_id'],
            'supplier_ids' => $input['supplier_ids'] ?? [],
        ], [
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'subcategory_id' => ['nullable', 'integer', Rule::exists('subcategories', 'id')],
            'supplier_ids' => ['array'],
            'supplier_ids.*' => ['integer', 'distinct', Rule::exists('suppliers', 'id')],
        ], [
            'category_id.exists' => 'Seleccioná una categoría válida.',
            'subcategory_id.exists' => 'Seleccioná una subcategoría válida.',
            'supplier_ids.*.exists' => 'Seleccioná proveedores válidos.',
        ])->validate();

        $categoryId = isset($data['category_id']) ? (int) $data['category_id'] : null;
        $subcategoryId = isset($data['subcategory_id']) ? (int) $data['subcategory_id'] : null;
        $supplierIds = array_map('intval', $data['supplier_ids']);

        if ($categoryId !== null && $categoryId !== $group?->category_id && ! Category::findOrFail($categoryId)->is_active) {
            throw ValidationException::withMessages(['category_id' => 'Seleccioná una categoría activa.']);
        }

        if ($subcategoryId !== null) {
            $subcategory = Subcategory::findOrFail($subcategoryId);

            if ($subcategory->category_id !== $categoryId) {
                throw ValidationException::withMessages(['subcategory_id' => 'La subcategoría no pertenece a la categoría elegida.']);
            }

            if ($subcategoryId !== $group?->subcategory_id && ! $subcategory->is_active) {
                throw ValidationException::withMessages(['subcategory_id' => 'Seleccioná una subcategoría activa.']);
            }
        }

        $previousSupplierIds = $group?->suppliers()->pluck('suppliers.id')->all() ?? [];
        $inactiveNewSuppliers = Supplier::query()->whereIn('id', array_diff($supplierIds, $previousSupplierIds))->where('is_active', false)->exists();

        if ($inactiveNewSuppliers) {
            throw ValidationException::withMessages(['supplier_ids' => 'Seleccioná proveedores activos.']);
        }

        return [$categoryId, $subcategoryId, $supplierIds];
    }

    /**
     * @param  array{presentation: string, sku?: ?string}  $input
     * @return array{presentation: string, sku: string}
     */
    private function validatedVariant(array $input, ?int $ignoreVariantId = null): array
    {
        $manualSku = Str::upper(trim($input['sku'] ?? ''));
        $sku = $manualSku !== '' || $ignoreVariantId !== null ? $manualSku : 'AD-'.Str::ulid();
        $unique = Rule::unique('product_variants', 'sku');

        if ($ignoreVariantId !== null) {
            $unique->ignore($ignoreVariantId);
        }

        $data = Validator::make([
            'presentation' => trim($input['presentation']),
            'sku' => $sku,
        ], [
            'presentation' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:80', $unique],
        ])->validate();

        return ['presentation' => $data['presentation'], 'sku' => $data['sku']];
    }
}
