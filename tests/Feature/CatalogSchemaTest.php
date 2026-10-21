<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\ProductGroup;
use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_can_group_products_without_creating_stock_or_prices(): void
    {
        $category = Category::factory()->create();
        $group = ProductGroup::factory()->for($category)->create();

        $this->assertSame($category->id, $group->category->id);
        $this->assertSame($group->id, $category->groups()->firstOrFail()->id);
        $this->assertDatabaseHas('product_groups', ['id' => $group->id, 'category_id' => $category->id]);
        $this->assertDatabaseCount('stock_balances', 0);
    }

    public function test_category_in_use_cannot_be_deleted(): void
    {
        $category = Category::factory()->create();
        $group = ProductGroup::factory()->for($category)->create();

        try {
            $category->delete();
            $this->fail('Una categoría con productos no debería eliminarse.');
        } catch (QueryException) {
            $this->assertModelExists($category);
            $this->assertModelExists($group);
        }
    }

    public function test_customer_and_supplier_factories_create_optional_contacts_without_commercial_activity(): void
    {
        $customer = Customer::factory()->create();
        $supplier = Supplier::factory()->create();

        $this->assertModelExists($customer);
        $this->assertModelExists($supplier);
        $this->assertNull($customer->phone);
        $this->assertNull($supplier->email);
        $this->assertDatabaseCount('stock_operations', 0);
    }

    public function test_product_group_rejects_unknown_category(): void
    {
        $this->expectException(QueryException::class);

        DB::table('product_groups')->insert([
            'name' => 'Grupo sin categoría válida',
            'category_id' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
