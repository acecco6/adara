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
        Schema::create('product_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_group_id')->constrained()->restrictOnDelete();
            $table->string('sku')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('minimum_stock')->default(0);
            $table->bigInteger('current_unit_cost_10000')->nullable();
            $table->string('markup_override_decimal')->nullable();
            $table->bigInteger('current_price_cents')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_balances', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('quantity_on_hand')->default(0);
            $table->timestamp('updated_at')->useCurrent();
        });

        Schema::create('stock_operations', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key')->unique();
            $table->char('request_fingerprint', 64);
            $table->string('kind');
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['occurred_at', 'id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_operation_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('line_position');
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->bigInteger('quantity_delta');
            $table->unsignedBigInteger('quantity_after');
            $table->bigInteger('unit_cost_10000')->nullable();
            $table->foreignId('reversal_of_movement_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['stock_operation_id', 'line_position']);
            $table->index(['product_variant_id', 'id']);
        });

        DB::statement("CREATE TRIGGER product_variants_valid_insert BEFORE INSERT ON product_variants WHEN trim(NEW.sku) = '' OR trim(NEW.name) = '' OR typeof(NEW.minimum_stock) <> 'integer' OR NEW.minimum_stock < 0 OR NEW.is_active NOT IN (0, 1) OR (NEW.current_unit_cost_10000 IS NOT NULL AND (typeof(NEW.current_unit_cost_10000) <> 'integer' OR NEW.current_unit_cost_10000 < 0)) OR (NEW.current_price_cents IS NOT NULL AND (typeof(NEW.current_price_cents) <> 'integer' OR NEW.current_price_cents < 0)) BEGIN SELECT RAISE(ABORT, 'invalid product variant'); END");
        DB::statement("CREATE TRIGGER product_variants_valid_update BEFORE UPDATE ON product_variants WHEN trim(NEW.sku) = '' OR trim(NEW.name) = '' OR typeof(NEW.minimum_stock) <> 'integer' OR NEW.minimum_stock < 0 OR NEW.is_active NOT IN (0, 1) OR (NEW.current_unit_cost_10000 IS NOT NULL AND (typeof(NEW.current_unit_cost_10000) <> 'integer' OR NEW.current_unit_cost_10000 < 0)) OR (NEW.current_price_cents IS NOT NULL AND (typeof(NEW.current_price_cents) <> 'integer' OR NEW.current_price_cents < 0)) BEGIN SELECT RAISE(ABORT, 'invalid product variant'); END");
        DB::statement('CREATE TRIGGER product_variants_initial_balance AFTER INSERT ON product_variants BEGIN INSERT INTO stock_balances (product_variant_id, quantity_on_hand, updated_at) VALUES (NEW.id, 0, CURRENT_TIMESTAMP); END');
        DB::statement("CREATE TRIGGER stock_balances_nonnegative_insert BEFORE INSERT ON stock_balances WHEN typeof(NEW.quantity_on_hand) <> 'integer' OR NEW.quantity_on_hand < 0 BEGIN SELECT RAISE(ABORT, 'invalid stock balance'); END");
        DB::statement("CREATE TRIGGER stock_balances_nonnegative_update BEFORE UPDATE ON stock_balances WHEN typeof(NEW.quantity_on_hand) <> 'integer' OR NEW.quantity_on_hand < 0 BEGIN SELECT RAISE(ABORT, 'invalid stock balance'); END");
        DB::statement("CREATE TRIGGER stock_operations_valid_insert BEFORE INSERT ON stock_operations WHEN NEW.kind = 'manual_adjustment' AND (NEW.reason IS NULL OR trim(NEW.reason) = '') BEGIN SELECT RAISE(ABORT, 'manual adjustment requires reason'); END");
        DB::statement("CREATE TRIGGER stock_operations_immutable_update BEFORE UPDATE ON stock_operations BEGIN SELECT RAISE(ABORT, 'stock operations are immutable'); END");
        DB::statement("CREATE TRIGGER stock_operations_immutable_delete BEFORE DELETE ON stock_operations BEGIN SELECT RAISE(ABORT, 'stock operations are immutable'); END");
        DB::statement("CREATE TRIGGER stock_movements_valid_insert BEFORE INSERT ON stock_movements WHEN typeof(NEW.quantity_delta) <> 'integer' OR NEW.quantity_delta = 0 OR typeof(NEW.quantity_after) <> 'integer' OR NEW.quantity_after < 0 OR (NEW.unit_cost_10000 IS NOT NULL AND (typeof(NEW.unit_cost_10000) <> 'integer' OR NEW.unit_cost_10000 < 0)) OR NOT EXISTS (SELECT 1 FROM stock_balances WHERE product_variant_id = NEW.product_variant_id AND quantity_on_hand = NEW.quantity_after) BEGIN SELECT RAISE(ABORT, 'invalid stock movement'); END");
        DB::statement("CREATE TRIGGER stock_movements_immutable_update BEFORE UPDATE ON stock_movements BEGIN SELECT RAISE(ABORT, 'stock movements are immutable'); END");
        DB::statement("CREATE TRIGGER stock_movements_immutable_delete BEFORE DELETE ON stock_movements BEGIN SELECT RAISE(ABORT, 'stock movements are immutable'); END");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS stock_movements_immutable_delete');
        DB::statement('DROP TRIGGER IF EXISTS stock_movements_immutable_update');
        DB::statement('DROP TRIGGER IF EXISTS stock_movements_valid_insert');
        DB::statement('DROP TRIGGER IF EXISTS stock_operations_immutable_delete');
        DB::statement('DROP TRIGGER IF EXISTS stock_operations_immutable_update');
        DB::statement('DROP TRIGGER IF EXISTS stock_operations_valid_insert');
        DB::statement('DROP TRIGGER IF EXISTS stock_balances_nonnegative_update');
        DB::statement('DROP TRIGGER IF EXISTS stock_balances_nonnegative_insert');
        DB::statement('DROP TRIGGER IF EXISTS product_variants_initial_balance');
        DB::statement('DROP TRIGGER IF EXISTS product_variants_valid_update');
        DB::statement('DROP TRIGGER IF EXISTS product_variants_valid_insert');

        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_operations');
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_groups');
    }
};
