<?php

namespace App\Http\Controllers;

use App\Models\ProductImage;
use Illuminate\Http\Response;

class ProductImageController extends Controller
{
    public function showPublic(ProductImage $image): Response
    {
        abort_unless($image->is_primary && $image->group()->whereHas('variants', fn ($variants) => $variants->where('is_active', true))->exists(), 404);

        return $this->imageResponseFor($image, 'private, max-age=300');
    }

    public function show(ProductImage $image): Response
    {
        return $this->imageResponseFor($image, 'private, max-age=3600');
    }

    private function imageResponseFor(ProductImage $image, string $cacheControl): Response
    {
        $bytes = $image->content_bytes ?? base64_decode($image->content_base64 ?? '', true);
        abort_if(! is_string($bytes) || $bytes === '', 404);

        return response($bytes, 200, [
            'Content-Type' => $image->mime_type,
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => $cacheControl,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
