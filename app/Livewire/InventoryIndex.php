<?php

namespace App\Livewire;

use App\Models\ProductVariant;
use App\Services\Pricing\DecimalNumber;
use App\Services\StockAdjustmentService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;
use RangeException;

class InventoryIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = 'active';

    public string $level = 'all';

    #[Locked]
    public ?int $historyVariantId = null;

    #[Locked]
    public ?int $adjustmentVariantId = null;

    #[Locked]
    public string $adjustmentKey = '';

    public string $adjustmentDirection = 'in';

    public string $adjustmentQuantity = '';

    public string $adjustmentReason = '';

    public function updatedSearch(): void
    {
        $this->resetPage(pageName: 'stock-page');
    }

    public function updatedStatus(): void
    {
        $this->resetPage(pageName: 'stock-page');
    }

    public function updatedLevel(): void
    {
        $this->resetPage(pageName: 'stock-page');
    }

    public function showHistory(int $variantId): void
    {
        ProductVariant::findOrFail($variantId);
        $this->historyVariantId = $variantId;
        $this->resetPage(pageName: 'history-page');
        $this->dispatch('adara:inventory-history');
    }

    public function clearHistory(): void
    {
        $this->historyVariantId = null;
        $this->resetPage(pageName: 'history-page');
    }

    public function openAdjustment(int $variantId): void
    {
        $variant = ProductVariant::query()->with('group')->findOrFail($variantId);

        if (! $variant->is_active) {
            $this->addError('adjustment', 'La presentación está archivada.');

            return;
        }

        $this->adjustmentVariantId = $variantId;
        $this->adjustmentKey = (string) Str::ulid();
        $this->adjustmentDirection = 'in';
        $this->adjustmentQuantity = '';
        $this->adjustmentReason = '';
        $this->resetErrorBag();
        $this->dispatch('adara:open', id: 'inventory-adjustment-form');
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

            $variant = ProductVariant::findOrFail($this->adjustmentVariantId);

            if (! $variant->is_active) {
                throw new DomainException('La presentación está archivada.');
            }

            $delta = $this->adjustmentDirection === 'out' ? -$quantity : $quantity;
            $stock->adjust($variant->id, $delta, $this->adjustmentReason, $this->adjustmentKey);
        } catch (DomainException|InvalidArgumentException|RangeException $exception) {
            $this->addError('adjustment', $exception->getMessage());

            return;
        }

        $this->adjustmentVariantId = null;
        $this->dispatch('adara:close', id: 'inventory-adjustment-form');
        $this->dispatch('adara:toast', tone: 'success', message: 'Ajuste registrado.');
    }

    public function render(): View
    {
        $variants = ProductVariant::query()
            ->join('stock_balances', 'stock_balances.product_variant_id', '=', 'product_variants.id')
            ->with('group:id,name')
            ->select('product_variants.*', 'stock_balances.quantity_on_hand as available')
            ->when(trim($this->search) !== '', function ($query): void {
                $search = mb_substr(trim($this->search), 0, 100);
                $query->where(function ($query) use ($search): void {
                    $query->where('product_variants.sku', 'like', '%'.$search.'%')
                        ->orWhere('product_variants.name', 'like', '%'.$search.'%')
                        ->orWhereHas('group', fn ($groups) => $groups->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($this->status === 'active' || $this->status === 'archived',
                fn ($query) => $query->where('product_variants.is_active', $this->status === 'active'))
            ->when($this->level === 'at_minimum',
                fn ($query) => $query->whereColumn('stock_balances.quantity_on_hand', '<=', 'product_variants.minimum_stock'))
            ->when($this->level === 'out', fn ($query) => $query->where('stock_balances.quantity_on_hand', 0))
            ->when($this->level === 'available', fn ($query) => $query->where('stock_balances.quantity_on_hand', '>', 0))
            ->orderBy('product_variants.id')
            ->paginate(12, pageName: 'stock-page');

        $movements = DB::table('stock_movements')
            ->join('stock_operations', 'stock_operations.id', '=', 'stock_movements.stock_operation_id')
            ->join('product_variants', 'product_variants.id', '=', 'stock_movements.product_variant_id')
            ->join('product_groups', 'product_groups.id', '=', 'product_variants.product_group_id')
            ->when($this->historyVariantId !== null,
                fn ($query) => $query->where('stock_movements.product_variant_id', $this->historyVariantId))
            ->orderByDesc('stock_movements.id')
            ->select(
                'stock_movements.id', 'stock_movements.quantity_delta', 'stock_movements.quantity_after',
                'stock_movements.unit_cost_10000', 'stock_operations.kind', 'stock_operations.reason',
                'stock_operations.occurred_at', 'product_variants.name as presentation',
                'product_variants.sku', 'product_groups.name as product_name',
            )
            ->paginate(10, pageName: 'history-page');

        return view('livewire.inventory-index', [
            'variants' => $variants,
            'movements' => $movements,
            'historyVariant' => $this->historyVariantId === null ? null : ProductVariant::with('group:id,name')->find($this->historyVariantId),
            'adjustmentVariant' => $this->adjustmentVariantId === null ? null : ProductVariant::with('group:id,name')->find($this->adjustmentVariantId),
            'availableUnits' => (int) DB::table('stock_balances')->join('product_variants', 'product_variants.id', '=', 'stock_balances.product_variant_id')->where('product_variants.is_active', true)->sum('stock_balances.quantity_on_hand'),
            'presentationsInStock' => DB::table('stock_balances')->join('product_variants', 'product_variants.id', '=', 'stock_balances.product_variant_id')->where('product_variants.is_active', true)->where('stock_balances.quantity_on_hand', '>', 0)->count(),
            'lowStockPresentations' => DB::table('stock_balances')->join('product_variants', 'product_variants.id', '=', 'stock_balances.product_variant_id')->where('product_variants.is_active', true)->whereColumn('stock_balances.quantity_on_hand', '<=', 'product_variants.minimum_stock')->count(),
        ]);
    }
}
