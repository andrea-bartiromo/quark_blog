<?php

namespace Tests\Feature\DesignSystem;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere F — Archives Visual Adoption (Prompt 137).
 *
 * Congela, PRIMA di qualunque modifica di markup/CSS, i comportamenti di
 * /notizie e /categoria/{slug} non già coperti in dettaglio dalle suite
 * esistenti (ArchivePaginationCanonicalTest, CategoryPageEmptyStateTest,
 * CategoryPaginationV1RegressionTest, CategorySourceDbFirstTest,
 * CollectionPageStructuredDataTest, InactiveCategoryPublicLabelTest):
 * H1 unico, card articolo, stato vuoto categoria, link Turing condizionale.
 */
class ArchivesRefreshTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $this->author()->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-'.uniqid(),
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Corpo.</p>',
            'category' => 'fisica',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'read_minutes' => 4,
        ], $overrides));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Category::updateOrCreate(['slug' => 'fisica'], ['name' => 'Fisica', 'is_active' => true, 'sort_order' => 0]);
        Category::updateOrCreate(['slug' => 'intelligenza-artificiale'], ['name' => 'Intelligenza Artificiale', 'is_active' => true, 'sort_order' => 1]);
    }

    public function test_notizie_has_a_single_h1_and_lists_published_articles(): void
    {
        $article = $this->article(['title' => 'Titolo unico per notizie']);

        $html = $this->get(route('notizie'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('Titolo unico per notizie', $html);
        $this->assertStringContainsString('href="'.route('articolo', $article->slug).'"', $html);
    }

    public function test_categoria_has_a_single_h1_and_lists_published_articles(): void
    {
        $article = $this->article(['title' => 'Titolo unico per categoria']);

        $html = $this->get(route('categoria', 'fisica'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('Titolo unico per categoria', $html);
        $this->assertStringContainsString('href="'.route('articolo', $article->slug).'"', $html);
    }

    public function test_categoria_shows_the_turing_link_only_for_ai_category(): void
    {
        // La navigazione sitewide (header) contiene sempre un link a
        // Turing: la verifica va scoperta alla sola banda
        // "Editorial Focus" (public-feature-band), non all'intera pagina.
        $this->article(['category' => 'intelligenza-artificiale']);
        $htmlAi = $this->get(route('categoria', 'intelligenza-artificiale'))->assertOk()->getContent();
        preg_match('/<section class="public-feature-band">.*?<\/section>/s', $htmlAi, $bandAi);
        $this->assertStringContainsString('href="'.route('turing').'"', $bandAi[0] ?? '');

        $this->article(['category' => 'fisica']);
        $htmlPhysics = $this->get(route('categoria', 'fisica'))->assertOk()->getContent();
        preg_match('/<section class="public-feature-band">.*?<\/section>/s', $htmlPhysics, $bandPhysics);
        $this->assertNotSame('', $bandPhysics[0] ?? '', 'Banda Editorial Focus non trovata.');
        $this->assertStringNotContainsString('href="'.route('turing').'"', $bandPhysics[0]);
    }

    public function test_categoria_empty_state_is_generic_and_never_hardcoded_per_slug(): void
    {
        Category::updateOrCreate(['slug' => 'vuota'], ['name' => 'Categoria Vuota', 'is_active' => true, 'sort_order' => 2]);

        $html = $this->get(route('categoria', 'vuota'))->assertOk()->getContent();

        $this->assertStringContainsString('Nessun articolo pubblicato ancora', $html);
        $this->assertStringContainsString('Categoria Vuota', $html);
    }

    public function test_article_cards_render_as_a_single_link_each(): void
    {
        $this->article();
        $this->article();

        $html = $this->get(route('notizie'))->assertOk()->getContent();

        $cardCount = preg_match_all('/<a href="[^"]*" class="[^"]*\bkairus-article-card\b[^"]*"/', $html, $cards);
        $this->assertGreaterThanOrEqual(2, $cardCount, 'Attese almeno due card articolo.');
    }
}
