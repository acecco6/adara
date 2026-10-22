<?php

namespace App\Livewire;

use App\Actions\Purchases\ClosePurchaseOrderAction;
use App\Actions\Purchases\ReceivePurchaseOrderAction;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\Pricing\DecimalNumber;
use App\Services\PurchaseOrderService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;
use RangeException;

class PurchaseWorkspace extends Component
{
    use WithPagination;

    #[Locked]
    public string $view = 'index';

    #[Locked]
    public ?int $orderId = null;

    #[Locked]
    public ?int $editingOrderId = null;

    #[Locked]
    public string $receiptKey = '';

    public string $search = '';

    public string $status = 'all';

    public string $supplierId = '';

    public string $shippingMethod = 'quantity';

    public string $generalShipping = '0';

    public string $notes = '';

    /** @var list<array{_key: string, variant_id: string, quantity: string, unit_paid: string}> */
    public array $orderLines = [];

    /** @var list<array{order_line_id: int, quantity: string, unit_paid: string}> */
    public array $receiptLines = [];

    public string $additionalShipping = '0';

    public bool $acceptCostChange = false;

    public bool $acceptOverdelivery = false;

    public string $closureReason = '';

    public function mount(string $view = 'index', ?int $orderId = null): void
    {
        if (! in_array($view, ['index', 'detail', 'receipts'], true)) {
            abort(404);
        }

        $this->view = $view;
        $this->orderId = $orderId;

        if ($view === 'detail') {
            PurchaseOrder::findOrFail($orderId);
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->editingOrderId = null;
        $this->supplierId = '';
        $this->shippingMethod = 'quantity';
        $this->generalShipping = '0';
        $this->notes = '';
        $this->orderLines = [['_key' => (string) Str::ulid(), 'variant_id' => '', 'quantity' => '1', 'unit_paid' => '']];
        $this->resetErrorBag();
        $this->dispatch('adara:open', id: 'purchase-order-form');
    }

    public function openEdit(): void
    {
        $order = PurchaseOrder::with('lines')->findOrFail($this->orderId);

        if ($order->status !== 'draft') {
            $this->addError('form', 'Solo se puede editar un borrador.');

            return;
        }

        $this->editingOrderId = $order->id;
        $this->supplierId = (string) $order->supplier_id;
        $this->shippingMethod = $order->shipping_method;
        $this->generalShipping = DecimalNumber::formatUnsigned($order->general_shipping_cents, 2);
        $this->notes = $order->notes ?? '';
        $this->orderLines = array_values($order->lines->map(fn ($line): array => [
            '_key' => (string) $line->id,
            'variant_id' => (string) $line->product_variant_id,
            'quantity' => (string) $line->quantity_ordered,
            'unit_paid' => DecimalNumber::formatUnsigned($line->unit_paid_cents, 2),
        ])->all());
        $this->resetErrorBag();
        $this->dispatch('adara:open', id: 'purchase-order-form');
    }

    public function addOrderLine(): void
    {
        $this->orderLines[] = ['_key' => (string) Str::ulid(), 'variant_id' => '', 'quantity' => '1', 'unit_paid' => ''];
    }

    public function removeOrderLine(int $position): void
    {
        if (count($this->orderLines) > 1 && isset($this->orderLines[$position])) {
            array_splice($this->orderLines, $position, 1);
        }
    }

    public function saveDraft(PurchaseOrderService $service): void
    {
        try {
            $input = [
                'supplier_id' => $this->supplierId,
                'shipping_method' => $this->shippingMethod,
                'general_shipping' => $this->generalShipping,
                'notes' => $this->notes,
                'lines' => array_map(fn (array $line): array => [
                    'variant_id' => data_get($line, 'variant_id', ''),
                    'quantity' => data_get($line, 'quantity', ''),
                    'unit_paid' => data_get($line, 'unit_paid', ''),
                ], $this->orderLines),
            ];
            $order = $this->editingOrderId === null
                ? $service->createDraft($input)
                : $service->updateDraft($this->editingOrderId, $input);
        } catch (ValidationException $exception) {
            $this->addError('form', collect($exception->errors())->flatten()->first() ?: 'Revisá los datos del pedido.');

            return;
        } catch (DomainException|InvalidArgumentException|RangeException $exception) {
            $this->addError('form', $exception->getMessage());

            return;
        }

        $this->dispatch('adara:close', id: 'purchase-order-form');
        $this->dispatch('adara:toast', tone: 'success', message: 'Borrador guardado.');
        $this->redirectRoute('purchases.show', ['order' => $order->id], navigate: true);
    }

    public function confirm(PurchaseOrderService $service): void
    {
        try {
            $service->confirm((int) $this->orderId);
        } catch (DomainException|InvalidArgumentException|RangeException $exception) {
            $this->addError('confirm', $exception->getMessage());

            return;
        }

        $this->dispatch('adara:close', id: 'purchase-confirm');
        $this->dispatch('adara:toast', tone: 'success', message: 'Pedido confirmado. El stock cambia al recibir.');
    }

    public function openReceipt(): void
    {
        $order = PurchaseOrder::with('lines')->findOrFail($this->orderId);

        if (! in_array($order->status, ['confirmed', 'partially_received'], true)) {
            $this->addError('receipt', 'El pedido no tiene unidades pendientes.');

            return;
        }

        $received = $this->receivedByLine($order->id);
        $extended = $this->extendedByLine($order->id);
        $this->receiptLines = array_values($order->lines->map(function ($line) use ($received, $extended): array {
            $pending = $line->quantity_ordered + ($extended[$line->id] ?? 0) - ($received[$line->id] ?? 0);

            return [
                'order_line_id' => $line->id,
                'quantity' => (string) max(0, $pending),
                'unit_paid' => DecimalNumber::formatUnsigned($line->unit_paid_cents, 2),
            ];
        })->filter(fn (array $line): bool => $line['quantity'] !== '0')->values()->all());
        $this->additionalShipping = '0';
        $this->acceptCostChange = false;
        $this->acceptOverdelivery = false;
        $this->receiptKey = (string) Str::ulid();
        $this->resetErrorBag();
        $this->dispatch('adara:open', id: 'purchase-receipt-form');
    }

    public function saveReceipt(ReceivePurchaseOrderAction $action): void
    {
        try {
            $lines = array_values(array_filter($this->receiptLines, fn (array $line): bool => trim($line['quantity']) !== '' && trim($line['quantity']) !== '0'));
            $action->execute(
                (int) $this->orderId,
                $lines,
                $this->additionalShipping,
                $this->receiptKey,
                $this->acceptCostChange,
                $this->acceptOverdelivery,
            );
        } catch (DomainException|InvalidArgumentException|RangeException $exception) {
            $this->addError('receipt', $exception->getMessage());

            return;
        }

        $this->receiptKey = (string) Str::ulid();
        $this->dispatch('adara:close', id: 'purchase-receipt-form');
        $this->dispatch('adara:toast', tone: 'success', message: 'Recepción registrada y stock actualizado.');
    }

    public function closePending(ClosePurchaseOrderAction $action): void
    {
        try {
            $action->execute((int) $this->orderId, $this->closureReason);
        } catch (DomainException|InvalidArgumentException|RangeException $exception) {
            $this->addError('closure', $exception->getMessage());

            return;
        }

        $this->closureReason = '';
        $this->dispatch('adara:close', id: 'purchase-close-form');
        $this->dispatch('adara:toast', tone: 'success', message: 'Pendientes cancelados; envío sin asignar registrado.');
    }

    public function render(): View
    {
        $orders = null;
        $receipts = null;
        $order = null;
        $receiptHistory = collect();
        $receiptLineHistory = collect();
        $closure = null;

        if ($this->view === 'index') {
            $orders = PurchaseOrder::query()->with(['supplier:id,name', 'lines'])
                ->when(in_array($this->status, ['draft', 'confirmed', 'partially_received', 'received', 'closed_partial'], true),
                    fn ($query) => $query->where('status', $this->status))
                ->when(trim($this->search) !== '', function ($query): void {
                    $search = mb_substr(trim($this->search), 0, 100);
                    $query->where(fn ($query) => $query->where('number', 'like', '%'.$search.'%')
                        ->orWhereHas('supplier', fn ($suppliers) => $suppliers->where('name', 'like', '%'.$search.'%')));
                })
                ->orderByDesc('id')->paginate(10);
        } elseif ($this->view === 'receipts') {
            $receipts = DB::table('purchase_receipt_lines')
                ->join('purchase_receipts', 'purchase_receipts.id', '=', 'purchase_receipt_lines.purchase_receipt_id')
                ->join('purchase_order_lines', 'purchase_order_lines.id', '=', 'purchase_receipt_lines.purchase_order_line_id')
                ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_receipts.purchase_order_id')
                ->select('purchase_receipt_lines.*', 'purchase_receipts.purchase_order_id', 'purchase_receipts.received_at', 'purchase_orders.number', 'purchase_orders.supplier_name_snapshot', 'purchase_order_lines.product_name_snapshot', 'purchase_order_lines.presentation_snapshot', 'purchase_order_lines.sku_snapshot')
                ->orderByDesc('purchase_receipts.id')->orderBy('purchase_receipt_lines.line_position')->paginate(10);
        } else {
            $order = PurchaseOrder::with(['supplier', 'lines.variant.group'])->findOrFail($this->orderId);
            $received = $this->receivedByLine($order->id);
            $extended = $this->extendedByLine($order->id);
            $closure = DB::table('purchase_order_closures')->where('purchase_order_id', $order->id)->first();
            $canceled = $closure === null ? [] : DB::table('purchase_order_canceled_lines')
                ->where('purchase_order_closure_id', $closure->id)
                ->pluck('quantity_canceled', 'purchase_order_line_id')->map(fn ($quantity): int => (int) $quantity)->all();

            foreach ($order->lines as $line) {
                $line->setAttribute('received_quantity', $received[$line->id] ?? 0);
                $line->setAttribute('extended_quantity', $extended[$line->id] ?? 0);
                $line->setAttribute('canceled_quantity', $canceled[$line->id] ?? 0);
                $line->setAttribute('pending_quantity', max(0, $line->quantity_ordered + ($extended[$line->id] ?? 0) - ($received[$line->id] ?? 0) - ($canceled[$line->id] ?? 0)));
            }

            $receiptHistory = DB::table('purchase_receipts')->where('purchase_order_id', $order->id)->orderBy('id')->get();
            $receiptLineHistory = DB::table('purchase_receipt_lines')
                ->join('purchase_receipts', 'purchase_receipts.id', '=', 'purchase_receipt_lines.purchase_receipt_id')
                ->join('purchase_order_lines', 'purchase_order_lines.id', '=', 'purchase_receipt_lines.purchase_order_line_id')
                ->where('purchase_receipts.purchase_order_id', $order->id)
                ->select('purchase_receipt_lines.*', 'purchase_order_lines.sku_snapshot', 'purchase_order_lines.presentation_snapshot')
                ->orderBy('purchase_receipt_lines.line_position')->get()->groupBy('purchase_receipt_id');
        }

        return view('livewire.purchase-workspace', [
            'orders' => $orders,
            'receipts' => $receipts,
            'order' => $order,
            'receiptHistory' => $receiptHistory,
            'receiptLineHistory' => $receiptLineHistory,
            'closure' => $closure,
            'orderCount' => $this->view === 'index' ? PurchaseOrder::query()->count() : 0,
            'pendingOrderCount' => $this->view === 'index' ? PurchaseOrder::query()->whereIn('status', ['confirmed', 'partially_received'])->count() : 0,
            'receiptCount' => $this->view === 'index' || $this->view === 'receipts' ? DB::table('purchase_receipts')->count() : 0,
            'receivedUnits' => $this->view === 'receipts' ? (int) DB::table('purchase_receipt_lines')->sum('quantity_received') : 0,
            'receivedShippingCents' => $this->view === 'receipts' ? (int) DB::table('purchase_receipts')->selectRaw('SUM(general_shipping_applied_cents + additional_shipping_cents) as total')->first()?->total : 0,
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'variants' => ProductVariant::query()->where('is_active', true)->with('group:id,name')->orderBy('sku')->get(['id', 'product_group_id', 'sku', 'name']),
        ]);
    }

    /** @return array<int, int> */
    private function receivedByLine(int $orderId): array
    {
        return DB::table('purchase_receipt_lines')
            ->join('purchase_receipts', 'purchase_receipts.id', '=', 'purchase_receipt_lines.purchase_receipt_id')
            ->where('purchase_receipts.purchase_order_id', $orderId)
            ->select('purchase_receipt_lines.purchase_order_line_id')
            ->selectRaw('SUM(purchase_receipt_lines.quantity_received) as quantity')
            ->groupBy('purchase_receipt_lines.purchase_order_line_id')
            ->get()->mapWithKeys(fn ($line): array => [(int) $line->purchase_order_line_id => (int) $line->quantity])->all();
    }

    /** @return array<int, int> */
    private function extendedByLine(int $orderId): array
    {
        return DB::table('purchase_order_quantity_extensions')
            ->join('purchase_receipts', 'purchase_receipts.id', '=', 'purchase_order_quantity_extensions.purchase_receipt_id')
            ->where('purchase_receipts.purchase_order_id', $orderId)
            ->select('purchase_order_quantity_extensions.purchase_order_line_id')
            ->selectRaw('SUM(purchase_order_quantity_extensions.extra_quantity) as quantity')
            ->groupBy('purchase_order_quantity_extensions.purchase_order_line_id')
            ->get()->mapWithKeys(fn ($line): array => [(int) $line->purchase_order_line_id => (int) $line->quantity])->all();
    }
}
