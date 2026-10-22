<?php

namespace App\Actions\Catalog;

use App\Services\Pricing\Percentage;
use App\Services\Pricing\PriceCalculator;
use App\Services\Pricing\UnitCost;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ChangeMarkupAction
{
    public function __construct(private PriceCalculator $prices) {}

    /** @return array{scope: string, variant_id: ?int, previous_markup: ?string, new_markup: ?string, global_markup: string, lines: list<array<string, mixed>>, fingerprint: string} */
    public function preview(string $scope, ?int $variantId, string $markupInput): array
    {
        if (! in_array($scope, ['global', 'variant'], true) || ($scope === 'variant' && ($variantId === null || $variantId <= 0))) {
            throw new InvalidArgumentException('Elegí un tipo de recargo y una presentación válidos.');
        }
        $newMarkup = $this->normalizeMarkup($markupInput, $scope === 'variant');
        $globalMarkup = DB::table('pricing_settings')->where('id', 1)->value('global_markup_decimal');
        if (! is_string($globalMarkup)) {
            throw new DomainException('Falta el recargo general de precios.');
        }
        $previousMarkup = $scope === 'global' ? $globalMarkup : DB::table('product_variants')->where('id', $variantId)->value('markup_override_decimal');
        if ($scope === 'variant' && ! DB::table('product_variants')->where('id', $variantId)->exists()) {
            throw new DomainException('La presentación ya no está disponible.');
        }
        if ($previousMarkup === $newMarkup || ($previousMarkup !== null && $newMarkup !== null && $this->normalizeMarkup((string) $previousMarkup, false) === $newMarkup)) {
            throw new DomainException('El recargo ingresado ya está vigente.');
        }

        $variants = DB::table('product_variants')
            ->join('product_groups', 'product_groups.id', '=', 'product_variants.product_group_id')
            ->select('product_variants.id', 'product_variants.sku', 'product_variants.name', 'product_variants.markup_override_decimal', 'product_variants.current_unit_cost_10000', 'product_variants.current_price_cents', 'product_groups.name as product_name')
            ->when($scope === 'global', fn ($query) => $query->whereNull('product_variants.markup_override_decimal'))
            ->when($scope === 'variant', fn ($query) => $query->where('product_variants.id', $variantId))
            ->orderBy('product_variants.id')->get();

        $lines = [];
        foreach ($variants as $variant) {
            if ($variant->current_unit_cost_10000 === null) {
                continue;
            }
            $oldEffectiveMarkup = $variant->markup_override_decimal ?? $globalMarkup;
            $newEffectiveMarkup = $scope === 'global' ? $newMarkup : ($newMarkup ?? $globalMarkup);
            $lines[] = [
                'variant_id' => (int) $variant->id,
                'sku' => $variant->sku,
                'name' => $variant->product_name.' · '.$variant->name,
                'cost_10000' => (int) $variant->current_unit_cost_10000,
                'previous_price_cents' => $variant->current_price_cents === null ? null : (int) $variant->current_price_cents,
                'new_price_cents' => $this->prices->catalogPrice(UnitCost::fromTenThousandths((int) $variant->current_unit_cost_10000), Percentage::fromDecimal($newEffectiveMarkup))->cents,
                'previous_markup' => $oldEffectiveMarkup,
                'new_markup' => $newEffectiveMarkup,
            ];
        }

        return [
            'scope' => $scope,
            'variant_id' => $scope === 'variant' ? $variantId : null,
            'previous_markup' => $previousMarkup,
            'new_markup' => $newMarkup,
            'global_markup' => $globalMarkup,
            'lines' => $lines,
            'fingerprint' => hash('sha256', json_encode([$scope, $variantId, $previousMarkup, $newMarkup, $globalMarkup, $lines], JSON_THROW_ON_ERROR)),
        ];
    }

    public function execute(string $scope, ?int $variantId, string $markupInput, string $idempotencyKey, string $expectedFingerprint): int
    {
        if (trim($idempotencyKey) === '' || Str::length($idempotencyKey) > 128) {
            throw new InvalidArgumentException('La clave de confirmación no es válida.');
        }
        try {
            return DB::transaction(function () use ($scope, $variantId, $markupInput, $idempotencyKey, $expectedFingerprint): int {
                $previous = DB::table('price_change_events')->where('idempotency_key', $idempotencyKey)->first();
                if ($previous !== null) {
                    return $this->previousEventId($previous, $scope, $variantId, $markupInput, $expectedFingerprint);
                }
                DB::table('pricing_settings')->where('id', 1)->lockForUpdate()->first();
                $review = $this->preview($scope, $variantId, $markupInput);
                if ($review['fingerprint'] !== $expectedFingerprint) {
                    throw new DomainException('Los costos o precios cambiaron desde la revisión. Revisá los precios nuevamente.');
                }
                $eventId = DB::table('price_change_events')->insertGetId([
                    'idempotency_key' => $idempotencyKey, 'request_fingerprint' => $expectedFingerprint,
                    'scope' => $scope, 'product_variant_id' => $review['variant_id'],
                    'previous_markup_decimal' => $review['previous_markup'], 'new_markup_decimal' => $review['new_markup'],
                    'created_at' => now(),
                ]);
                foreach ($review['lines'] as $line) {
                    DB::table('price_change_lines')->insert([
                        'price_change_event_id' => $eventId, 'product_variant_id' => $line['variant_id'],
                        'unit_cost_snapshot_10000' => $line['cost_10000'],
                        'previous_price_cents' => $line['previous_price_cents'], 'new_price_cents' => $line['new_price_cents'],
                        'previous_markup_decimal' => $line['previous_markup'], 'new_markup_decimal' => $line['new_markup'],
                    ]);
                    DB::table('product_variants')->where('id', $line['variant_id'])->update(['current_price_cents' => $line['new_price_cents'], 'updated_at' => now()]);
                }
                if ($scope === 'global') {
                    DB::table('pricing_settings')->where('id', 1)->update(['global_markup_decimal' => $review['new_markup'], 'updated_at' => now()]);
                } else {
                    DB::table('product_variants')->where('id', $variantId)->update(['markup_override_decimal' => $review['new_markup'], 'updated_at' => now()]);
                }

                return $eventId;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $previous = DB::table('price_change_events')->where('idempotency_key', $idempotencyKey)->first();
            if ($previous === null) {
                throw $exception;
            }

            return $this->previousEventId($previous, $scope, $variantId, $markupInput, $expectedFingerprint);
        }
    }

    private function normalizeMarkup(string $input, bool $allowInherit): ?string
    {
        $normalized = str_replace(',', '.', trim($input));
        if ($allowInherit && $normalized === '') {
            return null;
        }
        if (preg_match('/\A(?:0|[1-9][0-9]{0,4})(?:\.[0-9]{1,2})?\z/D', $normalized) !== 1) {
            throw new InvalidArgumentException('Ingresá un recargo no negativo con hasta dos decimales.');
        }
        Percentage::fromDecimal($normalized);

        return str_contains($normalized, '.') ? rtrim(rtrim($normalized, '0'), '.') : $normalized;
    }

    private function previousEventId(object $previous, string $scope, ?int $variantId, string $markupInput, string $expectedFingerprint): int
    {
        if ($previous->request_fingerprint !== $expectedFingerprint || $previous->scope !== $scope || ($previous->product_variant_id === null ? null : (int) $previous->product_variant_id) !== ($scope === 'variant' ? $variantId : null) || $previous->new_markup_decimal !== $this->normalizeMarkup($markupInput, $scope === 'variant')) {
            throw new InvalidArgumentException('La clave de confirmación ya se usó con otro cambio.');
        }

        return (int) $previous->id;
    }
}
