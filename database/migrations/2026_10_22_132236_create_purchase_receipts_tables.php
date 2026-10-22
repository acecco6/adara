<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_settings', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('global_markup_decimal')->nullable();
            $table->timestamps();
        });

        DB::table('pricing_settings')->insert(['id' => 1, 'global_markup_decimal' => '80', 'created_at' => now(), 'updated_at' => now()]);

        Schema::create('purchase_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_operation_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('general_shipping_applied_cents');
            $table->unsignedBigInteger('additional_shipping_cents');
            $table->boolean('cost_change_accepted')->default(false);
            $table->timestamp('received_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['purchase_order_id', 'id']);
        });

        Schema::create('purchase_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_receipt_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('line_position');
            $table->unsignedBigInteger('quantity_received');
            $table->unsignedBigInteger('unit_paid_cents');
            $table->unsignedBigInteger('merchandise_paid_cents');
            $table->unsignedBigInteger('general_shipping_applied_cents');
            $table->unsignedBigInteger('additional_shipping_applied_cents');
            $table->unsignedBigInteger('unit_cost_10000');
            $table->unsignedBigInteger('price_cents');
            $table->string('markup_decimal');
            $table->unique(['purchase_receipt_id', 'line_position']);
            $table->unique(['purchase_receipt_id', 'purchase_order_line_id']);
        });

        DB::statement("CREATE TRIGGER purchase_receipts_valid_insert BEFORE INSERT ON purchase_receipts WHEN typeof(NEW.general_shipping_applied_cents) <> 'integer' OR NEW.general_shipping_applied_cents < 0 OR typeof(NEW.additional_shipping_cents) <> 'integer' OR NEW.additional_shipping_cents < 0 BEGIN SELECT RAISE(ABORT, 'invalid purchase receipt'); END");
        DB::statement("CREATE TRIGGER purchase_receipt_lines_valid_insert BEFORE INSERT ON purchase_receipt_lines WHEN typeof(NEW.quantity_received) <> 'integer' OR NEW.quantity_received <= 0 OR typeof(NEW.unit_paid_cents) <> 'integer' OR NEW.unit_paid_cents <= 0 OR typeof(NEW.merchandise_paid_cents) <> 'integer' OR NEW.merchandise_paid_cents <= 0 OR typeof(NEW.general_shipping_applied_cents) <> 'integer' OR NEW.general_shipping_applied_cents < 0 OR typeof(NEW.additional_shipping_applied_cents) <> 'integer' OR NEW.additional_shipping_applied_cents < 0 OR typeof(NEW.unit_cost_10000) <> 'integer' OR NEW.unit_cost_10000 <= 0 OR typeof(NEW.price_cents) <> 'integer' OR NEW.price_cents <= 0 BEGIN SELECT RAISE(ABORT, 'invalid purchase receipt line'); END");

        foreach (['purchase_receipts', 'purchase_receipt_lines'] as $table) {
            DB::statement("CREATE TRIGGER {$table}_immutable_update BEFORE UPDATE ON {$table} BEGIN SELECT RAISE(ABORT, 'confirmed purchase receipts are immutable'); END");
            DB::statement("CREATE TRIGGER {$table}_immutable_delete BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT, 'confirmed purchase receipts are immutable'); END");
        }
    }

    public function down(): void
    {
        foreach (['purchase_receipt_lines', 'purchase_receipts'] as $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$table}_immutable_delete");
            DB::statement("DROP TRIGGER IF EXISTS {$table}_immutable_update");
        }

        DB::statement('DROP TRIGGER IF EXISTS purchase_receipt_lines_valid_insert');
        DB::statement('DROP TRIGGER IF EXISTS purchase_receipts_valid_insert');
        Schema::dropIfExists('purchase_receipt_lines');
        Schema::dropIfExists('purchase_receipts');
        Schema::dropIfExists('pricing_settings');
    }
};
