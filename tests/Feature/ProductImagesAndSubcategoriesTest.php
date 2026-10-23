<?php

namespace Tests\Feature;

use App\Livewire\Categories\Index as CategoriesIndex;
use App\Livewire\Products\Catalog;
use App\Livewire\Products\Detail;
use App\Models\Category;
use App\Models\ProductGroup;
use App\Models\ProductImage;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class ProductImagesAndSubcategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_subcategory_can_be_created_and_product_assigned_to_it(): void
    {
        $category = Category::factory()->create(['name' => 'Limpieza']);
        Livewire::test(CategoriesIndex::class)
            ->set('subcategoryCategoryId', (string) $category->id)
            ->set('subcategoryName', 'Facial')
            ->call('saveSubcategory')
            ->assertHasNoErrors();
        $subcategory = Subcategory::query()->firstOrFail();
        Livewire::test(Catalog::class)
            ->set('name', 'Jabón')
            ->set('presentation', 'Unidad')
            ->set('categoryId', (string) $category->id)
            ->set('subcategoryId', (string) $subcategory->id)
            ->call('save')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('product_groups', ['name' => 'Jabón', 'category_id' => $category->id, 'subcategory_id' => $subcategory->id]);
    }

    public function test_product_rejects_subcategory_from_a_different_category(): void
    {
        $category = Category::factory()->create();
        $other = Category::factory()->create();
        $subcategory = Subcategory::factory()->for($other)->create();
        Livewire::test(Catalog::class)
            ->set('name', 'Jabón')->set('presentation', 'Unidad')
            ->set('categoryId', (string) $category->id)->set('subcategoryId', (string) $subcategory->id)
            ->call('save')->assertHasErrors('subcategory_id');
        $this->assertDatabaseCount('product_groups', 0);
    }

    public function test_product_gallery_keeps_one_cover_and_serves_image_to_active_user(): void
    {
        $group = ProductGroup::factory()->create();
        Livewire::test(Detail::class, ['groupId' => $group->id])
            ->set('photos', [UploadedFile::fake()->image('portada.png', 1, 1), UploadedFile::fake()->image('otra.png', 1, 1)])
            ->call('savePhotos')->assertHasNoErrors();
        $images = $group->images()->orderBy('position')->get();
        $this->assertCount(2, $images);
        $this->assertTrue($images[0]->is_primary);
        $this->assertFalse($images[1]->is_primary);
        Livewire::test(Detail::class, ['groupId' => $group->id])->call('makePrimaryPhoto', $images[1]->id)->assertHasNoErrors();
        $this->assertSame($images[1]->id, $group->primaryImage()->value('id'));
        auth()->logout();
        $this->get(route('products.images.show', $images[1]))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['is_active' => true]));
        $response = $this->get(route('products.images.show', $images[1]));
        $response->assertOk()->assertHeader('Content-Type', $images[1]->mime_type);
        $this->assertSame($images[1]->content_bytes, $response->getContent());
        Livewire::test(Detail::class, ['groupId' => $group->id])
            ->call('removePhoto')
            ->assertSet('photoToDeleteId', null)
            ->call('openPhotoRemoval', $images[1]->id)
            ->assertSet('photoToDeleteId', $images[1]->id)
            ->call('cancelPhotoRemoval')
            ->assertSet('photoToDeleteId', null)
            ->call('openPhotoRemoval', $images[1]->id)
            ->call('removePhoto')
            ->assertHasNoErrors();
        $this->assertSame($images[0]->id, $group->primaryImage()->value('id'));
    }

    public function test_invalid_image_does_not_create_product(): void
    {
        Livewire::test(Catalog::class)
            ->set('name', 'Jabón')->set('presentation', 'Unidad')
            ->set('photos', [UploadedFile::fake()->create('documento.pdf', 10, 'application/pdf')])
            ->call('save')->assertHasErrors();
        $this->assertDatabaseCount('product_groups', 0);
        $this->assertDatabaseCount('product_images', 0);
    }

    public function test_large_photo_is_reduced_and_saved_as_smaller_webp(): void
    {
        $group = ProductGroup::factory()->create();
        $canvas = imagecreatetruecolor(2400, 1200);

        for ($column = 0; $column < 2400; $column += 20) {
            $color = imagecolorallocate($canvas, $column % 255, 120, 200);
            imagefilledrectangle($canvas, $column, 0, $column + 19, 1199, $color);
        }

        ob_start();
        imagejpeg($canvas, null, 95);
        $original = ob_get_clean();
        imagedestroy($canvas);

        Livewire::test(Detail::class, ['groupId' => $group->id])
            ->set('photos', [UploadedFile::fake()->createWithContent('grande.jpg', $original)])
            ->call('savePhotos')
            ->assertHasNoErrors();

        $image = $group->images()->firstOrFail();
        $saved = $image->content_bytes;
        $dimensions = getimagesizefromstring($saved);

        $this->assertSame('image/webp', $image->mime_type);
        $this->assertNull($image->content_base64);
        $this->assertLessThan(strlen($original), $image->size_bytes);
        $this->assertSame(1600, $dimensions[0]);
        $this->assertSame(800, $dimensions[1]);
    }

    public function test_product_cannot_store_more_than_five_images(): void
    {
        $group = ProductGroup::factory()->create();
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        $photos = [];
        for ($index = 0; $index < 6; $index++) {
            $photos[] = UploadedFile::fake()->createWithContent("foto-{$index}.png", $pixel);
        }

        Livewire::test(Detail::class, ['groupId' => $group->id])
            ->set('photos', $photos)
            ->call('savePhotos')
            ->assertHasErrors('photos');

        $this->assertDatabaseCount('product_images', 0);
    }

    public function test_legacy_base64_image_remains_readable(): void
    {
        $group = ProductGroup::factory()->create();
        $bytes = 'contenido anterior';
        $image = ProductImage::factory()->for($group, 'group')->create([
            'content_bytes' => null,
            'content_base64' => base64_encode($bytes),
            'size_bytes' => strlen($bytes),
        ]);

        $this->get(route('products.images.show', $image))
            ->assertOk()
            ->assertSee($bytes);
    }

    public function test_deleting_photo_does_not_need_image_processor(): void
    {
        $group = ProductGroup::factory()->create();
        $image = ProductImage::factory()->for($group, 'group')->create();
        $this->app->bind(ImageManager::class, static fn (): never => throw new RuntimeException('GD no disponible'));

        Livewire::test(Detail::class, ['groupId' => $group->id])
            ->call('openPhotoRemoval', $image->id)
            ->call('removePhoto')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
    }
}
