<?php

namespace Tests\Feature;

use App\Livewire\Categories\Index as CategoryIndex;
use App\Livewire\Contacts\Directory;
use App\Livewire\Products\Catalog;
use App\Livewire\Products\Detail;
use App\Models\Category;
use App\Models\Customer;
use App\Models\ProductGroup;
use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogReferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_modal_flow_creates_edits_and_archives_without_losing_product_links(): void
    {
        Livewire::test(CategoryIndex::class)
            ->call('startCreating')
            ->set('name', ' Cuidado facial ')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Cuidado facial');

        $category = Category::firstOrFail();
        $group = ProductGroup::factory()->for($category)->create();

        Livewire::test(CategoryIndex::class)
            ->call('startEditing', $category->id)
            ->set('name', 'Rostro')
            ->call('save')
            ->call('toggleActive', $category->id)
            ->assertSee('Archivada');

        $this->assertSame('Rostro', $category->fresh()->name);
        $this->assertFalse($category->fresh()->is_active);
        $this->assertSame($category->id, $group->fresh()->category_id);
    }

    public function test_category_requires_a_unique_name_even_after_archiving(): void
    {
        Category::factory()->create(['name' => 'Perfumes', 'is_active' => false]);
        Category::factory()->create(['name' => 'Ámbar']);

        Livewire::test(CategoryIndex::class)
            ->set('name', ' perfumes ')
            ->call('save')
            ->assertHasErrors('name');

        Livewire::test(CategoryIndex::class)
            ->set('name', 'ámbar')
            ->call('save')
            ->assertHasErrors('name');

        $this->assertDatabaseCount('categories', 2);
    }

    public function test_supplier_and_customer_forms_validate_edit_and_archive_contacts(): void
    {
        Livewire::test(Directory::class, ['kind' => 'supplier'])
            ->set('name', ' Distribuidora Norte ')
            ->set('email', 'incorrecto')
            ->call('save')
            ->assertHasErrors('email')
            ->set('email', 'ventas@example.test')
            ->call('save')
            ->assertHasNoErrors();

        $supplier = Supplier::firstOrFail();

        Livewire::test(Directory::class, ['kind' => 'supplier'])
            ->call('startEditing', $supplier->id)
            ->set('phone', '011 5555 1234')
            ->call('save')
            ->call('toggleActive', $supplier->id)
            ->assertSee('Archivado');

        Livewire::test(Directory::class, ['kind' => 'customer'])
            ->set('name', ' Clienta Ejemplo ')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Clienta Ejemplo');

        $customer = Customer::firstOrFail();
        Livewire::test(Directory::class, ['kind' => 'customer'])
            ->call('startEditing', $customer->id)
            ->set('phone', '11 4444 2222')
            ->call('save')
            ->call('toggleActive', $customer->id)
            ->assertSee('Archivado');

        $this->assertSame('011 5555 1234', $supplier->fresh()->phone);
        $this->assertFalse($supplier->fresh()->is_active);
        $this->assertDatabaseHas('customers', ['name' => 'Clienta Ejemplo', 'email' => null]);
        $this->assertSame('11 4444 2222', $customer->fresh()->phone);
        $this->assertFalse($customer->fresh()->is_active);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_product_can_choose_category_and_multiple_suppliers_then_change_them(): void
    {
        $category = Category::factory()->create();
        $otherCategory = Category::factory()->create();
        $firstSupplier = Supplier::factory()->create();
        $secondSupplier = Supplier::factory()->create();

        Livewire::test(Catalog::class)
            ->set('name', 'Crema ejemplo')
            ->set('presentation', '50 ml')
            ->set('categoryId', (string) $category->id)
            ->set('supplierIds', [$firstSupplier->id, $secondSupplier->id])
            ->call('save')
            ->assertHasNoErrors();

        $group = ProductGroup::firstOrFail();
        $this->assertSame($category->id, $group->category_id);
        $this->assertEqualsCanonicalizing([$firstSupplier->id, $secondSupplier->id], $group->suppliers->modelKeys());

        Livewire::test(Detail::class, ['groupId' => $group->id])
            ->set('categoryId', (string) $otherCategory->id)
            ->set('supplierIds', [$secondSupplier->id])
            ->call('saveGroup')
            ->assertHasNoErrors();

        $this->assertSame($otherCategory->id, $group->fresh()->category_id);
        $this->assertSame([$secondSupplier->id], $group->fresh()->suppliers->modelKeys());
        $this->assertDatabaseCount('product_group_supplier', 1);

        $otherCategory->update(['is_active' => false]);
        $secondSupplier->update(['is_active' => false]);

        Livewire::test(Detail::class, ['groupId' => $group->id])
            ->set('name', 'Crema renovada')
            ->call('saveGroup')
            ->assertHasNoErrors();

        $this->assertSame($otherCategory->id, $group->fresh()->category_id);
        $this->assertSame([$secondSupplier->id], $group->fresh()->suppliers->modelKeys());
    }

    public function test_invalid_or_archived_relations_are_rejected_without_partial_product_changes(): void
    {
        $archivedCategory = Category::factory()->create(['is_active' => false]);
        $archivedSupplier = Supplier::factory()->create(['is_active' => false]);

        Livewire::test(Catalog::class)
            ->set('name', 'Producto inválido')
            ->set('presentation', 'Unidad')
            ->set('categoryId', (string) $archivedCategory->id)
            ->call('save')
            ->assertHasErrors('category_id');

        Livewire::test(Catalog::class)
            ->set('name', 'Producto inválido')
            ->set('presentation', 'Unidad')
            ->set('categoryId', '0')
            ->call('save')
            ->assertHasErrors('category_id');

        Livewire::test(Catalog::class)
            ->set('name', 'Producto inválido')
            ->set('presentation', 'Unidad')
            ->set('supplierIds', [$archivedSupplier->id])
            ->call('save')
            ->assertHasErrors('supplier_ids');

        Livewire::test(Catalog::class)
            ->set('name', 'Producto inválido')
            ->set('presentation', 'Unidad')
            ->set('supplierIds', [999999])
            ->call('save')
            ->assertHasErrors('supplier_ids.0');

        $this->assertDatabaseCount('product_groups', 0);
        $this->assertDatabaseCount('product_variants', 0);
    }

    public function test_supplier_link_has_foreign_keys_and_no_duplicate_pairs(): void
    {
        $group = ProductGroup::factory()->create();
        $supplier = Supplier::factory()->create();
        $group->suppliers()->attach($supplier);

        try {
            $group->suppliers()->attach($supplier);
            $this->fail('El mismo proveedor no debería vincularse dos veces.');
        } catch (QueryException) {
            $this->assertDatabaseCount('product_group_supplier', 1);
        }

        $this->expectException(QueryException::class);
        DB::table('product_group_supplier')->insert(['product_group_id' => $group->id, 'supplier_id' => 999999]);
    }

    public function test_contact_pages_show_real_empty_states(): void
    {
        $this->withoutVite();

        $this->get(route('categories.index'))->assertOk()->assertSee('No hay categorías para mostrar');
        $this->get(route('suppliers.index'))->assertOk()->assertSee('No hay proveedores para mostrar');
        $this->get(route('customers.index'))->assertOk()->assertSee('No hay clientas para mostrar');
    }
}
