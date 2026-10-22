<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_shows_adara_shell_with_available_navigation_links(): void
    {
        $this->withoutVite();

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Bienvenida')
            ->assertSee('Navegación principal')
            ->assertSee('Stock que necesita atención')
            ->assertDontSee('aria-disabled="true"', false)
            ->assertDontSee('Laravel');
    }
}
