<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trust Layer V1 — rendering pubblico di Article::primary_sources sulla
 * pagina articolo. Copre solo la presentazione (vedi
 * App\Services\ArticlePrimarySourcesParser per i casi malformati/ostili):
 * qui si verifica che il dato arrivi in pagina nella forma attesa, sia
 * assente quando non c'è nulla da mostrare, e non collida con il blocco
 * "Fonti" legacy derivato dal corpo.
 */
class ArticlePublicPrimarySourcesTest extends TestCase
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

    public function test_primary_sources_panel_renders_a_link_for_a_url(): void
    {
        $article = $this->publishedArticle([
            'primary_sources' => 'https://www.nature.com/articles/12345',
        ]);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertSee('Fonti primarie');
        $response->assertSee('href="https://www.nature.com/articles/12345"', false);
        $response->assertSee('rel="nofollow noopener noreferrer"', false);
    }

    public function test_primary_sources_panel_renders_plain_text_for_non_url_lines(): void
    {
        $article = $this->publishedArticle([
            'primary_sources' => 'Comunicato stampa ESA, ottobre 2026',
        ]);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertSee('Comunicato stampa ESA, ottobre 2026');
    }

    public function test_hostile_markup_in_primary_sources_is_escaped_not_executed(): void
    {
        $article = $this->publishedArticle([
            'primary_sources' => '<script>window.__pwned = true;</script>',
        ]);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertDontSee('<script>window.__pwned = true;</script>', false);
        $response->assertSee('&lt;script&gt;', false);
    }

    public function test_no_panel_is_rendered_when_primary_sources_is_null(): void
    {
        $article = $this->publishedArticle(['primary_sources' => null]);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertDontSee('id="article-primary-sources-heading"', false);
    }

    public function test_no_panel_is_rendered_when_primary_sources_is_blank_or_whitespace_only(): void
    {
        $article = $this->publishedArticle(['primary_sources' => "   \n\n  "]);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertDontSee('id="article-primary-sources-heading"', false);
    }

    public function test_legacy_body_sources_block_and_new_primary_sources_panel_coexist_without_collision(): void
    {
        $article = $this->publishedArticle([
            'body' => "<p>Corpo.</p>\n---\nFonte legacy dal corpo",
            'primary_sources' => 'https://example.com/fonte-primaria',
        ]);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertSee('Fonte legacy dal corpo');
        $response->assertSee('Fonti primarie');
        $response->assertSee('href="https://example.com/fonte-primaria"', false);
    }
}
