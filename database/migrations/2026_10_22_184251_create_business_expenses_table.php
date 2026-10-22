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
        Schema::create('business_expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->string('concept', 160);
            $table->string('category', 100);
            $table->unsignedBigInteger('amount_cents');
            $table->date('spent_on');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['spent_on', 'id']);
        });

        DB::statement("CREATE TRIGGER business_expenses_valid_insert BEFORE INSERT ON business_expenses WHEN typeof(NEW.amount_cents) <> 'integer' OR NEW.amount_cents <= 0 BEGIN SELECT RAISE(ABORT, 'invalid business expense'); END");
        DB::statement("CREATE TRIGGER business_expenses_immutable_update BEFORE UPDATE ON business_expenses BEGIN SELECT RAISE(ABORT, 'business expense is immutable'); END");
        DB::statement("CREATE TRIGGER business_expenses_immutable_delete BEFORE DELETE ON business_expenses BEGIN SELECT RAISE(ABORT, 'business expense is immutable'); END");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS business_expenses_immutable_delete');
        DB::statement('DROP TRIGGER IF EXISTS business_expenses_immutable_update');
        DB::statement('DROP TRIGGER IF EXISTS business_expenses_valid_insert');
        Schema::dropIfExists('business_expenses');
    }
};
