<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class PaginationLocalizationTest extends TestCase
{
    public function test_pagination_links_render_in_spanish(): void
    {
        app()->setLocale('es');

        $paginator = new LengthAwarePaginator(range(1, 10), 30, 10, 2, ['path' => '/stock', 'pageName' => 'stock-page']);

        $html = $paginator->links('components.adara.pagination', data: ['scrollTo' => false])->toHtml();

        $this->assertStringContainsString('Anterior', $html);
        $this->assertStringContainsString('Siguiente', $html);
        $this->assertStringContainsString('Mostrando', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString("nextPage('stock-page')", $html);
        $this->assertStringContainsString('bg-adara-primary', $html);
        $this->assertStringNotContainsString('pagination.previous', $html);
        $this->assertStringNotContainsString('pagination.next', $html);
    }
}
