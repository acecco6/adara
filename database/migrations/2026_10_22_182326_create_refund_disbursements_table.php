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
        Schema::create('refund_disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_cancellation_id')->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 128)->unique();
            $table->string('request_fingerprint', 64);
            $table->string('method', 24);
            $table->unsignedBigInteger('amount_cents');
            $table->timestamp('paid_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['sale_cancellation_id', 'paid_at', 'id']);
        });
        DB::statement("CREATE TRIGGER refund_disbursements_valid_insert BEFORE INSERT ON refund_disbursements WHEN NEW.method NOT IN ('cash', 'transfer', 'mercado_pago', 'card') OR typeof(NEW.amount_cents) <> 'integer' OR NEW.amount_cents <= 0 OR NEW.amount_cents + COALESCE((SELECT SUM(amount_cents) FROM refund_disbursements WHERE sale_cancellation_id = NEW.sale_cancellation_id), 0) > (SELECT refund_owed_cents FROM sale_cancellations WHERE id = NEW.sale_cancellation_id) OR NEW.paid_at < (SELECT cancelled_at FROM sale_cancellations WHERE id = NEW.sale_cancellation_id) BEGIN SELECT RAISE(ABORT, 'invalid refund disbursement'); END");
        DB::statement("CREATE TRIGGER refund_disbursements_immutable_update BEFORE UPDATE ON refund_disbursements BEGIN SELECT RAISE(ABORT, 'refund disbursement is immutable'); END");
        DB::statement("CREATE TRIGGER refund_disbursements_immutable_delete BEFORE DELETE ON refund_disbursements BEGIN SELECT RAISE(ABORT, 'refund disbursement is immutable'); END");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS refund_disbursements_immutable_delete');
        DB::statement('DROP TRIGGER IF EXISTS refund_disbursements_immutable_update');
        DB::statement('DROP TRIGGER IF EXISTS refund_disbursements_valid_insert');
        Schema::dropIfExists('refund_disbursements');
    }
};
