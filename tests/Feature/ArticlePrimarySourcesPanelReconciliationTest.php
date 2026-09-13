<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 34 (programma 100 cantieri Kairus, dipende dal Cantiere 33).
 *
 * ArticlePublicPrimarySourcesTest copre già la presentazione isolata del
 * pannello Fonti primarie (Article::primary_sources) e un unico caso di
 * coesistenza con il blocco "Fonti" legacy — ma quel caso usa solo il
 * delimitatore `---` nel corpo, mai una heading "Fonti"/"Fonti primarie"
 * riconosciuta da ArticleManualSourcesDetector. La reale logica di
 * soppressione (articolo.blade.php: `@unless($hasManualSourcesSection)`)
 * non aveva quindi mai un test che la eserciti davvero.
 *
 * Qui si verifica l'intera matrice: pannello fonti primarie strutturate
 * (x-article.primary-sources) vs. heading manuale "Fonti" nel corpo vs.
 * blocco legacy `---` (x-kairus.trust-panel, indipendente dalla
 * soppressione — vedi ArticleController::show(), che passa
 * $article->body per intero, non $mainBody, ad hasManualSourcesSection()).
 */
class ArticlePrimarySourcesPanelReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function publishedArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $this->author()->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Sommario di prova',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'intelligenza-artificiale',
            'cover_image' => 'copertina.jpg',
            'status' => 'published',
            'published_at' => now(),
        ], $overrides));
    }

    public function test_primary_sources_panel_shows_when_body_has_no_manual_heading_and_no_legacy_delimiter(): void
    {
        $article = $this->publishedArticle([
            'body' => '<p>Corpo senza heading fonti né delimitatore.</p>',
            'primary_sources' => 'https://example.com/fonte-primaria',
        ]);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertSee('id="article-primary-sources-heading"', false);
        $response->assertSee('href="https://example.com/fonte-primaria"', false);
    }

    public function test_primary_sources_panel_is_suppressed_when_body_has_a_manual_fonti_heading(): void
    {
        $article = $this->publishedArticle([
            'body' => '<h2>Fonti</h2><p>Fonte manuale scritta direttamente nel corpo.</p>',
            'primary_sources' => 'https://example.com/fonte-primaria',
        ]);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertSee('Fonte manuale scritta direttamente nel corpo.');
        $response->assertDontSee('id="article-primary-sources-heading"', false);
        $response->assertDontSee('href="https://example.com/fonte-primaria"', false);
    }

    public function test_primary_sources_panel_stays_suppressed_when_manual_heading_present_and_primary_sources_is_null(): void
    {
        $article = $this->publishedArticle([
            'body' => '<h2>Fonti primarie</h2><p>Fonte manuale scritta direttamente nel corpo.</p>',
            'primary_sources' => null,
        ]);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertSee('Fonte manuale scritta direttamente nel corpo.');
        $response->assertDontSee('id="article-primary-sources-heading"', false);
    }

    public function test_legacy_delimiter_panel_and_primary_sources_panel_both_show_when_body_has_no_manual_heading(): void
    {
        $article = $this->publishedArticle([
            'body' => "<p>Corpo.</p>\n---\nFonte legacy dal corpo, nessuna heading.",
            'primary_sources' => 'https://example.com/fonte-primaria',
        ]);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertSee('Fonte legacy dal corpo, nessuna heading.');
        $response->assertSee('id="article-primary-sources-heading"', false);
        $response->assertSee('href="https://example.com/fonte-primaria"', false);
    }

    public function test_manual_heading_suppresses_primary_panel_while_legacy_delimiter_panel_still_shows(): void
    {
        $article = $this->publishedArticle([
            'body' => "<h2>Fonti</h2><p>Fonte manuale nel corpo.</p>\n---\nFonte legacy dopo il delimitatore.",
            'primary_sources' => 'https://example.com/fonte-primaria',
        ]);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertSee('Fonte manuale nel corpo.');
        $response->assertSee('Fonte legacy dopo il delimitatore.');
        $response->assertDontSee('id="article-primary-sources-heading"', false);
        $response->assertDontSee('href="https://example.com/fonte-primaria"', false);
    }
}
