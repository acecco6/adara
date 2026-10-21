<?php

namespace Tests\Feature;

use Tests\TestCase;

class ShellNavigationTest extends TestCase
{
    public function test_each_planned_section_opens_a_named_page_with_an_honest_placeholder(): void
    {
        $this->withoutVite();

        $pages = [
            ['products.index', [], 'Productos'],
            ['products.show', ['product' => 1], 'Detalle del producto'],
            ['inventory.index', [], 'Stock y movimientos'],
            ['purchases.index', [], 'Pedidos a proveedor'],
            ['purchases.show', ['order' => 1], 'Detalle del pedido'],
            ['purchases.receipts', [], 'Recepción de compras'],
            ['sales.index', [], 'Ventas'],
            ['sales.create', [], 'Nueva venta'],
            ['customers.index', [], 'Clientas'],
            ['suppliers.index', [], 'Proveedores'],
            ['categories.index', [], 'Categorías'],
            ['reports.index', [], 'Reportes'],
            ['settings.index', [], 'Configuración'],
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
    }

    public function test_list_and_detail_shells_explain_their_unavailable_content(): void
    {
        $this->withoutVite();

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('Listado en preparación')
            ->assertSee('Todavía no muestra datos ni permite registrar operaciones.')
            ->assertSee('Disponible próximamente');

        $this->get(route('products.show', ['product' => 1]))
            ->assertOk()
            ->assertSee('No se muestran datos de ningún registro.')
            ->assertSee('Volver a productos');
    }

    public function test_sidebar_links_go_to_existing_sections(): void
    {
        $this->withoutVite();

        $response = $this->get(route('home'));

        $response->assertOk();

        foreach (['products.index', 'inventory.index', 'purchases.index', 'purchases.receipts', 'sales.index', 'sales.create', 'customers.index', 'suppliers.index', 'categories.index', 'reports.index', 'settings.index', 'backups.index', 'help.index'] as $routeName) {
            $response->assertSee('href="'.route($routeName).'"', false);
        }

        $response->assertDontSee('aria-disabled="true"', false);
    }

    public function test_unknown_sections_and_non_numeric_detail_ids_are_not_found(): void
    {
        $this->withoutVite();

        $this->get('/no-existe')->assertNotFound();
        $this->get('/productos/desconocido')->assertNotFound();
        $this->get('/pedidos/desconocido')->assertNotFound();
    }
}
