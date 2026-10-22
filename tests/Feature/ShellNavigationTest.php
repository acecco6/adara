<?php

namespace Tests\Feature;

use App\Models\ProductGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShellNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_remaining_planned_sections_open_named_pages_with_honest_placeholders(): void
    {
        $this->withoutVite();

        $pages = [
            ['backups.index', [], 'Copias de seguridad'],
            ['help.index', [], 'Ayuda'],
        ];

        foreach ($pages as [$routeName, $parameters, $title]) {
            $this->get(route($routeName, $parameters))
                ->assertOk()
                ->assertSee('<title>'.$title.' · Adara Gestión</title>', false)
                ->assertSee('En preparación')
                ->assertSee('Volver');
        }

        $this->get(route('reports.index'))->assertOk()
            ->assertSee('<title>Reportes · Adara Gestión</title>', false)
            ->assertSee('Reportes y resultados')
            ->assertDontSee('En preparación');

        $this->get(route('settings.index'))->assertOk()
            ->assertSee('<title>Configuración · Adara Gestión</title>', false)
            ->assertSee('Recargo general')
            ->assertDontSee('En preparación');

        $product = ProductGroup::factory()->create();

        $this->get(route('products.index'))->assertOk()->assertSee('<title>Productos · Adara Gestión</title>', false);
        $this->get(route('sales.create'))->assertOk()->assertSee('Buscar por nombre o código')->assertDontSee('En preparación');
        $this->get(route('sales.index'))->assertOk()->assertSee('<title>Historial de ventas · Adara Gestión</title>', false)->assertDontSee('En preparación');
        $this->get(route('products.show', $product))->assertOk()->assertSee('<title>Detalle del producto · Adara Gestión</title>', false);
        $this->get(route('inventory.index'))->assertOk()->assertSee('Existencias por presentación')->assertDontSee('En preparación');
        $this->get(route('categories.index'))->assertOk()->assertSee('Nueva categoría')->assertDontSee('En preparación');
        $this->get(route('suppliers.index'))->assertOk()->assertSee('Nuevo proveedor')->assertDontSee('En preparación');
        $this->get(route('customers.index'))->assertOk()->assertSee('Nueva clienta')->assertDontSee('En preparación');
    }

    public function test_list_and_detail_shells_explain_their_unavailable_content(): void
    {
        $this->withoutVite();

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('No hay productos para mostrar')
            ->assertSee('Agregar producto');

        $product = ProductGroup::factory()->create();

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Últimos movimientos')
            ->assertSee('Volver a productos');
    }

    public function test_sidebar_links_go_to_existing_sections(): void
    {
        $this->withoutVite();

        $response = $this->get(route('home'));

        $response->assertOk();

        foreach (['products.index', 'inventory.index', 'purchases.index', 'purchases.receipts', 'sales.index', 'sales.create', 'payments.index', 'customers.index', 'suppliers.index', 'categories.index', 'reports.index', 'settings.index', 'backups.index', 'help.index'] as $routeName) {
            $response->assertSee('href="'.route($routeName).'"', false);
        }

        $response->assertDontSee('aria-disabled="true"', false);
    }

    public function test_unknown_sections_and_non_numeric_detail_ids_are_not_found(): void
    {
        $this->withoutVite();

        $this->get('/no-existe')->assertNotFound();
        $this->get('/productos/desconocido')->assertNotFound();
        $this->get('/productos/999999')->assertNotFound();
        $this->get('/pedidos/desconocido')->assertNotFound();
    }
}
