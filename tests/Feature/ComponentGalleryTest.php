<?php

namespace Tests\Feature;

use Tests\TestCase;

class ComponentGalleryTest extends TestCase
{
    public function test_gallery_shows_accessible_field_errors_and_content_states(): void
    {
        $this->withoutVite();

        $response = $this->get(route('dev.components'));

        $response->assertOk()
            ->assertSee('Componentes y estados')
            ->assertSee('for="ejemplo-codigo"', false)
            ->assertSee('id="ejemplo-codigo"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="ejemplo-codigo-error"', false)
            ->assertSee('id="ejemplo-codigo-error" role="alert"', false)
            ->assertSee('inputmode="decimal"', false)
            ->assertSee('data-adara-select-native', false)
            ->assertSee('data-adara-select-trigger aria-haspopup="listbox"', false)
            ->assertSee('data-adara-select-listbox role="listbox"', false)
            ->assertSee('id="ejemplo-modal"', false)
            ->assertSee('id="ejemplo-confirmacion"', false)
            ->assertSee('aria-labelledby="ejemplo-confirmacion-title"', false)
            ->assertSee('Confirmar ejemplo')
            ->assertSee('wire:navigate', false)
            ->assertSee('Cargando la lista')
            ->assertSee('Todavía no hay registros');
    }
}
