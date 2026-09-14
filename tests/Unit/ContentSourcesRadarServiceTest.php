<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\User;
use App\Services\ContentSourcesRadarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 84 (programma "100 cantieri Kairus", indipendente): a
 * differenza di EditorialQualityChecker::sourcesCheck() (Cantieri
 * 33/34, per un singolo articolo), questo servizio aggrega il profilo
 * delle fonti su TUTTI gli articoli pubblicati — quali domini
 * ricorrono, quanti articoli non citano alcuna fonte.
 */
class ContentSourcesRadarServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ContentSourcesRadarService
    {
        // Risolto dal container, non `new` diretto: il servizio dipende
        // anche da EditorialQualityChecker (Codex, PR #609), che a sua
        // volta dipende da ArticleLinkInsertionService — comporlo a
        // mano qui duplicherebbe silenziosamente il grafo di dipendenze
        // reale, con rischio di andare fuori sincrono ad ogni modifica
        // futura a monte.
        return $this->app->make(ContentSourcesRadarService::class);
    }

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $this->author()->id,
            'title' => 'Articolo di prova '.uniqid(),
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
        ], $overrides));
    }

    public function test_with_no_articles_everything_is_zero(): void
    {
        $report = $this->service()->report();

        $this->assertSame(0, $report['total_published_articles']);
        $this->assertSame(0, $report['articles_with_sources']);
        $this->assertSame(0, $report['articles_without_sources']);
        $this->assertSame(0, $report['distinct_domains']);
        $this->assertSame([], $report['top_domains']);
    }

    public function test_an_article_with_a_blank_primary_sources_field_counts_as_without_sources(): void
    {
        $this->article(['primary_sources' => null]);

        $report = $this->service()->report();

        $this->assertSame(1, $report['total_published_articles']);
        $this->assertSame(0, $report['articles_with_sources']);
        $this->assertSame(1, $report['articles_without_sources']);
    }

    public function test_an_article_with_only_free_text_sources_counts_as_with_sources_but_contributes_no_domain(): void
    {
        $this->article(['primary_sources' => "Intervista telefonica all'autore, 12 settembre 2026."]);

        $report = $this->service()->report();

        $this->assertSame(1, $report['articles_with_sources']);
        $this->assertSame(0, $report['articles_without_sources']);
        $this->assertSame(1, $report['articles_with_only_text_sources']);
        $this->assertSame(0, $report['distinct_domains']);
    }

    public function test_a_link_source_contributes_to_its_domain_count(): void
    {
        $this->article(['primary_sources' => 'https://www.nature.com/articles/example']);

        $report = $this->service()->report();

        $this->assertSame(1, $report['distinct_domains']);
        $this->assertSame(['domain' => 'nature.com', 'article_count' => 1], $report['top_domains'][0]);
    }

    /**
     * "www.nature.com" e "nature.com" devono contare come lo stesso
     * dominio, non due domini distinti.
     */
    public function test_www_prefix_is_normalized_away(): void
    {
        $this->article(['primary_sources' => 'https://nature.com/a']);
        $this->article(['primary_sources' => 'https://www.nature.com/b']);

        $report = $this->service()->report();

        $this->assertSame(1, $report['distinct_domains']);
        $this->assertSame(2, $report['top_domains'][0]['article_count']);
    }

    /**
     * Il radar misura quanti ARTICOLI distinti citano un dominio, non
     * il numero grezzo di righe: un solo articolo che cita lo stesso
     * dominio più volte non deve pesare quanto più articoli distinti.
     */
    public function test_repeated_citations_of_the_same_domain_within_one_article_count_once(): void
    {
        $this->article(['primary_sources' => "https://nature.com/a\nhttps://nature.com/b\nhttps://nature.com/c"]);

        $report = $this->service()->report();

        $this->assertSame(1, $report['distinct_domains']);
        $this->assertSame(1, $report['top_domains'][0]['article_count']);
    }

    public function test_a_doi_link_contributes_to_the_doi_org_domain(): void
    {
        $this->article(['primary_sources' => '10.1038/s41586-021-03819-2']);

        $report = $this->service()->report();

        $this->assertSame(['domain' => 'doi.org', 'article_count' => 1], $report['top_domains'][0]);
    }

    public function test_top_domains_are_sorted_descending_by_distinct_article_count(): void
    {
        $this->article(['primary_sources' => 'https://common-domain.test/a']);
        $this->article(['primary_sources' => 'https://common-domain.test/b']);
        $this->article(['primary_sources' => 'https://rare-domain.test/a']);

        $report = $this->service()->report();

        $this->assertSame('common-domain.test', $report['top_domains'][0]['domain']);
        $this->assertSame(2, $report['top_domains'][0]['article_count']);
        $this->assertSame('rare-domain.test', $report['top_domains'][1]['domain']);
        $this->assertSame(1, $report['top_domains'][1]['article_count']);
    }

    public function test_an_unpublished_article_is_excluded_entirely(): void
    {
        $this->article([
            'status' => Article::STATUS_DRAFT,
            'published_at' => null,
            'primary_sources' => 'https://nature.com/a',
        ]);

        $report = $this->service()->report();

        $this->assertSame(0, $report['total_published_articles']);
        $this->assertSame(0, $report['distinct_domains']);
    }

    /**
     * Codex (PR #609, P1): un articolo con una sezione "Fonti" scritta
     * a mano nel corpo (heading h1-h6, riconosciuta da
     * ArticleManualSourcesDetector — lo stesso identico segnale usato
     * da articolo.blade.php per sopprimere il pannello pubblico) ma
     * primary_sources mai compilato è il caso reale più comune: prima
     * del fix veniva contato come "senza fonti", un falso negativo
     * sistematico.
     */
    public function test_an_article_with_only_a_manual_heading_sources_section_in_the_body_counts_as_having_sources(): void
    {
        $this->article([
            'primary_sources' => null,
            'body' => '<p>Corpo.</p><h2>Fonti</h2><p>Intervista diretta.</p>',
        ]);

        $report = $this->service()->report();

        $this->assertSame(1, $report['articles_with_sources']);
        $this->assertSame(0, $report['articles_without_sources']);
        $this->assertSame(1, $report['articles_with_body_only_sources']);
    }

    /**
     * Codex (PR #609, P1), metà "inversa": quando esiste una sezione
     * Fonti manuale a heading nel corpo, articolo.blade.php sopprime il
     * pannello pubblico di primary_sources — i suoi URL, anche se il
     * campo è compilato, non sono mai visibili al lettore reale e non
     * devono contribuire al conteggio domini del radar.
     */
    public function test_a_manual_heading_sources_section_suppresses_the_primary_sources_domain_from_the_radar(): void
    {
        $this->article([
            'primary_sources' => 'https://nature.com/a',
            'body' => '<p>Corpo.</p><h2>Fonti</h2><p>Intervista diretta.</p>',
        ]);

        $report = $this->service()->report();

        $this->assertSame(1, $report['articles_with_sources']);
        $this->assertSame(1, $report['articles_with_body_only_sources']);
        $this->assertSame(0, $report['distinct_domains']);
        $this->assertSame([], $report['top_domains']);
    }

    /**
     * Codex (PR #609, P1): il caso legacy, testo libero dopo "---" nel
     * corpo senza alcuna heading — stesso identico segnale di
     * sourcesCheck() (EditorialQualityChecker::hasDelimitedSourcesSection(),
     * verificato solo quando né la heading manuale né primary_sources
     * sono presenti).
     */
    public function test_an_article_with_only_a_delimited_legacy_sources_block_counts_as_having_sources(): void
    {
        $this->article([
            'primary_sources' => null,
            'body' => "<p>Corpo dell'articolo con abbastanza contenuto.</p>\n---\nRicerca pubblicata su Nature (2026), consultata direttamente.",
        ]);

        $report = $this->service()->report();

        $this->assertSame(1, $report['articles_with_sources']);
        $this->assertSame(0, $report['articles_without_sources']);
        $this->assertSame(1, $report['articles_with_body_only_sources']);
    }
}
