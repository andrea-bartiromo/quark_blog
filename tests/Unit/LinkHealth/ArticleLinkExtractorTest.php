<?php

namespace Tests\Unit\LinkHealth;

use App\Services\LinkHealth\ArticleLinkExtractor;
use Tests\TestCase;

/**
 * Cantiere 25 (programma 100-cantieri Kairus).
 */
class ArticleLinkExtractorTest extends TestCase
{
    private function extractor(): ArticleLinkExtractor
    {
        return new ArticleLinkExtractor;
    }

    public function test_extracts_a_relative_internal_link(): void
    {
        $links = $this->extractor()->extract('<p>Vedi la <a href="/categoria/fisica">categoria</a>.</p>');

        $this->assertCount(1, $links);
        $this->assertSame('/categoria/fisica', $links[0]['url']);
        $this->assertSame('categoria', $links[0]['anchorText']);
        $this->assertSame('internal', $links[0]['type']);
    }

    public function test_classifies_an_absolute_link_to_a_different_host_as_external(): void
    {
        $links = $this->extractor()->extract('<a href="https://esempio-esterno.it/pagina">fonte</a>');

        $this->assertSame('external', $links[0]['type']);
    }

    public function test_classifies_an_absolute_link_to_the_app_host_as_internal(): void
    {
        config(['app.url' => 'https://kairus.it']);

        $links = $this->extractor()->extract('<a href="https://kairus.it/percorsi/spazio">percorso</a>');

        $this->assertSame('internal', $links[0]['type']);
    }

    public function test_skips_mailto_tel_and_fragment_only_links(): void
    {
        $links = $this->extractor()->extract(
            '<a href="mailto:redazione@kairus.it">scrivici</a>'
            .'<a href="tel:+390000000">chiama</a>'
            .'<a href="#nota-1">nota</a>'
        );

        $this->assertSame([], $links);
    }

    public function test_skips_empty_href(): void
    {
        $links = $this->extractor()->extract('<a>senza href</a><a href="">vuoto</a>');

        $this->assertSame([], $links);
    }

    public function test_returns_empty_for_body_without_html(): void
    {
        $this->assertSame([], $this->extractor()->extract('solo testo semplice'));
        $this->assertSame([], $this->extractor()->extract(''));
    }

    public function test_to_internal_path_strips_scheme_and_host_from_an_absolute_url(): void
    {
        $extractor = $this->extractor();

        $this->assertSame('/percorsi/spazio', $extractor->toInternalPath('https://kairus.it/percorsi/spazio'));
        $this->assertSame('/categoria/fisica?ref=test', $extractor->toInternalPath('https://kairus.it/categoria/fisica?ref=test'));
        $this->assertSame('/percorsi/spazio', $extractor->toInternalPath('/percorsi/spazio'));
    }
}
