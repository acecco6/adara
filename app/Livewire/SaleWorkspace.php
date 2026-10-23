<?php

namespace App\Livewire;

use App\Actions\Sales\CreateSaleAction;
use App\Services\Pricing\DecimalNumber;
use App\Services\Pricing\Money;
use App\Services\Pricing\SaleQuoteService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RangeException;

class SaleWorkspace extends Component
{
    public string $search = '';

    /** @var array<array-key, mixed> Client state is validated before confirmation. */
    public array $cart = [];

    public string $customerId = '';

    public string $discountType = 'none';

    public string $discountValue = '0';

    /** @var array<array-key, mixed> Client state is validated before confirmation. */
    public array $payments = [];

    #[Locked]
    public string $confirmationKey = '';

    /** @var array<string, mixed> */
    #[Locked]
    public array $review = [];

    #[Locked]
    public string $reviewInputFingerprint = '';

    #[Locked]
    public ?int $confirmedSaleId = null;

    #[Locked]
    public string $confirmedSaleNumber = '';

    public bool $acceptBelowCost = false;

    public function mount(): void
    {
        $this->confirmationKey = (string) Str::uuid();
    }

    public function reviewSale(CreateSaleAction $sales): void
    {
        if ($this->confirmedSaleId !== null) {
            return;
        }

        try {
            $this->review = $sales->preview(
                $this->saleLines(), $this->selectedCustomerId(), $this->salePayments(),
                $this->discountType, $this->discountType === 'none' ? '0' : $this->discountValue,
            );
            $this->reviewInputFingerprint = $this->inputFingerprint();
            $this->acceptBelowCost = false;
            $this->dispatch('adara:open', id: 'sale-confirmation');
        } catch (DomainException|InvalidArgumentException|RangeException $exception) {
            $this->review = [];
            $this->notifyError($exception->getMessage());
        }
    }

    public function confirmSale(CreateSaleAction $sales): void
    {
        if ($this->confirmedSaleId !== null) {
            return;
        }

        if ($this->review === [] || $this->reviewInputFingerprint !== $this->inputFingerprint()) {
            $this->review = [];
            $this->notifyError('El carrito cambió. Revisá nuevamente la venta antes de confirmar.');
            $this->dispatch('adara:close', id: 'sale-confirmation');

            return;
        }

        if ($this->review['quote']['below_cost'] && ! $this->acceptBelowCost) {
            $this->notifyError('Confirmá expresamente la venta debajo del costo de reposición.');

            return;
        }

        try {
            $this->confirmedSaleId = $sales->execute(
                $this->saleLines(), $this->selectedCustomerId(), $this->salePayments(),
                $this->discountType, $this->discountType === 'none' ? '0' : $this->discountValue,
                $this->confirmationKey, $this->acceptBelowCost, $this->review['quote_fingerprint'],
            );
            $this->confirmedSaleNumber = (string) DB::table('sales')->where('id', $this->confirmedSaleId)->value('number');
            $this->clearDraft();
            $this->dispatch('adara:close', id: 'sale-confirmation');
            $this->dispatch('adara:open', id: 'sale-success');
        } catch (DomainException|InvalidArgumentException|RangeException $exception) {
            $this->review = [];
            $this->notifyError($exception->getMessage());
            $this->dispatch('adara:close', id: 'sale-confirmation');
        }
    }

    public function startAnotherSale(): void
    {
        if ($this->confirmedSaleId === null) {
            return;
        }

        $this->clearDraft();
        $this->confirmedSaleId = null;
        $this->confirmedSaleNumber = '';
        $this->confirmationKey = (string) Str::uuid();
        $this->dispatch('adara:close', id: 'sale-success');
    }

    private function clearDraft(): void
    {
        $this->cart = [];
        $this->payments = [];
        $this->customerId = '';
        $this->discountType = 'none';
        $this->discountValue = '0';
        $this->search = '';
        $this->review = [];
        $this->reviewInputFingerprint = '';
        $this->acceptBelowCost = false;
    }

