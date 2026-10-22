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
        Schema::create('sale_cancellations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('stock_operation_id')->unique()->constrained()->restrictOnDelete();
            $table->string('reason', 500);
            $table->unsignedBigInteger('debt_relieved_cents');
            $table->unsignedBigInteger('refund_owed_cents');
            $table->timestamp('cancelled_at');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('sale_cancellation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_cancellation_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_item_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('quantity_usable');
            $table->unsignedBigInteger('quantity_unusable');
            $table->string('unusable_reason', 500)->nullable();
        });

        PortableTrigger::create("CREATE TRIGGER sale_cancellations_immutable_update BEFORE UPDATE ON sale_cancellations BEGIN SELECT RAISE(ABORT, 'sale cancellation is immutable'); END");
        PortableTrigger::create("CREATE TRIGGER sale_cancellations_immutable_delete BEFORE DELETE ON sale_cancellations BEGIN SELECT RAISE(ABORT, 'sale cancellation is immutable'); END");
        PortableTrigger::create("CREATE TRIGGER sale_cancellation_items_immutable_update BEFORE UPDATE ON sale_cancellation_items BEGIN SELECT RAISE(ABORT, 'sale cancellation items are immutable'); END");
        PortableTrigger::create("CREATE TRIGGER sale_cancellation_items_immutable_delete BEFORE DELETE ON sale_cancellation_items BEGIN SELECT RAISE(ABORT, 'sale cancellation items are immutable'); END");
        PortableTrigger::create("CREATE TRIGGER sales_status_transition BEFORE UPDATE OF status ON sales WHEN OLD.status <> 'confirmed' OR NEW.status <> 'cancelled' BEGIN SELECT RAISE(ABORT, 'invalid sale status transition'); END");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS sales_status_transition');
        foreach (['sale_cancellation_items', 'sale_cancellations'] as $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$table}_immutable_update");
            DB::statement("DROP TRIGGER IF EXISTS {$table}_immutable_delete");
        }
        Schema::dropIfExists('sale_cancellation_items');
        Schema::dropIfExists('sale_cancellations');
    }
};
