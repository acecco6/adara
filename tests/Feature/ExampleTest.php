<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_shows_adara_shell_without_unavailable_navigation_links(): void
    {
        $this->withoutVite();

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Bienvenida a Adara')
            ->assertSee('Navegación principal')
            ->assertSee('Productos y presentaciones')
            ->assertSee('aria-disabled="true"', false)
            ->assertDontSee('Laravel');
    }
}
