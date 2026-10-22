<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_quantity_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_receipt_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('extra_quantity');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['purchase_receipt_id', 'purchase_order_line_id']);
        });

        Schema::create('purchase_order_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->unique()->constrained()->restrictOnDelete();
            $table->text('reason');
            $table->unsignedBigInteger('unassigned_general_shipping_cents');
            $table->timestamp('closed_at');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('purchase_order_canceled_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_closure_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('quantity_canceled');
            $table->unique(['purchase_order_closure_id', 'purchase_order_line_id']);
        });

        DB::statement("CREATE TRIGGER purchase_order_quantity_extensions_valid BEFORE INSERT ON purchase_order_quantity_extensions WHEN typeof(NEW.extra_quantity) <> 'integer' OR NEW.extra_quantity <= 0 BEGIN SELECT RAISE(ABORT, 'invalid extension quantity'); END");
        DB::statement("CREATE TRIGGER purchase_order_closures_valid BEFORE INSERT ON purchase_order_closures WHEN trim(NEW.reason) = '' OR typeof(NEW.unassigned_general_shipping_cents) <> 'integer' OR NEW.unassigned_general_shipping_cents < 0 BEGIN SELECT RAISE(ABORT, 'invalid purchase closure'); END");
        DB::statement("CREATE TRIGGER purchase_order_canceled_lines_valid BEFORE INSERT ON purchase_order_canceled_lines WHEN typeof(NEW.quantity_canceled) <> 'integer' OR NEW.quantity_canceled <= 0 BEGIN SELECT RAISE(ABORT, 'invalid canceled quantity'); END");

        foreach (['purchase_order_quantity_extensions', 'purchase_order_closures', 'purchase_order_canceled_lines'] as $table) {
            DB::statement("CREATE TRIGGER {$table}_immutable_update BEFORE UPDATE ON {$table} BEGIN SELECT RAISE(ABORT, 'purchase history is immutable'); END");
            DB::statement("CREATE TRIGGER {$table}_immutable_delete BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT, 'purchase history is immutable'); END");
        }
    }

    public function down(): void
    {
        foreach (['purchase_order_canceled_lines', 'purchase_order_closures', 'purchase_order_quantity_extensions'] as $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$table}_immutable_delete");
            DB::statement("DROP TRIGGER IF EXISTS {$table}_immutable_update");
        }

        DB::statement('DROP TRIGGER IF EXISTS purchase_order_canceled_lines_valid');
        DB::statement('DROP TRIGGER IF EXISTS purchase_order_closures_valid');
        DB::statement('DROP TRIGGER IF EXISTS purchase_order_quantity_extensions_valid');
        Schema::dropIfExists('purchase_order_canceled_lines');
        Schema::dropIfExists('purchase_order_closures');
        Schema::dropIfExists('purchase_order_quantity_extensions');
    }
};
