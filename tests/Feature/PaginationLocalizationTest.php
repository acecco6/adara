<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class PaginationLocalizationTest extends TestCase
{
    public function test_pagination_links_render_in_spanish(): void
    {
        app()->setLocale('es');

        $paginator = new LengthAwarePaginator(range(1, 10), 20, 10, 1, ['path' => '/stock']);

        $html = $paginator->links()->toHtml();

        $this->assertStringContainsString('Anterior', $html);
        $this->assertStringContainsString('Siguiente', $html);
        $this->assertStringNotContainsString('pagination.previous', $html);
        $this->assertStringNotContainsString('pagination.next', $html);
    }
}
