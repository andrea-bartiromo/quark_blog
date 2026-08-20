<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Copre il quarto intervento dell'audit dati strutturati (Fase 5):
 * CollectionPage sulle pagine archivio (/notizie e /categoria/{slug}).
 * Nessun ItemList: gli articoli elencati hanno già il proprio nodo
 * NewsArticle sulla propria pagina (vedi partial condiviso per i motivi).
 *
 * Tutte le asserzioni decodificano il JSON-LD ed esaminano la struttura
 * risultante, non il markup.
 */
class CollectionPageStructuredDataTest extends TestCase
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
     * @return array<string, mixed>
     */
    private function collectionPageNodeFrom(string $html): array
    {
        $this->assertMatchesRegularExpression(
            '#<script type="application/ld\+json">(.*?)</script>#s',
            $html,
            'Nessun blocco <script type="application/ld+json"> trovato.'
        );

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

        $decoded = json_decode($matches[1], true);
        $this->assertIsArray($decoded, 'Il blocco JSON-LD non è JSON valido: '.json_last_error_msg());

        $node = collect($decoded['@graph'] ?? [])->first(fn ($item) => ($item['@type'] ?? null) === 'CollectionPage');
        $this->assertIsArray($node, 'Nessun nodo @type=CollectionPage trovato nel @graph.');

        return $node;
    }

    public function test_notizie_page_includes_a_valid_collection_page_node(): void
    {
        $this->publishedArticle();

        $html = $this->get(route('notizie'))->getContent();
        $node = $this->collectionPageNodeFrom($html);

        $this->assertSame('CollectionPage', $node['@type']);
        $this->assertSame('Tutti gli articoli', $node['name']);
        $this->assertSame(
            'Tutti gli articoli di Kairus: scienza, tecnologia e innovazione spiegate in modo chiaro, visuale e moderno.',
            $node['description']
        );
        $this->assertSame(route('notizie'), $node['url']);
        $this->assertSame(route('notizie').'#collectionpage', $node['@id']);
    }

    public function test_category_page_uses_the_category_label_as_name(): void
    {
        $this->publishedArticle(['category' => 'energia']);

        $html = $this->get(route('categoria', 'energia'))->getContent();
        $node = $this->collectionPageNodeFrom($html);

        $this->assertSame('Energia & Clima', $node['name']);
        $this->assertSame(route('categoria', 'energia'), $node['url']);
        $this->assertSame(route('categoria', 'energia').'#collectionpage', $node['@id']);
    }

    public function test_is_part_of_references_the_same_website_id_published_on_the_home_page(): void
    {
        $this->publishedArticle();

        $html = $this->get(route('notizie'))->getContent();
        $node = $this->collectionPageNodeFrom($html);

        $this->assertCount(1, $node['isPartOf']);

        $homeHtml = $this->get(route('home'))->getContent();
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $homeHtml, $homeMatches);
        $homeData = json_decode($homeMatches[1], true);
        $homeWebsite = collect($homeData['@graph'])->first(fn ($item) => ($item['@type'] ?? null) === 'WebSite');

        $this->assertSame($homeWebsite['@id'], $node['isPartOf']['@id']);
    }

    public function test_unrecognized_category_returns_404_without_any_structured_data(): void
    {
        $this->get(route('categoria', 'categoria-inesistente'))->assertNotFound();
    }

    public function test_collection_page_is_still_valid_when_the_archive_has_no_articles(): void
    {
        $html = $this->get(route('notizie'))->getContent();
        $node = $this->collectionPageNodeFrom($html);

        $this->assertSame('CollectionPage', $node['@type']);
        $this->assertArrayNotHasKey('numberOfItems', $node);
    }

    public function test_collection_page_is_still_valid_for_a_category_with_zero_articles(): void
    {
        $html = $this->get(route('categoria', 'salute'))->getContent();
        $node = $this->collectionPageNodeFrom($html);

        $this->assertSame('Salute & Biotech', $node['name']);
    }

    public function test_json_ld_cannot_be_broken_out_of_by_a_category_name_containing_a_closing_script_tag(): void
    {
        Category::create([
            'name' => 'Fisica </script><script>alert(1)</script>',
            'slug' => 'fisica-iniettata',
            'is_active' => true,
        ]);
        $this->publishedArticle(['category' => 'fisica-iniettata']);

        $html = $this->get(route('categoria', 'fisica-iniettata'))->assertOk()->getContent();

        $this->assertStringNotContainsString('</script><script>alert(1)</script>', $html);
    }

    public function test_page_two_describes_the_url_actually_served_not_page_one(): void
    {
        for ($i = 0; $i < 13; $i++) {
            $this->publishedArticle();
        }

        $pageOneHtml = $this->get(route('notizie'))->getContent();
        $pageOneNode = $this->collectionPageNodeFrom($pageOneHtml);

        $pageTwoHtml = $this->get(route('notizie', ['page' => 2]))->getContent();
        $pageTwoNode = $this->collectionPageNodeFrom($pageTwoHtml);

        $this->assertStringEndsNotWith('?page=2', $pageOneNode['url']);
        $this->assertStringEndsWith('?page=2', $pageTwoNode['url']);
        $this->assertNotSame($pageOneNode['url'], $pageTwoNode['url']);
        $this->assertNotSame($pageOneNode['@id'], $pageTwoNode['@id']);
    }

    public function test_no_item_list_or_news_article_node_is_published_on_archive_pages(): void
    {
        $this->publishedArticle();

        $html = $this->get(route('notizie'))->getContent();

        $this->assertStringNotContainsString('ItemList', $html);
        $this->assertStringNotContainsString('NewsArticle', $html);
    }

    public function test_collection_page_only_appears_on_archive_pages(): void
    {
        $article = $this->publishedArticle();

        $this->get(route('notizie'))->assertOk()->assertSee('CollectionPage', false);
        $this->get(route('categoria', 'intelligenza-artificiale'))->assertOk()->assertSee('CollectionPage', false);

        $this->get(route('home'))->assertOk()->assertDontSee('CollectionPage', false);
        $this->get(route('articolo', $article->slug))->assertOk()->assertDontSee('CollectionPage', false);
        $this->get('/turing')->assertOk()->assertDontSee('CollectionPage', false);
    }
}
