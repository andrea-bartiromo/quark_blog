<?php

namespace Tests\Unit\EditorialQuality;

use App\Models\Article;
use App\Models\User;
use App\Services\ArticleLinkInsertionService;
use App\Services\EditorialQuality\EditorialQualityChecker;
use App\Services\EditorialQuality\EditorialQualityCheckResult as R;
use App\Services\EditorialQuality\EditorialQualityReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorialQualityCheckerTest extends TestCase
{
    use RefreshDatabase;

    private EditorialQualityChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new EditorialQualityChecker(new ArticleLinkInsertionService);
    }

    /**
     * Articolo "completo": deve passare tutti i controlli applicabili
     * senza alcuna eccezione — usato come baseline per gli scenari
     * A/B/C/D/E/F/G/H/I/J/K/L della missione.
     */
    private function completeArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'editor'])->id,
            'title' => 'La scoperta di un nuovo esopianeta abitabile',
            'slug' => 'scoperta-esopianeta-abitabile-'.uniqid(),
            'excerpt' => 'Un team internazionale ha individuato un pianeta nella zona abitabile di una stella vicina.',
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso sulla scoperta. ', 15).'</p>',
            'category' => 'spazio',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'cover_image' => 'copertina.webp',
            'cover_alt' => 'Rappresentazione artistica dell\'esopianeta',
            'primary_sources' => 'https://www.nasa.gov/press-release/esopianeta',
        ], $overrides));
    }

    private function resultFor(EditorialQualityReport $report, string $code): R
    {
        foreach ($report->results as $result) {
            if ($result->code === $code) {
                return $result;
            }
        }

        $this->fail("Nessun risultato con code={$code}");
    }

    // ── Titolo ──

    public function test_a_blank_title_fails(): void
    {
        $article = $this->completeArticle(['title' => '   ']);

        $result = $this->resultFor($this->checker->check($article), 'title_present');

        $this->assertSame(R::STATUS_FAIL, $result->status);
        $this->assertSame(R::IMPORTANCE_ESSENTIAL, $result->importance);
    }

    public function test_a_short_but_legitimate_title_still_passes(): void
    {
        $article = $this->completeArticle(['title' => 'Ali']);

        $result = $this->resultFor($this->checker->check($article), 'title_present');

        $this->assertSame(R::STATUS_PASS, $result->status);
    }

    // ── Slug ──

    public function test_a_blank_slug_fails(): void
    {
        $article = $this->completeArticle();
        $article->slug = '';

        $result = $this->resultFor($this->checker->check($article), 'slug_present');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    public function test_an_invalid_slug_format_fails(): void
    {
        $article = $this->completeArticle();
        $article->slug = 'Slug Con Spazi!';

        $result = $this->resultFor($this->checker->check($article), 'slug_present');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    public function test_a_historical_slug_unrelated_to_the_current_title_still_passes(): void
    {
        $article = $this->completeArticle(['title' => 'Titolo cambiato molte volte']);
        $article->slug = 'slug-storico-invariato';

        $result = $this->resultFor($this->checker->check($article), 'slug_present');

        $this->assertSame(R::STATUS_PASS, $result->status);
    }

    // ── Sommario ──

    public function test_an_empty_excerpt_warns_but_never_fails(): void
    {
        $article = $this->completeArticle(['excerpt' => '']);

        $result = $this->resultFor($this->checker->check($article), 'excerpt_present');

        $this->assertSame(R::STATUS_WARNING, $result->status);
    }

    // ── Corpo ──

    public function test_an_html_only_body_with_no_real_text_fails(): void
    {
        $article = $this->completeArticle(['body' => '<p>&nbsp;</p><br><hr>']);

        $result = $this->resultFor($this->checker->check($article), 'body_present');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    public function test_a_body_made_only_of_images_without_real_text_fails(): void
    {
        $article = $this->completeArticle(['body' => '<img src="/a.jpg"><img src="/b.jpg">']);

        $result = $this->resultFor($this->checker->check($article), 'body_present');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    public function test_a_short_but_legitimate_body_still_passes_if_above_the_word_threshold(): void
    {
        $article = $this->completeArticle(['body' => '<p>'.str_repeat('parola ', 60).'</p>']);

        $result = $this->resultFor($this->checker->check($article), 'body_present');

        $this->assertSame(R::STATUS_PASS, $result->status);
    }

    // ── Placeholder ──

    public function test_lorem_ipsum_in_the_body_is_detected(): void
    {
        $article = $this->completeArticle(['body' => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>']);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    public function test_a_todo_marker_in_the_excerpt_is_detected(): void
    {
        $article = $this->completeArticle(['excerpt' => 'TODO: scrivere il sommario definitivo']);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    /**
     * Falso positivo noto da evitare: un articolo scientifico legittimo
     * può usare "test" in senso tecnico ("test di laboratorio",
     * "Test di Turing") senza essere un placeholder.
     */
    public function test_the_word_test_used_in_a_legitimate_scientific_context_is_never_flagged(): void
    {
        $article = $this->completeArticle([
            'title' => 'Il nuovo test di laboratorio per la diagnosi precoce',
            'body' => '<p>'.str_repeat('Il test clinico ha coinvolto centinaia di pazienti in tutta Italia. ', 10).'</p>',
        ]);

        $result = $this->resultFor($this->checker->check($article), 'no_placeholder_markers');

        $this->assertSame(R::STATUS_PASS, $result->status);
    }

    // ── Cover / alt ──

    public function test_no_cover_fails(): void
    {
        $article = $this->completeArticle(['cover_image' => null]);

        $result = $this->resultFor($this->checker->check($article), 'cover_present');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    public function test_a_cover_without_alt_text_fails_the_alt_check(): void
    {
        $article = $this->completeArticle(['cover_alt' => null]);

        $result = $this->resultFor($this->checker->check($article), 'cover_alt_present');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    public function test_the_cover_alt_check_is_not_applicable_without_a_cover(): void
    {
        $article = $this->completeArticle(['cover_image' => null]);

        $result = $this->resultFor($this->checker->check($article), 'cover_alt_present');

        $this->assertSame(R::STATUS_NOT_APPLICABLE, $result->status);
    }

    public function test_a_body_image_without_alt_warns(): void
    {
        $article = $this->completeArticle(['body' => '<p>'.str_repeat('Testo. ', 20).'</p><img src="/foto.jpg">']);

        $result = $this->resultFor($this->checker->check($article), 'body_images_alt');

        $this->assertSame(R::STATUS_WARNING, $result->status);
    }

    public function test_a_body_with_no_images_is_not_applicable(): void
    {
        $article = $this->completeArticle();

        $result = $this->resultFor($this->checker->check($article), 'body_images_alt');

        $this->assertSame(R::STATUS_NOT_APPLICABLE, $result->status);
    }

    // ── SEO ──

    public function test_seo_checks_are_not_applicable_for_a_draft(): void
    {
        $article = $this->completeArticle(['status' => Article::STATUS_DRAFT, 'published_at' => null]);

        $report = $this->checker->check($article);

        $this->assertSame(R::STATUS_NOT_APPLICABLE, $this->resultFor($report, 'seo_title')->status);
        $this->assertSame(R::STATUS_NOT_APPLICABLE, $this->resultFor($report, 'meta_description')->status);
    }

    public function test_a_very_long_effective_seo_title_warns(): void
    {
        $article = $this->completeArticle(['title' => str_repeat('Un titolo scientifico molto lungo e dettagliato ', 3)]);

        $result = $this->resultFor($this->checker->check($article), 'seo_title');

        $this->assertSame(R::STATUS_WARNING, $result->status);
    }

    public function test_noindex_on_a_published_article_warns(): void
    {
        $article = $this->completeArticle(['robots' => 'noindex,follow']);

        $result = $this->resultFor($this->checker->check($article), 'indexability');

        $this->assertSame(R::STATUS_WARNING, $result->status);
    }

    // ── Struttura ──

    public function test_a_long_article_without_any_heading_warns(): void
    {
        $article = $this->completeArticle(['body' => '<p>'.str_repeat('parola ', 700).'</p>']);

        $result = $this->resultFor($this->checker->check($article), 'structure_headings');

        $this->assertSame(R::STATUS_WARNING, $result->status);
    }

    public function test_a_long_article_with_headings_passes(): void
    {
        $article = $this->completeArticle(['body' => '<h2>Introduzione</h2><p>'.str_repeat('parola ', 700).'</p>']);

        $result = $this->resultFor($this->checker->check($article), 'structure_headings');

        $this->assertSame(R::STATUS_PASS, $result->status);
    }

    public function test_a_short_article_never_requires_headings(): void
    {
        $article = $this->completeArticle();

        $result = $this->resultFor($this->checker->check($article), 'structure_headings');

        $this->assertSame(R::STATUS_NOT_APPLICABLE, $result->status);
    }

    // ── Fonti ──

    public function test_missing_sources_warn_but_never_fail(): void
    {
        $article = $this->completeArticle(['primary_sources' => null]);

        $result = $this->resultFor($this->checker->check($article), 'sources_present');

        $this->assertSame(R::STATUS_WARNING, $result->status);
    }

    public function test_a_recognized_institutional_domain_is_surfaced_in_details(): void
    {
        $article = $this->completeArticle(['primary_sources' => 'Fonte: https://www.nature.com/articles/xyz']);

        $result = $this->resultFor($this->checker->check($article), 'sources_present');

        $this->assertSame(R::STATUS_PASS, $result->status);
        $this->assertSame('nature.com', $result->details['recognized_domain'] ?? null);
    }

    // ── Autore / categoria / pubblicazione ──

    public function test_a_missing_category_fails(): void
    {
        $article = $this->completeArticle(['category' => 'categoria-inesistente-xyz']);

        $result = $this->resultFor($this->checker->check($article), 'category_valid');

        $this->assertSame(R::STATUS_FAIL, $result->status);
    }

    public function test_an_overdue_scheduled_article_warns_on_publishing_coherence(): void
    {
        $article = $this->completeArticle([
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->subHour(),
        ]);

        $result = $this->resultFor($this->checker->check($article), 'publishing_coherence');

        $this->assertSame(R::STATUS_WARNING, $result->status);
    }

    // ── Readiness ──

    public function test_a_fully_complete_article_is_ready(): void
    {
        $article = $this->completeArticle(['body' => '<p>'.str_repeat('Testo scientifico reale. ', 20).'</p><a href="/articolo/altro">altro articolo</a>']);

        $report = $this->checker->check($article);

        $this->assertSame(EditorialQualityReport::LEVEL_READY, $report->level());
    }

    public function test_an_essential_failure_makes_the_article_incomplete_regardless_of_other_passes(): void
    {
        $article = $this->completeArticle(['cover_image' => null]);

        $report = $this->checker->check($article);

        // cover_present è ESSENTIAL: la sua assenza basta da sola.
        $this->assertSame(EditorialQualityReport::LEVEL_INCOMPLETE, $report->level());
    }

    public function test_only_recommended_warnings_result_in_attention_not_incomplete(): void
    {
        $article = $this->completeArticle(['excerpt' => '']);

        $report = $this->checker->check($article);

        $this->assertSame(EditorialQualityReport::LEVEL_ATTENTION, $report->level());
    }

    public function test_not_applicable_checks_are_excluded_from_the_denominator(): void
    {
        // Articolo minimale (scenario B della missione): draft, quindi
        // SEO/indexability/internal_links/structure sono NOT_APPLICABLE.
        $article = $this->completeArticle(['status' => Article::STATUS_DRAFT, 'published_at' => null]);

        $report = $this->checker->check($article);

        $this->assertLessThan(count($report->results), $report->applicableCount());
        $this->assertGreaterThan(0, $report->applicableCount());
    }

    public function test_a_minimal_but_valid_draft_never_throws(): void
    {
        $article = Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Bozza minima',
            'slug' => 'bozza-minima-'.uniqid(),
            'body' => '<p>'.str_repeat('parola ', 60).'</p>',
            'category' => 'spazio',
            'status' => Article::STATUS_DRAFT,
            'published_at' => null,
        ]);

        $report = $this->checker->check($article);

        $this->assertNotEmpty($report->results);
    }
}
