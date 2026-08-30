<?php

namespace Tests\Feature\EditorialSources;

use App\Models\Article;
use App\Models\ArticleSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EDITORIAL TRUST (Missione 28) — rendering pubblico della sezione fonti.
 */
class ArticlePublicSourcesTest extends TestCase
{
    use RefreshDatabase;

    private function publishedArticle(): Article
    {
        $author = User::factory()->create(['role' => 'author']);

        return Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo pubblicato',
            'slug' => 'articolo-pubblicato-'.uniqid(),
            'excerpt' => 'Sommario',
            'body' => '<p>Corpo articolo.</p>',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
        ]);
    }

    public function test_the_sources_section_is_absent_when_the_article_has_no_sources(): void
    {
        $article = $this->publishedArticle();

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertDontSee('id="fonti"', false);
    }

    public function test_a_source_with_a_valid_url_is_rendered_as_a_safe_link(): void
    {
        $article = $this->publishedArticle();
        $article->sources()->create([
            'title' => 'Comunicato ESA',
            'url' => 'https://www.esa.int/comunicato',
        ]);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertSee('id="fonti"', false);
        $response->assertSee('Comunicato ESA');
        $response->assertSee('href="https://www.esa.int/comunicato"', false);
        $response->assertSee('rel="noopener noreferrer"', false);
    }

    public function test_an_unsafe_url_persisted_directly_in_the_database_is_never_rendered_as_a_link(): void
    {
        $article = $this->publishedArticle();

        // Scrittura diretta sul modello, bypassando il normalizzatore: copre
        // il caso di una riga scritta da un percorso futuro che dimenticasse
        // di normalizzare — il rendering deve restare sicuro comunque.
        ArticleSource::withoutEvents(function () use ($article) {
            $article->sources()->create([
                'title' => 'Fonte manomessa',
                'url' => 'javascript:alert(1)',
            ]);
        });

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertSee('Fonte manomessa');
        $response->assertDontSee('javascript:', false);
    }

    public function test_unknown_source_type_never_prints_an_invented_label(): void
    {
        $article = $this->publishedArticle();
        $article->sources()->create([
            'title' => 'Fonte senza tipo dichiarato',
            'url' => 'https://www.esa.int/uno',
        ]);

        $response = $this->get(route('articolo', $article->slug))->assertOk();

        $response->assertDontSee('Non specificato');
    }

    public function test_a_declared_source_type_is_shown_publicly(): void
    {
        $article = $this->publishedArticle();
        $article->sources()->create([
            'title' => 'Comunicato ESA',
            'url' => 'https://www.esa.int/uno',
            'source_type' => ArticleSource::TYPE_INSTITUTIONAL,
        ]);

        $response = $this->get(route('articolo', $article->slug))->assertOk();

        $response->assertSee('Fonte istituzionale');
    }

    public function test_doi_takes_precedence_over_url_for_the_link_destination(): void
    {
        $article = $this->publishedArticle();
        $article->sources()->create([
            'title' => 'Studio Nature',
            'url' => 'https://www.nature.com/articles/x',
            'doi' => '10.1038/x-1',
        ]);

        $response = $this->get(route('articolo', $article->slug))->assertOk();

        $response->assertSee('href="https://doi.org/10.1038/x-1"', false);
        $response->assertSee('DOI 10.1038/x-1');
    }

    public function test_sources_render_in_the_editorial_order_regardless_of_creation_order(): void
    {
        $article = $this->publishedArticle();

        $second = $article->sources()->create(['title' => 'Seconda', 'url' => 'https://www.esa.int/due', 'position' => 1]);
        $first = $article->sources()->create(['title' => 'Prima', 'url' => 'https://www.esa.int/uno', 'position' => 0]);

        $html = $this->get(route('articolo', $article->slug))->assertOk()->getContent();

        $this->assertLessThan(
            strpos($html, 'Seconda'),
            strpos($html, 'Prima'),
            'La fonte con posizione minore deve comparire prima nell\'HTML.'
        );
    }

    public function test_accessed_date_is_shown_without_a_time_component(): void
    {
        $article = $this->publishedArticle();
        $article->sources()->create([
            'title' => 'Fonte',
            'url' => 'https://www.esa.int/uno',
            'accessed_on' => '2026-03-05',
        ]);

        $response = $this->get(route('articolo', $article->slug))->assertOk();

        $response->assertSee('Consultata il');
        $response->assertSee('datetime="2026-03-05"', false);
        $response->assertDontSee('2026-03-05 00:00');
    }

    public function test_a_source_without_any_safe_link_is_shown_as_plain_text_not_a_link(): void
    {
        $article = $this->publishedArticle();

        ArticleSource::withoutEvents(function () use ($article) {
            $article->sources()->create([
                'title' => 'Fonte senza riferimento sicuro',
                'url' => null,
                'doi' => null,
            ]);
        });

        $response = $this->get(route('articolo', $article->slug))->assertOk();

        $response->assertSee('Fonte senza riferimento sicuro');
        $response->assertSee('article-sources__link--plain', false);
    }
}
