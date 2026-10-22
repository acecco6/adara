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
        Schema::create('business_settings', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('business_name', 120);
            $table->string('phone', 80)->nullable();
            $table->string('address', 200)->nullable();
            $table->timestamps();
        });
        DB::table('business_settings')->insert(['id' => 1, 'business_name' => 'Adara Gestión', 'created_at' => now(), 'updated_at' => now()]);

        Schema::create('price_change_events', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 128)->unique();
            $table->string('request_fingerprint', 64);
            $table->string('scope', 16);
            $table->foreignId('product_variant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('previous_markup_decimal')->nullable();
            $table->string('new_markup_decimal')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('price_change_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_change_event_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('unit_cost_snapshot_10000');
            $table->unsignedBigInteger('previous_price_cents')->nullable();
            $table->unsignedBigInteger('new_price_cents');
            $table->string('previous_markup_decimal');
            $table->string('new_markup_decimal');
            $table->unique(['price_change_event_id', 'product_variant_id']);
        });
        foreach (['price_change_events', 'price_change_lines'] as $table) {
            DB::statement("CREATE TRIGGER {$table}_immutable_update BEFORE UPDATE ON {$table} BEGIN SELECT RAISE(ABORT, 'price history is immutable'); END");
            DB::statement("CREATE TRIGGER {$table}_immutable_delete BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT, 'price history is immutable'); END");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['price_change_lines', 'price_change_events'] as $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$table}_immutable_update");
            DB::statement("DROP TRIGGER IF EXISTS {$table}_immutable_delete");
        }
        Schema::dropIfExists('price_change_lines');
        Schema::dropIfExists('price_change_events');
        Schema::dropIfExists('business_settings');
    }
};
