<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subcategories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['category_id', 'name']);
            $table->index(['category_id', 'is_active', 'name']);
        });

        Schema::table('product_groups', function (Blueprint $table): void {
            $table->foreignId('subcategory_id')->nullable()->constrained()->restrictOnDelete();
        });

        Schema::create('product_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_group_id')->constrained()->cascadeOnDelete();
            $table->string('mime_type', 32);
            $table->mediumText('content_base64');
            $table->unsignedInteger('size_bytes');
            $table->unsignedSmallInteger('position');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['product_group_id', 'is_primary', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_images');

        Schema::table('product_groups', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('subcategory_id');
        });

        Schema::dropIfExists('subcategories');
    }
};
