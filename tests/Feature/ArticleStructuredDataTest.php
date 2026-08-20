<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Copre il secondo intervento dell'audit dati strutturati (Fase 3):
 * NewsArticle sulla pagina articolo. BreadcrumbList, Person standalone e
 * CollectionPage restano commit separati successivi.
 *
 * Tutte le asserzioni decodificano il JSON-LD ed esaminano la struttura
 * risultante, non il markup: restano valide indipendentemente da
 * indentazione/formattazione del blocco <script>.
 */
class ArticleStructuredDataTest extends TestCase
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
    private function newsArticleNodeFor(Article $article): array
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

        $node = collect($decoded['@graph'] ?? [])->first(fn ($item) => ($item['@type'] ?? null) === 'NewsArticle');
        $this->assertIsArray($node, 'Nessun nodo @type=NewsArticle trovato nel @graph.');

        return $node;
    }

    public function test_article_page_includes_a_valid_json_ld_newsarticle_node(): void
    {
        $article = $this->publishedArticle();

        $node = $this->newsArticleNodeFor($article);

        $this->assertSame('NewsArticle', $node['@type']);
    }

    public function test_headline_matches_the_visible_editorial_title_not_meta_title(): void
    {
        $article = $this->publishedArticle([
            'title' => 'Titolo editoriale visibile con più di cento caratteri per verificare che non venga troncato in nessun modo',
            'seo_title' => 'Titolo SEO completamente diverso, usato solo per il tag <title>',
        ]);

        $node = $this->newsArticleNodeFor($article);

        $this->assertSame($article->title, $node['headline']);
        $this->assertNotSame($article->metaTitle(), $node['headline']);
        $this->assertSame(mb_strlen($article->title), mb_strlen($node['headline']));
    }

    public function test_description_matches_meta_description_fallback_chain(): void
    {
        $article = $this->publishedArticle(['excerpt' => 'Sommario usato come description']);

        $node = $this->newsArticleNodeFor($article);

        $this->assertSame($article->metaDescription(), $node['description']);
    }

    public function test_main_entity_of_page_matches_canonical_url(): void
    {
        $article = $this->publishedArticle(['canonical_url' => 'https://example.com/originale']);

        $node = $this->newsArticleNodeFor($article);

        $this->assertSame($article->metaCanonicalUrl(), $node['mainEntityOfPage']);
        $this->assertSame('https://example.com/originale', $node['mainEntityOfPage']);
    }

    public function test_date_published_is_present_and_iso8601(): void
    {
        $article = $this->publishedArticle();

        $node = $this->newsArticleNodeFor($article);

        $this->assertSame($article->published_at->toIso8601String(), $node['datePublished']);
    }

    public function test_date_modified_is_not_published_because_updated_at_is_not_a_reliable_editorial_signal(): void
    {
        $article = $this->publishedArticle();

        $node = $this->newsArticleNodeFor($article);

        $this->assertArrayNotHasKey('dateModified', $node);
    }

    public function test_article_section_uses_the_readable_category_label(): void
    {
        $article = $this->publishedArticle(['category' => 'energia']);

        $node = $this->newsArticleNodeFor($article);

        $this->assertSame('Energia & Clima', $node['articleSection']);
    }

    public function test_image_uses_the_real_cover_when_present(): void
    {
        $article = $this->publishedArticle(['cover_image' => 'copertina-reale.jpg']);

        $node = $this->newsArticleNodeFor($article);

        $this->assertSame('ImageObject', $node['image']['@type']);
        $this->assertSame(asset('assets/img/copertina-reale.jpg'), $node['image']['url']);
        $this->assertArrayNotHasKey('width', $node['image']);
        $this->assertArrayNotHasKey('height', $node['image']);
    }

    public function test_image_falls_back_to_the_global_raster_default_and_never_to_the_svg_placeholder(): void
    {
        $article = $this->publishedArticle(['cover_image' => null]);

        $node = $this->newsArticleNodeFor($article);

        $this->assertSame(asset(config('laboratorio.default_share_image')), $node['image']['url']);
        $this->assertStringEndsNotWith('.svg', $node['image']['url']);
        $this->assertStringEndsNotWith('hero-placeholder.svg', $node['image']['url']);
    }

    public function test_author_is_a_minimal_person_without_image_or_sameas(): void
    {
        $author = $this->author();
        $author->update(['name' => 'Autrice di Prova', 'twitter' => '@autrice_prova']);

        $article = $this->publishedArticle(['user_id' => $author->id]);

        $node = $this->newsArticleNodeFor($article);

        $this->assertSame('Person', $node['author']['@type']);
        $this->assertSame('Autrice di Prova', $node['author']['name']);
        $this->assertSame(route('autore', $author), $node['author']['url']);
        $this->assertArrayNotHasKey('image', $node['author']);
        $this->assertArrayNotHasKey('sameAs', $node['author']);
        $this->assertCount(3, $node['author']);
    }

    public function test_publisher_is_a_complete_organization_with_the_stable_id_shared_with_the_home_page(): void
    {
        $article = $this->publishedArticle();

        $node = $this->newsArticleNodeFor($article);
        $publisher = $node['publisher'];

        $this->assertSame('Organization', $publisher['@type']);
        $this->assertSame(url('/').'/#organization', $publisher['@id']);
        $this->assertSame(config('laboratorio.name'), $publisher['name']);
        $this->assertSame(url('/'), $publisher['url']);
        $this->assertSame('ImageObject', $publisher['logo']['@type']);
        $this->assertSame(asset('assets/icons/icon-512.png'), $publisher['logo']['url']);
        $this->assertSame(512, $publisher['logo']['width']);
        $this->assertSame(512, $publisher['logo']['height']);

        // Stesso identificatore dichiarato sulla homepage: verifica diretta
        // che le due pagine condividano davvero la stessa identità, non solo
        // per costruzione ma per confronto tra le due risposte.
        $homeHtml = $this->get(route('home'))->getContent();
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $homeHtml, $homeMatches);
        $homeData = json_decode($homeMatches[1], true);
        $homeOrganization = collect($homeData['@graph'])->first(fn ($item) => ($item['@type'] ?? null) === 'Organization');

        $this->assertSame($homeOrganization['@id'], $publisher['@id']);
    }

    public function test_citation_is_not_derived_from_unstructured_primary_sources(): void
    {
        $article = $this->publishedArticle(['primary_sources' => 'terna.it, ANSA 21/01/2026']);

        $node = $this->newsArticleNodeFor($article);

        $this->assertArrayNotHasKey('citation', $node);
    }

    public function test_json_ld_cannot_be_broken_out_of_by_a_title_containing_a_closing_script_tag(): void
    {
        $article = $this->publishedArticle([
            'title' => 'Titolo con </script><script>alert(1)</script> iniettato',
        ]);

        $html = $this->get(route('articolo', $article->slug))->assertOk()->getContent();

        $this->assertStringNotContainsString('</script><script>alert(1)</script>', $html);

        $node = $this->newsArticleNodeFor($article);
        $this->assertSame($article->title, $node['headline']);
    }

    public function test_meta_tags_do_not_expose_article_modified_time_from_the_unreliable_updated_at_timestamp(): void
    {
        // Stessa motivazione già coperta per lastmod/dateModified (Fase 5):
        // updated_at viene toccato anche da una semplice pageview e dal
        // flusso di verifica editoriale, quindi non è un segnale affidabile
        // di modifica editoriale del contenuto.
        $article = $this->publishedArticle();

        $html = $this->get(route('articolo', $article->slug))->assertOk()->getContent();

        $this->assertStringNotContainsString('article:modified_time', $html);
    }

    public function test_structured_data_only_appears_on_article_pages(): void
    {
        $article = $this->publishedArticle();

        $this->get(route('articolo', $article->slug))->assertOk()->assertSee('application/ld+json', false);

        $this->get(route('home'))->assertOk(); // ha il proprio blocco Organization/WebSite, non NewsArticle
        $homeHtml = $this->get(route('home'))->getContent();
        $this->assertStringNotContainsString('NewsArticle', $homeHtml);

        // /notizie ha il proprio blocco CollectionPage (Fase 5), non NewsArticle.
        $notizieHtml = $this->get(route('notizie'))->assertOk()->getContent();
        $this->assertStringNotContainsString('NewsArticle', $notizieHtml);

        $this->get('/turing')->assertOk()->assertDontSee('application/ld+json', false);
    }
}
