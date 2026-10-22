<?php

use App\Support\Database\PortableTrigger;
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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->index()->constrained()->restrictOnDelete();
            $table->string('number', 40)->nullable()->unique();
            $table->string('supplier_name_snapshot')->nullable();
            $table->string('status', 32)->default('draft');
            $table->string('shipping_method', 32);
            $table->unsignedBigInteger('general_shipping_cents')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'id']);
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->index()->constrained()->restrictOnDelete();
            $table->unsignedInteger('line_position');
            $table->unsignedBigInteger('quantity_ordered');
            $table->unsignedBigInteger('unit_paid_cents');
            $table->string('sku_snapshot', 80)->nullable();
            $table->string('presentation_snapshot')->nullable();
            $table->string('product_name_snapshot')->nullable();
            $table->timestamps();
            $table->unique(['purchase_order_id', 'line_position']);
        });

        PortableTrigger::create("CREATE TRIGGER purchase_orders_money_insert BEFORE INSERT ON purchase_orders WHEN typeof(NEW.general_shipping_cents) <> 'integer' OR NEW.general_shipping_cents < 0 BEGIN SELECT RAISE(ABORT, 'invalid purchase shipping'); END");
        PortableTrigger::create("CREATE TRIGGER purchase_orders_money_update BEFORE UPDATE ON purchase_orders WHEN typeof(NEW.general_shipping_cents) <> 'integer' OR NEW.general_shipping_cents < 0 BEGIN SELECT RAISE(ABORT, 'invalid purchase shipping'); END");
        PortableTrigger::create("CREATE TRIGGER purchase_order_lines_values_insert BEFORE INSERT ON purchase_order_lines WHEN typeof(NEW.quantity_ordered) <> 'integer' OR NEW.quantity_ordered <= 0 OR typeof(NEW.unit_paid_cents) <> 'integer' OR NEW.unit_paid_cents < 0 BEGIN SELECT RAISE(ABORT, 'invalid purchase line'); END");
        PortableTrigger::create("CREATE TRIGGER purchase_order_lines_values_update BEFORE UPDATE ON purchase_order_lines WHEN typeof(NEW.quantity_ordered) <> 'integer' OR NEW.quantity_ordered <= 0 OR typeof(NEW.unit_paid_cents) <> 'integer' OR NEW.unit_paid_cents < 0 BEGIN SELECT RAISE(ABORT, 'invalid purchase line'); END");
        PortableTrigger::create("CREATE TRIGGER purchase_order_lines_confirmed_insert BEFORE INSERT ON purchase_order_lines WHEN (SELECT status FROM purchase_orders WHERE id = NEW.purchase_order_id) <> 'draft' BEGIN SELECT RAISE(ABORT, 'confirmed purchase line is immutable'); END");
        PortableTrigger::create("CREATE TRIGGER purchase_order_lines_confirmed_update BEFORE UPDATE ON purchase_order_lines WHEN (SELECT status FROM purchase_orders WHERE id = OLD.purchase_order_id) <> 'draft' OR (SELECT status FROM purchase_orders WHERE id = NEW.purchase_order_id) <> 'draft' BEGIN SELECT RAISE(ABORT, 'confirmed purchase line is immutable'); END");
        PortableTrigger::create("CREATE TRIGGER purchase_order_lines_confirmed_delete BEFORE DELETE ON purchase_order_lines WHEN (SELECT status FROM purchase_orders WHERE id = OLD.purchase_order_id) <> 'draft' BEGIN SELECT RAISE(ABORT, 'confirmed purchase line is immutable'); END");
        PortableTrigger::create("CREATE TRIGGER purchase_orders_confirmed_immutable BEFORE UPDATE ON purchase_orders WHEN OLD.status <> 'draft' AND (NEW.supplier_id IS NOT OLD.supplier_id OR NEW.number IS NOT OLD.number OR NEW.supplier_name_snapshot IS NOT OLD.supplier_name_snapshot OR NEW.shipping_method IS NOT OLD.shipping_method OR NEW.general_shipping_cents IS NOT OLD.general_shipping_cents OR NEW.notes IS NOT OLD.notes OR NEW.confirmed_at IS NOT OLD.confirmed_at OR NEW.status = 'draft') BEGIN SELECT RAISE(ABORT, 'confirmed purchase order fields are immutable'); END");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS purchase_orders_confirmed_immutable');
        DB::statement('DROP TRIGGER IF EXISTS purchase_order_lines_confirmed_delete');
        DB::statement('DROP TRIGGER IF EXISTS purchase_order_lines_confirmed_update');
        DB::statement('DROP TRIGGER IF EXISTS purchase_order_lines_confirmed_insert');
        DB::statement('DROP TRIGGER IF EXISTS purchase_order_lines_values_update');
        DB::statement('DROP TRIGGER IF EXISTS purchase_order_lines_values_insert');
        DB::statement('DROP TRIGGER IF EXISTS purchase_orders_money_update');
        DB::statement('DROP TRIGGER IF EXISTS purchase_orders_money_insert');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
    }
};
