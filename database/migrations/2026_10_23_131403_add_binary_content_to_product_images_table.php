<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table): void {
            $table->mediumText('content_base64')->nullable()->change();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE product_images ADD COLUMN content_bytes MEDIUMBLOB NULL');
        } else {
            Schema::table('product_images', function (Blueprint $table): void {
                $table->binary('content_bytes')->nullable();
            });
        }

        DB::table('product_images')->whereNotNull('content_base64')->select(['id', 'content_base64'])
            ->orderBy('id')->chunkById(10, function (Collection $images): void {
                foreach ($images as $image) {
                    $bytes = base64_decode($image->content_base64, true);

                    if ($bytes !== false) {
                        DB::table('product_images')->where('id', $image->id)->update([
                            'content_bytes' => $bytes,
                            'content_base64' => null,
                        ]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('product_images')->whereNotNull('content_bytes')->select(['id', 'content_bytes'])
            ->orderBy('id')->chunkById(10, function (Collection $images): void {
                foreach ($images as $image) {
                    DB::table('product_images')->where('id', $image->id)->update([
                        'content_base64' => base64_encode($image->content_bytes),
                    ]);
                }
            });

        Schema::table('product_images', function (Blueprint $table): void {
            $table->dropColumn('content_bytes');
        });

        Schema::table('product_images', function (Blueprint $table): void {
            $table->mediumText('content_base64')->nullable(false)->change();
        });
    }
};
