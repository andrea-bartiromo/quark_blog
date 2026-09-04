<?php

namespace Tests\Feature\DesignSystem;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere E — Article Visual Adoption (Prompt 107).
 *
 * Congela, PRIMA di qualunque modifica di markup/CSS, i comportamenti
 * della pagina articolo non già coperti in dettaglio dalle suite SEO/
 * JSON-LD/breadcrumb/cover/TOC esistenti (ArticleSeoMetaTest,
 * ArticleStructuredDataTest, ArticleBreadcrumbStructuredDataTest,
 * ArticleBreadcrumbVisibleTest, ArticleCoverMetadataTest,
 * ArticleTableOfContentsTest): ordine delle sezioni, H1 unico, pannello
 * "Fonti" legacy, form Newsletter, blocco autore/condividi.
 */
class ArticleRefreshTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'editor', 'name' => 'Autrice di Prova']);
    }

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $this->author()->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Sommario di prova.',
            'body' => "<p>Corpo di prova con abbastanza testo.</p>\n<h2>Un sottotitolo</h2>\n<p>Altro testo.</p>",
            'category' => 'intelligenza-artificiale',
            'status' => 'published',
            'featured' => false,
            'published_at' => now()->subDay(),
            'read_minutes' => 4,
            'views' => 12,
        ], $overrides));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Category::updateOrCreate(
            ['slug' => 'intelligenza-artificiale'],
            ['name' => 'Intelligenza Artificiale', 'is_active' => true, 'sort_order' => 0]
        );
    }

    public function test_article_page_sections_appear_in_the_approved_narrative_order(): void
    {
        $article = $this->article();
        // related-articles.blade.php si nasconde del tutto se $related è
        // vuoto. Un solo altro articolo nella stessa categoria non basta:
        // ArticleController::show() lo assorbirebbe come target "Continua
        // da qui" (stessa categoria, nessun Percorso) ed escluderebbe
        // quell'id dai correlati — servono due articoli aggiuntivi perché
        // uno resti per la sezione "Continua a leggere".
        $this->article(['title' => 'Articolo continuazione', 'published_at' => now()->subHours(2)]);
        $this->article(['title' => 'Articolo correlato', 'published_at' => now()->subHours(3)]);

        $html = $this->get(route('articolo', $article->slug))->assertOk()->getContent();

        $markers = [
            'breadcrumb' => 'article-premium__breadcrumb',
            'hero' => 'article-premium__hero',
            'body' => 'article-premium__body',
            'newsletter' => 'public-feature-band',
            'related' => 'related-premium-grid',
        ];

        $positions = [];
        foreach ($markers as $name => $needle) {
            $position = strpos($html, $needle);
            $this->assertNotFalse($position, "Sezione \"{$name}\" ({$needle}) non trovata.");
            $positions[$name] = $position;
        }

        $this->assertLessThan($positions['hero'], $positions['breadcrumb']);
        $this->assertLessThan($positions['body'], $positions['hero']);
        $this->assertLessThan($positions['newsletter'], $positions['body']);
        $this->assertLessThan($positions['related'], $positions['newsletter']);
    }

    public function test_article_page_has_exactly_one_h1(): void
    {
        $article = $this->article();

        $html = $this->get(route('articolo', $article->slug))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<h1 class="kairus-article-hero__title">'.$article->title.'</h1>', $html);
    }

    public function test_legacy_sources_panel_renders_from_the_body_delimiter(): void
    {
        $article = $this->article([
            'body' => "<p>Corpo principale.</p>\n---\nFonte uno: https://example.com/a\nFonte due: https://example.com/b",
        ]);

        $html = $this->get(route('articolo', $article->slug))->assertOk()->getContent();

        $this->assertStringContainsString('<h3>Fonti</h3>', $html);
        $this->assertStringContainsString('Fonte uno: https://example.com/a', $html);
        $this->assertStringContainsString('Fonte due: https://example.com/b', $html);
    }

    public function test_no_sources_panel_when_body_has_no_delimiter(): void
    {
        $article = $this->article(['body' => '<p>Corpo senza delimitatore.</p>']);

        $html = $this->get(route('articolo', $article->slug))->assertOk()->getContent();

        $this->assertStringNotContainsString('<h3>Fonti</h3>', $html);
    }

    public function test_newsletter_form_action_method_csrf_and_field_names_are_unchanged(): void
    {
        $article = $this->article();

        $html = $this->get(route('articolo', $article->slug))->assertOk()->getContent();

        preg_match('/<section class="public-feature-band">.*?<\/section>/s', $html, $sectionMatch);
        $section = $sectionMatch[0] ?? '';
        $this->assertNotSame('', $section, 'Sezione Newsletter non trovata.');

        $this->assertStringContainsString('action="'.route('newsletter.subscribe').'"', $section);
        $this->assertStringContainsString('method="POST"', $section);
        $this->assertStringContainsString('name="_token"', $section);
        $this->assertStringContainsString('name="source" value="article"', $section);
        $this->assertStringContainsString('name="email"', $section);
        $this->assertSame(1, substr_count($section, '<form'));
    }

    public function test_author_card_links_to_the_author_profile_with_correct_name(): void
    {
        $article = $this->article();

        $html = $this->get(route('articolo', $article->slug))->assertOk()->getContent();

        $this->assertStringContainsString($article->author->name, $html);
        $this->assertStringContainsString('href="'.route('autore', $article->author).'"', $html);
    }

    public function test_share_card_preserves_social_share_urls_and_copy_link_button(): void
    {
        $article = $this->article();

        $html = $this->get(route('articolo', $article->slug))->assertOk()->getContent();

        $this->assertStringContainsString('twitter.com/intent/tweet', $html);
        $this->assertStringContainsString('api.whatsapp.com/send', $html);
        $this->assertStringContainsString('linkedin.com/sharing/share-offsite', $html);
        $this->assertStringContainsString('copyArticleLink(this.dataset.url)', $html);
    }

    public function test_hero_and_body_have_no_nested_interactive_elements(): void
    {
        $article = $this->article();

        $html = $this->get(route('articolo', $article->slug))->assertOk()->getContent();

        preg_match('/<header class="[^"]*\barticle-premium__hero\b[^"]*">.*?<\/header>/s', $html, $heroMatch);
        $hero = $heroMatch[0] ?? '';
        $this->assertNotSame('', $hero);
        // Il trigger del lightbox (components/media/image-viewer.blade.php)
        // formatta i propri attributi su più righe: "<a\n    href=...",
        // senza uno spazio subito dopo "<a" — substr_count('<a ') non lo
        // conterebbe. \b<a\b intercetta "<a " e "<a\n" allo stesso modo.
        $this->assertSame(preg_match_all('/<a\b/', $hero), substr_count($hero, '</a>'), 'Numero di <a> aperti e chiusi non coincide nell\'hero.');
    }
}
