<?php

namespace App\Livewire\Reports;

use App\Services\Pricing\Money;
use App\Services\Reporting\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Index extends Component
{
    public string $from = '';

    public string $to = '';

    #[Locked]
    public ?string $expenseKey = null;

    public string $expenseConcept = '';

    public string $expenseCategory = '';

    public string $expenseAmount = '';

    public string $expenseDate = '';

    public string $expenseNotes = '';

    public function mount(): void
    {
        $today = CarbonImmutable::now('America/Argentina/Buenos_Aires');
        $this->from = $today->subDays(29)->format('Y-m-d');
        $this->to = $today->format('Y-m-d');
        $this->expenseDate = $this->to;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['from', 'to'], true)) {
            $this->resetValidation();
        }
    }

    public function openExpense(): void
    {
        $this->expenseKey = (string) Str::uuid();
        $this->dispatch('adara:open', id: 'report-expense');
    }

    public function saveExpense(): void
    {
        $this->validate([
            'expenseConcept' => 'required|string|min:2|max:160',
            'expenseCategory' => 'required|string|min:2|max:100',
            'expenseAmount' => ['required', 'regex:/^(?:0|[1-9][0-9]*)(?:[.,][0-9]{1,2})?$/'],
            'expenseDate' => 'required|date_format:Y-m-d',
            'expenseNotes' => 'nullable|string|max:1000',
        ]);

        $amount = Money::fromDecimal(str_replace(',', '.', $this->expenseAmount))->cents;
        if ($amount <= 0) {
            $this->addError('expenseAmount', 'Ingresá un importe mayor a cero.');

            return;
        }

        $key = $this->expenseKey ?? (string) Str::uuid();
        DB::table('business_expenses')->insertOrIgnore([
            'idempotency_key' => $key,
            'concept' => trim($this->expenseConcept),
            'category' => trim($this->expenseCategory),
            'amount_cents' => $amount,
            'spent_on' => $this->expenseDate,
            'notes' => trim($this->expenseNotes) ?: null,
        ]);

        $this->reset('expenseConcept', 'expenseCategory', 'expenseAmount', 'expenseNotes');
        $this->expenseKey = null;
        $this->dispatch('adara:close', id: 'report-expense');
        session()->flash('expenseSaved', 'Gasto registrado. Ya figura en el período correspondiente.');
    }

    public function export(ReportService $reports): StreamedResponse
    {
        $this->validatePeriod();
        $data = $reports->report($this->from, $this->to);

        return response()->streamDownload(function () use ($data): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Concepto', 'Importe ARS'], ';');
            foreach ([
                'Ventas originales' => $data['gross_sales_cents'],
                'Anulaciones' => -$data['cancelled_sales_cents'],
                'Ventas netas' => $data['net_sales_cents'],
                'Costo vendido neto' => $data['net_cost_cents'],
                'Margen bruto' => $data['gross_profit_cents'],
                'Cobros efectivos' => $data['collections_cents'],
                'Reintegros pagados' => $data['refunds_paid_cents'],
                'Comisiones' => $data['commissions_cents'],
                'Gastos operativos' => $data['expenses_cents'],
                'Resultado estimado' => $data['estimated_result_cents'],
            ] as $label => $cents) {
                fputcsv($stream, [$label, self::decimal($cents)], ';');
            }
            fputcsv($stream, [], ';');
            fputcsv($stream, ['SKU', 'Producto', 'Presentación', 'Unidades netas', 'Ventas ARS', 'Costo ARS', 'Margen ARS'], ';');
            foreach ($data['products'] as $product) {
                fputcsv($stream, [
                    self::safeCell($product['sku']), self::safeCell($product['name']), self::safeCell($product['presentation']),
                    $product['units'], self::decimal($product['sales_cents']), self::decimal($product['cost_cents']),
                    self::decimal($product['sales_cents'] - $product['cost_cents']),
                ], ';');
            }
            fclose($stream);
        }, 'adara-reporte-'.$this->from.'-'.$this->to.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(ReportService $reports): View
    {
        $data = null;
        try {
            $this->validatePeriod();
            $data = $reports->report($this->from, $this->to);
        } catch (ValidationException|\InvalidArgumentException) {
            // The date fields remain editable while the user completes a valid range.
        }

        return view('livewire.reports.index', ['report' => $data]);
    }

    private function validatePeriod(): void
    {
        $this->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d']);
        try {
            app(ReportService::class)->bounds($this->from, $this->to);
        } catch (\InvalidArgumentException) {
            $this->addError('to', 'La fecha final debe ser igual o posterior a la inicial.');
            throw ValidationException::withMessages(['to' => 'La fecha final debe ser igual o posterior a la inicial.']);
        }
    }

    private static function decimal(int $cents): string
    {
        return ($cents < 0 ? '-' : '').Money::fromCents(abs($cents))->toDecimal();
    }

    private static function safeCell(string $value): string
    {
        return preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $value) ? "'".$value : $value;
    }
}