    public function discardSale(): void
    {
        if ($this->confirmedSaleId === null) {
            $this->redirectRoute('sales.index', navigate: true);
        }
    }

    public function decreaseQuantity(int $variantId): void
    {
        try {
            $lines = $this->saleLines();
        } catch (InvalidArgumentException $exception) {
            $this->notifyError($exception->getMessage());

            return;
        }

        foreach ($lines as $position => $line) {
            if ($line['variant_id'] !== $variantId) {
                continue;
            }

            try {
                $quantity = DecimalNumber::parseUnsigned($line['quantity'], 0);
            } catch (InvalidArgumentException|RangeException) {
                $this->notifyError('Corregí la cantidad del producto.');

                return;
            }

            if ($quantity <= 1) {
                $this->removeVariant($variantId);

                return;
            }

            $this->cart[$position]['quantity'] = (string) ($quantity - 1);

            return;
        }
    }

    public function addVariant(int $variantId): void
    {
        $variant = DB::table('product_variants')
            ->leftJoin('stock_balances', 'stock_balances.product_variant_id', '=', 'product_variants.id')
            ->where('product_variants.id', $variantId)
            ->where('product_variants.is_active', true)
            ->first(['product_variants.current_price_cents', 'product_variants.current_unit_cost_10000', 'stock_balances.quantity_on_hand']);

        if ($variant === null || $variant->current_price_cents === null || (int) $variant->current_price_cents <= 0
            || $variant->current_unit_cost_10000 === null || (int) $variant->quantity_on_hand <= 0) {
            $this->notifyError('Esta presentación no tiene stock o precio disponible.');

            return;
        }

        try {
            $cart = $this->saleLines();
        } catch (InvalidArgumentException $exception) {
            $this->notifyError($exception->getMessage());

            return;
        }

        foreach ($cart as $position => $line) {
            if ($line['variant_id'] === $variantId) {
                try {
                    $quantity = DecimalNumber::parseUnsigned($line['quantity'], 0);
                } catch (InvalidArgumentException|RangeException) {
                    $this->notifyError('Corregí la cantidad antes de agregar otra unidad.');

                    return;
                }

                if ($quantity >= (int) $variant->quantity_on_hand) {
                    $this->notifyError('No hay más unidades disponibles de esta presentación.');

                    return;
                }

                $this->cart[$position]['quantity'] = (string) ($quantity + 1);

                return;
            }
        }

        $this->cart[] = ['variant_id' => $variantId, 'quantity' => '1'];
    }

    public function removeVariant(int $variantId): void
    {
        try {
            $this->cart = array_values(array_filter($this->saleLines(), fn (array $line): bool => $line['variant_id'] !== $variantId));
        } catch (InvalidArgumentException $exception) {
            $this->notifyError($exception->getMessage());
        }
    }

    public function addPayment(): void
    {
        $this->payments[] = [
            'method' => 'cash', 'amount' => '', 'commission' => '0',
            'cash_tendered' => '', 'change_confirmed' => false,
        ];
    }

    public function removePayment(int $position): void
    {
        if (isset($this->payments[$position])) {
            array_splice($this->payments, $position, 1);
        }
    }

    public function fillRemaining(int $position): void
    {
        if (! isset($this->payments[$position]) || ! is_array($this->payments[$position])) {
            return;
        }

        $quoteState = $this->currentQuote();

        if ($quoteState['quote'] === null) {
            $this->notifyError($quoteState['error'] ?? 'Revisá el carrito.');

            return;
        }

        $otherPayments = 0;

        try {
            foreach ($this->salePayments() as $index => $payment) {
                if ($index !== $position && trim($payment['amount']) !== '') {
                    $otherPayments = DecimalNumber::checkedAdd($otherPayments, Money::fromDecimal(str_replace(',', '.', trim($payment['amount'])))->cents);
                }
            }
        } catch (InvalidArgumentException|RangeException) {
            $this->notifyError('Revisá los importes de los otros cobros.');

            return;
        }

        $this->payments[$position]['amount'] = Money::fromCents(max(0, $quoteState['quote']['total'] - $otherPayments))->toDecimal();
    }

