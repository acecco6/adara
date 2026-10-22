<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_operation_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('number', 40)->nullable()->unique();
            $table->string('status', 24)->default('confirmed');
            $table->string('customer_name_snapshot')->nullable();
            $table->unsignedBigInteger('subtotal_cents');
            $table->string('discount_type', 20);
            $table->string('discount_value_decimal');
            $table->unsignedBigInteger('discount_base_cents');
            $table->unsignedBigInteger('discount_cents');
            $table->unsignedBigInteger('total_cents');
            $table->unsignedBigInteger('cost_total_snapshot_10000');
            $table->unsignedBigInteger('cost_total_snapshot_cents');
            $table->unsignedBigInteger('initial_paid_cents');
            $table->unsignedBigInteger('initial_balance_cents');
            $table->boolean('below_cost_accepted')->default(false);
            $table->timestamp('confirmed_at');
            $table->timestamps();
            $table->index(['confirmed_at', 'id']);
            $table->index(['customer_id', 'confirmed_at', 'id']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('line_position');
            $table->unsignedBigInteger('quantity');
            $table->string('sku_snapshot', 80);
            $table->string('presentation_snapshot');
            $table->string('product_name_snapshot');
            $table->unsignedBigInteger('unit_price_snapshot_cents');
            $table->unsignedBigInteger('gross_cents');
            $table->unsignedBigInteger('discount_base_per_unit_cents');
            $table->unsignedBigInteger('discount_cents');
            $table->unsignedBigInteger('net_cents');
            $table->unsignedBigInteger('unit_cost_snapshot_10000');
            $table->unsignedBigInteger('cost_total_snapshot_10000');
            $table->unsignedBigInteger('cost_total_snapshot_cents');
            $table->unique(['sale_id', 'line_position']);
        });

        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('origin_sale_id')->nullable()->constrained('sales')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('method', 24);
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('commission_cents');
            $table->unsignedBigInteger('cash_tendered_cents')->nullable();
            $table->unsignedBigInteger('cash_change_cents')->nullable();
            $table->timestamp('paid_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['customer_id', 'paid_at', 'id']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->unique(['customer_payment_id', 'sale_id']);
            $table->index(['sale_id', 'id']);
        });

        DB::statement("CREATE TRIGGER sales_valid_insert BEFORE INSERT ON sales WHEN typeof(NEW.subtotal_cents) <> 'integer' OR NEW.subtotal_cents <= 0 OR NEW.discount_type NOT IN ('none', 'fixed', 'percentage') OR NEW.discount_cents > NEW.subtotal_cents OR NEW.total_cents <> NEW.subtotal_cents - NEW.discount_cents OR NEW.initial_paid_cents > NEW.total_cents OR NEW.initial_balance_cents <> NEW.total_cents - NEW.initial_paid_cents OR (NEW.initial_balance_cents > 0 AND NEW.customer_id IS NULL) BEGIN SELECT RAISE(ABORT, 'invalid sale'); END");
        DB::statement("CREATE TRIGGER sales_immutable_update BEFORE UPDATE ON sales WHEN NEW.stock_operation_id IS NOT OLD.stock_operation_id OR NEW.customer_id IS NOT OLD.customer_id OR (OLD.number IS NOT NULL AND NEW.number IS NOT OLD.number) OR NEW.subtotal_cents IS NOT OLD.subtotal_cents OR NEW.discount_type IS NOT OLD.discount_type OR NEW.discount_value_decimal IS NOT OLD.discount_value_decimal OR NEW.discount_base_cents IS NOT OLD.discount_base_cents OR NEW.discount_cents IS NOT OLD.discount_cents OR NEW.total_cents IS NOT OLD.total_cents OR NEW.cost_total_snapshot_10000 IS NOT OLD.cost_total_snapshot_10000 OR NEW.cost_total_snapshot_cents IS NOT OLD.cost_total_snapshot_cents OR NEW.initial_paid_cents IS NOT OLD.initial_paid_cents OR NEW.initial_balance_cents IS NOT OLD.initial_balance_cents OR NEW.below_cost_accepted IS NOT OLD.below_cost_accepted OR NEW.confirmed_at IS NOT OLD.confirmed_at BEGIN SELECT RAISE(ABORT, 'sale snapshot is immutable'); END");
        DB::statement("CREATE TRIGGER sale_items_valid_insert BEFORE INSERT ON sale_items WHEN typeof(NEW.quantity) <> 'integer' OR NEW.quantity <= 0 OR typeof(NEW.unit_price_snapshot_cents) <> 'integer' OR NEW.unit_price_snapshot_cents <= 0 OR NEW.gross_cents <> NEW.quantity * NEW.unit_price_snapshot_cents OR NEW.discount_cents > NEW.gross_cents OR NEW.net_cents <> NEW.gross_cents - NEW.discount_cents BEGIN SELECT RAISE(ABORT, 'invalid sale item'); END");
        DB::statement("CREATE TRIGGER customer_payments_valid_insert BEFORE INSERT ON customer_payments WHEN NEW.method NOT IN ('cash', 'transfer', 'mercado_pago', 'card') OR NEW.amount_cents <= 0 OR NEW.commission_cents < 0 OR (NEW.method = 'cash' AND (NEW.cash_tendered_cents IS NULL OR NEW.cash_change_cents IS NULL OR NEW.cash_tendered_cents <> NEW.amount_cents + NEW.cash_change_cents)) OR (NEW.method <> 'cash' AND (NEW.cash_tendered_cents IS NOT NULL OR NEW.cash_change_cents IS NOT NULL)) BEGIN SELECT RAISE(ABORT, 'invalid customer payment'); END");
        DB::statement("CREATE TRIGGER payment_allocations_valid_insert BEFORE INSERT ON payment_allocations WHEN NEW.amount_cents <= 0 OR NEW.amount_cents > (SELECT amount_cents FROM customer_payments WHERE id = NEW.customer_payment_id) BEGIN SELECT RAISE(ABORT, 'invalid payment allocation'); END");

        foreach (['sale_items', 'customer_payments', 'payment_allocations'] as $table) {
            DB::statement("CREATE TRIGGER {$table}_immutable_update BEFORE UPDATE ON {$table} BEGIN SELECT RAISE(ABORT, 'confirmed sale history is immutable'); END");
            DB::statement("CREATE TRIGGER {$table}_immutable_delete BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT, 'confirmed sale history is immutable'); END");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['payment_allocations', 'customer_payments', 'sale_items'] as $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$table}_immutable_delete");
            DB::statement("DROP TRIGGER IF EXISTS {$table}_immutable_update");
        }

        DB::statement('DROP TRIGGER IF EXISTS payment_allocations_valid_insert');
        DB::statement('DROP TRIGGER IF EXISTS customer_payments_valid_insert');
        DB::statement('DROP TRIGGER IF EXISTS sale_items_valid_insert');
        DB::statement('DROP TRIGGER IF EXISTS sales_immutable_update');
        DB::statement('DROP TRIGGER IF EXISTS sales_valid_insert');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('customer_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
