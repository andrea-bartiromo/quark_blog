<?php

namespace Tests\Unit\Articles;

use App\Services\Articles\ArticleSourcePresenter;
use Tests\TestCase;

class ArticleSourcePresenterTest extends TestCase
{
    public function test_it_preserves_http_urls_dois_and_unrecognized_text(): void
    {
        $sources = app(ArticleSourcePresenter::class)->present(implode("\n", [
            'NASA — https://www.nasa.gov/example',
            'Studio principale — 10.1038/s41586-026-00001-2',
            'Archivio cartaceo Kairus, fascicolo 12',
        ]));

        $this->assertSame([
            ['label' => 'NASA', 'url' => 'https://www.nasa.gov/example'],
            ['label' => 'Studio principale', 'url' => 'https://doi.org/10.1038/s41586-026-00001-2'],
            ['label' => 'Archivio cartaceo Kairus, fascicolo 12', 'url' => null],
        ], $sources->all());
    }

    public function test_it_never_turns_non_http_schemes_or_html_into_links(): void
    {
        $sources = app(ArticleSourcePresenter::class)->present("javascript:alert(1)\n<script>alert(2)</script>");

        $this->assertNull($sources[0]['url']);
        $this->assertNull($sources[1]['url']);
    }

    public function test_it_combines_legacy_sources_without_duplicates(): void
    {
        $sources = app(ArticleSourcePresenter::class)->present(
            'NASA — https://www.nasa.gov/example',
            "NASA — https://www.nasa.gov/example\nFonte legacy",
        );

        $this->assertCount(2, $sources);
        $this->assertSame('Fonte legacy', $sources[1]['label']);
    }
}