    private function notifyError(string $message): void
    {
        $this->dispatch('adara:toast', tone: 'error', message: $message);
    }

    public function render(SaleQuoteService $quotes): View
    {
        $term = trim($this->search);
        $resultsQuery = $this->variantQuery()
            ->where('stock_balances.quantity_on_hand', '>', 0)
            ->where('product_variants.current_price_cents', '>', 0)
            ->whereNotNull('product_variants.current_unit_cost_10000');

        if ($term !== '') {
            $resultsQuery->where(function (Builder $query) use ($term): void {
                $query->where('product_variants.sku', 'like', '%'.$term.'%')
                    ->orWhere('product_variants.name', 'like', '%'.$term.'%')
                    ->orWhere('product_groups.name', 'like', '%'.$term.'%')
                    ->orWhere('categories.name', 'like', '%'.$term.'%');
            });
        }

        $results = $resultsQuery
            ->addSelect(['primary_image_id' => DB::table('product_images')
                ->select('id')
                ->whereColumn('product_images.product_group_id', 'product_groups.id')
                ->orderByDesc('is_primary')
                ->orderBy('position')
                ->orderBy('id')
                ->limit(1)])
            ->orderBy('product_groups.name')->orderBy('product_variants.name')->limit(12)->get();

        try {
            $cartLines = $this->saleLines();
        } catch (InvalidArgumentException) {
            $cartLines = [];
        }

        $selected = $results->keyBy('id');
        $missingIds = array_diff(array_column($cartLines, 'variant_id'), $selected->keys()->all());

        if ($missingIds !== []) {
            $selected = $selected->union($this->variantQuery()
                ->whereIn('product_variants.id', $missingIds)->get()->keyBy('id'));
        }

        $displayPayments = [];

        foreach ($this->payments as $payment) {
            if (is_array($payment) && isset($payment['method'], $payment['amount'], $payment['commission'])
                && is_string($payment['method']) && is_string($payment['amount']) && is_string($payment['commission'])) {
                $displayPayments[] = $payment;
            }
        }

        $quoteState = $this->currentQuote($quotes);
        $paid = 0;
        $paymentError = null;
        $cashChanges = [];

        try {
            foreach ($displayPayments as $position => $payment) {
                if (trim($payment['amount']) !== '') {
                    $paid = DecimalNumber::checkedAdd($paid, Money::fromDecimal(str_replace(',', '.', trim($payment['amount'])))->cents);
                }

                if ($payment['method'] === 'cash' && trim($payment['amount']) !== '' && is_string($payment['cash_tendered'] ?? null)
                    && trim($payment['cash_tendered']) !== '') {
                    $cashChanges[$position] = Money::fromDecimal(str_replace(',', '.', trim($payment['cash_tendered'])))->cents
                        - Money::fromDecimal(str_replace(',', '.', trim($payment['amount'])))->cents;
                }
            }
        } catch (InvalidArgumentException|RangeException) {
            $paymentError = 'Revisá los importes de los cobros.';
        }

        return view('livewire.sale-workspace', [
            'results' => $results, 'selected' => $selected,
            'quote' => $quoteState['quote'], 'quoteError' => $quoteState['error'],
            'paid' => $paid, 'paymentError' => $paymentError,
            'cashChanges' => $cashChanges,
            'cart' => $cartLines, 'payments' => $displayPayments,
            'customers' => DB::table('customers')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** @return array{quote: ?array<string, mixed>, error: ?string} */
    private function currentQuote(?SaleQuoteService $quotes = null): array
    {
        if ($this->cart === []) {
            return ['quote' => null, 'error' => null];
        }

        try {
            $lines = [];

            foreach ($this->saleLines() as $line) {
                $quantity = DecimalNumber::parseUnsigned(trim($line['quantity']), 0);

                if ($quantity === 0) {
                    throw new InvalidArgumentException('Las cantidades deben ser mayores que cero.');
                }

                $lines[] = ['variant_id' => $line['variant_id'], 'quantity' => $quantity];
            }

            $quotes ??= app(SaleQuoteService::class);
            [$type, $value] = $quotes->normalizeDiscount($this->discountType, $this->discountType === 'none' ? '0' : $this->discountValue);

            return ['quote' => $quotes->quote($lines, $type, $value), 'error' => null];
        } catch (DomainException|InvalidArgumentException|RangeException $exception) {
            return ['quote' => null, 'error' => $exception->getMessage()];
        }
    }

    private function variantQuery(): Builder
    {
        return DB::table('product_variants')
            ->join('product_groups', 'product_groups.id', '=', 'product_variants.product_group_id')
            ->leftJoin('categories', 'categories.id', '=', 'product_groups.category_id')
            ->leftJoin('stock_balances', 'stock_balances.product_variant_id', '=', 'product_variants.id')
            ->where('product_variants.is_active', true)
            ->select('product_variants.id', 'product_variants.sku', 'product_variants.name', 'product_variants.product_group_id',
                'product_variants.current_price_cents', 'product_variants.current_unit_cost_10000',
                'product_groups.name as product_name', 'categories.name as category_name', 'stock_balances.quantity_on_hand');
    }

    /** @return list<array{variant_id: int, quantity: string}> */
    private function saleLines(): array
    {
        if (! array_is_list($this->cart)) {
            throw new InvalidArgumentException('Revisá los renglones del carrito.');
        }

        $lines = [];

        foreach ($this->cart as $line) {
            if (! is_array($line) || ! isset($line['variant_id'], $line['quantity'])
                || ! is_int($line['variant_id']) || ! is_string($line['quantity'])) {
                throw new InvalidArgumentException('Revisá los renglones del carrito.');
            }

            $lines[] = ['variant_id' => $line['variant_id'], 'quantity' => $line['quantity']];
        }

        return $lines;
    }

    private function selectedCustomerId(): ?int
    {
        if ($this->customerId === '') {
            return null;
        }

        if (! ctype_digit($this->customerId)) {
            throw new InvalidArgumentException('Elegí una clienta válida.');
        }

        return (int) $this->customerId;
    }

    /** @return list<array<string, mixed>> */
    private function salePayments(): array
    {
        if (! array_is_list($this->payments)) {
            throw new InvalidArgumentException('Revisá los cobros de la venta.');
        }

        $payments = [];

        foreach ($this->payments as $payment) {
            if (! is_array($payment) || ! isset($payment['method'], $payment['amount'], $payment['commission'])
                || ! is_string($payment['method']) || ! is_string($payment['amount']) || ! is_string($payment['commission'])) {
                throw new InvalidArgumentException('Revisá los cobros de la venta.');
            }

            $normalized = [
                'method' => $payment['method'], 'amount' => $payment['amount'],
                'commission' => $payment['method'] === 'cash' ? '0' : $payment['commission'],
            ];

            if ($payment['method'] === 'cash') {
                $normalized['cash_tendered'] = $payment['cash_tendered'] ?? '';
                $normalized['change_confirmed'] = $payment['change_confirmed'] ?? false;
            }

            $payments[] = $normalized;
        }

        return $payments;
    }

    private function inputFingerprint(): string
    {
        return hash('sha256', json_encode([
            $this->cart, $this->customerId, $this->payments, $this->discountType, $this->discountValue,
        ], JSON_THROW_ON_ERROR));
    }
}
