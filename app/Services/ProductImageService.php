<?php

namespace App\Services;

use App\Models\ProductGroup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Exceptions\ImageException;
use Intervention\Image\Format;
use Intervention\Image\Laravel\Facades\Image;

class ProductImageService
{
    public const MAX_IMAGES = 5;

    private const MAX_DIMENSION = 1600;

    private const WEBP_QUALITY = 80;

    /** @param array<int, UploadedFile> $photos */
    public function add(int $groupId, array $photos): void
    {
        if ($photos === []) {
            return;
        }

        Validator::make(['photos' => $photos], [
            'photos' => ['array', 'max:'.self::MAX_IMAGES],
            'photos.*' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
        ], [
            'photos.max' => 'Podés guardar hasta cinco fotos por producto.',
            'photos.*.image' => 'Elegí una imagen válida.',
            'photos.*.mimes' => 'Usá imágenes JPG, PNG o WebP.',
            'photos.*.max' => 'Cada foto puede pesar hasta 2 MB.',
        ])->validate();

        DB::transaction(function () use ($groupId, $photos): void {
            $group = ProductGroup::query()->lockForUpdate()->findOrFail($groupId);
            $existingCount = $group->images()->count();

            if ($existingCount + count($photos) > self::MAX_IMAGES) {
                throw ValidationException::withMessages(['photos' => 'Podés guardar hasta cinco fotos por producto.']);
            }

            $position = (int) $group->images()->max('position');

            foreach ($photos as $photo) {
                $bytes = $photo->get();
                $details = is_string($bytes) ? @getimagesizefromstring($bytes) : false;
                $mimeType = is_array($details) ? ($details['mime'] ?? '') : '';

                if (! is_string($bytes) || strlen($bytes) > 2 * 1024 * 1024 || ! in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                    throw ValidationException::withMessages(['photos' => 'Elegí fotos JPG, PNG o WebP de hasta 2 MB.']);
                }

                try {
                    $optimized = (string) Image::decodeBinary($bytes)
                        ->scaleDown(width: self::MAX_DIMENSION, height: self::MAX_DIMENSION)
                        ->encodeUsingFormat(Format::WEBP, quality: self::WEBP_QUALITY, strip: true);
                } catch (ImageException $exception) {
                    throw ValidationException::withMessages(['photos' => 'No se pudo procesar una foto. Probá con otra imagen JPG, PNG o WebP.']);
                }

                if (strlen($optimized) < strlen($bytes)) {
                    $bytes = $optimized;
                    $mimeType = 'image/webp';
                }

                $group->images()->create([
                    'mime_type' => $mimeType,
                    'content_bytes' => $bytes,
                    'size_bytes' => strlen($bytes),
                    'position' => ++$position,
                    'is_primary' => $existingCount === 0 && $position === 1,
                ]);
            }
        });
    }

    public function makePrimary(int $groupId, int $imageId): void
    {
        DB::transaction(function () use ($groupId, $imageId): void {
            $group = ProductGroup::query()->lockForUpdate()->findOrFail($groupId);
            $image = $group->images()->findOrFail($imageId);

            $group->images()->where('is_primary', true)->update(['is_primary' => false]);
            $image->update(['is_primary' => true]);
        });
    }

    public function remove(int $groupId, int $imageId): void
    {
        DB::transaction(function () use ($groupId, $imageId): void {
            $group = ProductGroup::query()->lockForUpdate()->findOrFail($groupId);
            $image = $group->images()->findOrFail($imageId);
            $wasPrimary = $image->is_primary;
            $image->delete();

            if ($wasPrimary) {
                $next = $group->images()->orderBy('position')->orderBy('id')->first();
                $next?->update(['is_primary' => true]);
            }
        });
    }
}
