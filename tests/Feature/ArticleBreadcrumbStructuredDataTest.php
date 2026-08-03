<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Copre il terzo intervento dell'audit dati strutturati (Fase 3):
 * BreadcrumbList sulla pagina articolo, nello stesso @graph del NewsArticle.
 * Nessun breadcrumb visibile viene introdotto in questo commit (FASE 4,
 * intervento separato): qui si verifica solo il JSON-LD.
 *
 * Tutte le asserzioni decodificano il JSON-LD ed esaminano la struttura
 * risultante, non il markup.
 */
class ArticleBreadcrumbStructuredDataTest extends TestCase
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function graphFor(Article $article): array
    {
        $html = $this->get(route('articolo', $article->slug))->getContent();

        $this->assertMatchesRegularExpression(
            '#<script type="application/ld\+json">(.*?)</script>#s',
            $html,
            'Nessun blocco <script type="application/ld+json"> trovato sulla pagina articolo.'
        );

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

        $decoded = json_decode($matches[1], true);
        $this->assertIsArray($decoded, 'Il blocco JSON-LD non è JSON valido: '.json_last_error_msg());

        return $decoded['@graph'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function breadcrumbNodeFor(Article $article): array
    {
        $graph = $this->graphFor($article);

        $node = collect($graph)->first(fn ($item) => ($item['@type'] ?? null) === 'BreadcrumbList');
        $this->assertIsArray($node, 'Nessun nodo @type=BreadcrumbList trovato nel @graph.');

        return $node;
    }

    public function test_breadcrumb_list_node_coexists_with_the_newsarticle_node_in_the_same_graph(): void
    {
        $article = $this->publishedArticle();

        $graph = $this->graphFor($article);
        $types = collect($graph)->pluck('@type');

        $this->assertTrue($types->contains('NewsArticle'));
        $this->assertTrue($types->contains('BreadcrumbList'));
        $this->assertCount(2, $graph);
    }

    public function test_breadcrumb_has_three_levels_when_the_category_is_recognized(): void
    {
        $article = $this->publishedArticle(['category' => 'energia']);

        $items = $this->breadcrumbNodeFor($article)['itemListElement'];

        $this->assertCount(3, $items);

        $this->assertSame(1, $items[0]['position']);
        $this->assertSame('Home', $items[0]['name']);
        $this->assertSame(url('/'), $items[0]['item']);

        $this->assertSame(2, $items[1]['position']);
        $this->assertSame('Energia & Clima', $items[1]['name']);
        $this->assertSame(route('categoria', 'energia'), $items[1]['item']);

        $this->assertSame(3, $items[2]['position']);
        $this->assertSame($article->title, $items[2]['name']);
        $this->assertSame($article->metaCanonicalUrl(), $items[2]['item']);
    }

    public function test_breadcrumb_falls_back_to_two_levels_without_gaps_when_the_category_is_unrecognized(): void
    {
        $article = $this->publishedArticle(['category' => 'categoria-inesistente']);

        $items = $this->breadcrumbNodeFor($article)['itemListElement'];

        $this->assertCount(2, $items);

        $this->assertSame(1, $items[0]['position']);
        $this->assertSame('Home', $items[0]['name']);
        $this->assertSame(url('/'), $items[0]['item']);

        $this->assertSame(2, $items[1]['position']);
        $this->assertSame($article->title, $items[1]['name']);
        $this->assertSame($article->metaCanonicalUrl(), $items[1]['item']);

        // Nessuna posizione deve saltare (es. 1,3) e nessun ListItem deve
        // referenziare la categoria non riconosciuta.
        $positions = collect($items)->pluck('position')->all();
        $this->assertSame([1, 2], $positions);
        $this->assertStringNotContainsString('categoria-inesistente', json_encode($items));
    }

    public function test_last_breadcrumb_item_explicitly_includes_the_canonical_url(): void
    {
        $article = $this->publishedArticle(['canonical_url' => 'https://example.com/originale']);

        $last = collect($this->breadcrumbNodeFor($article)['itemListElement'])->last();

        $this->assertArrayHasKey('item', $last);
        $this->assertSame($article->metaCanonicalUrl(), $last['item']);
        $this->assertSame('https://example.com/originale', $last['item']);
    }

    public function test_breadcrumb_category_label_is_consistent_with_the_newsarticle_article_section(): void
    {
        $article = $this->publishedArticle(['category' => 'salute']);

        $graph = $this->graphFor($article);
        $newsArticle = collect($graph)->first(fn ($item) => ($item['@type'] ?? null) === 'NewsArticle');
        $breadcrumb = collect($graph)->first(fn ($item) => ($item['@type'] ?? null) === 'BreadcrumbList');

        $categoryItem = collect($breadcrumb['itemListElement'])->firstWhere('position', 2);

        $this->assertSame($newsArticle['articleSection'], $categoryItem['name']);
    }

    public function test_breadcrumb_list_only_appears_on_article_pages(): void
    {
        $article = $this->publishedArticle();

        $this->get(route('articolo', $article->slug))->assertOk()->assertSee('BreadcrumbList', false);

        $homeHtml = $this->get(route('home'))->getContent();
        $this->assertStringNotContainsString('BreadcrumbList', $homeHtml);

        $this->get(route('notizie'))->assertOk()->assertDontSee('BreadcrumbList', false);
        $this->get('/turing')->assertOk()->assertDontSee('BreadcrumbList', false);
    }
}
