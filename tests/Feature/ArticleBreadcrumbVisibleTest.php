<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Copre il breadcrumb visibile della pagina articolo, companion del
 * BreadcrumbList JSON-LD già coperto da ArticleBreadcrumbStructuredDataTest.
 *
 * Le asserzioni sul markup usano DOMDocument/DOMXPath sul solo elemento
 * <nav class="article-premium__breadcrumb">, non l'intera pagina: l'hero
 * contiene già un link categoria preesistente e potenzialmente rotto, fuori
 * scope per questo intervento, che non deve interferire con queste verifiche.
 */
class ArticleBreadcrumbVisibleTest extends TestCase
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

    private function pageHtmlFor(Article $article): string
    {
        return $this->get(route('articolo', $article->slug))->getContent();
    }

    private function breadcrumbNavHtmlFrom(string $html): string
    {
        $this->assertMatchesRegularExpression(
            '#<nav class="article-premium__breadcrumb"[^>]*>.*?</nav>#s',
            $html,
            'Nessun <nav class="article-premium__breadcrumb"> trovato sulla pagina articolo.'
        );

        preg_match('#<nav class="article-premium__breadcrumb"[^>]*>.*?</nav>#s', $html, $matches);

        return $matches[0];
    }

    /**
     * @return array<int, array{name: string, href: ?string, current: bool}>
     */
    private function breadcrumbItemsFrom(string $navHtml): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$navHtml);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $items = [];

        foreach ($xpath->query('//li') as $li) {
            $link = $xpath->query('.//a', $li)->item(0);
            $current = $xpath->query('.//span[@aria-current="page"]', $li)->item(0);

            if ($link) {
                $items[] = [
                    'name' => trim($link->textContent),
                    'href' => $link->getAttribute('href'),
                    'current' => false,
                ];
            } elseif ($current) {
                $items[] = [
                    'name' => trim($current->textContent),
                    'href' => null,
                    'current' => true,
                ];
            }
        }

        return $items;
    }

    public function test_breadcrumb_nav_has_the_expected_landmark_and_list_structure(): void
    {
        $article = $this->publishedArticle();

        $navHtml = $this->breadcrumbNavHtmlFrom($this->pageHtmlFor($article));

        $this->assertStringContainsString('aria-label="Percorso di navigazione"', $navHtml);
        $this->assertMatchesRegularExpression('#<ol>.*</ol>#s', $navHtml);
    }

    public function test_breadcrumb_shows_three_levels_when_the_category_is_recognized(): void
    {
        $article = $this->publishedArticle(['category' => 'energia']);

        $navHtml = $this->breadcrumbNavHtmlFrom($this->pageHtmlFor($article));
        $items = $this->breadcrumbItemsFrom($navHtml);

        $this->assertCount(3, $items);

        $this->assertSame('Home', $items[0]['name']);
        $this->assertSame(route('home'), $items[0]['href']);
        $this->assertFalse($items[0]['current']);

        $this->assertSame('Energia & Clima', $items[1]['name']);
        $this->assertSame(route('categoria', 'energia'), $items[1]['href']);
        $this->assertFalse($items[1]['current']);

        $this->assertSame($article->title, $items[2]['name']);
        $this->assertNull($items[2]['href']);
        $this->assertTrue($items[2]['current']);
    }

    public function test_breadcrumb_falls_back_to_two_levels_without_a_category_link_when_unrecognized(): void
    {
        $article = $this->publishedArticle(['category' => 'categoria-inesistente']);

        $navHtml = $this->breadcrumbNavHtmlFrom($this->pageHtmlFor($article));
        $items = $this->breadcrumbItemsFrom($navHtml);

        $this->assertCount(2, $items);

        $this->assertSame('Home', $items[0]['name']);
        $this->assertFalse($items[0]['current']);

        $this->assertSame($article->title, $items[1]['name']);
        $this->assertTrue($items[1]['current']);

        // Nessun link verso /categoria/... dentro il solo <nav> del
        // breadcrumb (l'hero ha un link categoria preesistente, fuori scope).
        $this->assertStringNotContainsString('/categoria/', $navHtml);
    }

    public function test_the_full_editorial_title_is_rendered_without_php_truncation(): void
    {
        $longTitle = 'Titolo editoriale molto lungo usato per verificare che nel breadcrumb non venga applicato alcun troncamento lato PHP, restando gestito soltanto via wrapping CSS';

        $article = $this->publishedArticle(['title' => $longTitle]);

        $navHtml = $this->breadcrumbNavHtmlFrom($this->pageHtmlFor($article));
        $items = $this->breadcrumbItemsFrom($navHtml);

        $this->assertSame($longTitle, collect($items)->last()['name']);
        $this->assertStringNotContainsString('…', $navHtml);
    }

    public function test_visible_breadcrumb_is_consistent_with_the_json_ld_breadcrumb_list(): void
    {
        $article = $this->publishedArticle(['category' => 'salute']);

        $html = $this->pageHtmlFor($article);

        $visibleItems = $this->breadcrumbItemsFrom($this->breadcrumbNavHtmlFrom($html));

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
        $decoded = json_decode($matches[1], true);
        $jsonLdItems = collect($decoded['@graph'])
            ->first(fn ($item) => ($item['@type'] ?? null) === 'BreadcrumbList')['itemListElement'];

        $this->assertCount(count($jsonLdItems), $visibleItems);

        foreach ($visibleItems as $position => $visible) {
            $this->assertSame($visible['name'], $jsonLdItems[$position]['name']);

            if (! $visible['current']) {
                $this->assertSame($visible['href'], $jsonLdItems[$position]['item']);
            }
        }
    }

    public function test_breadcrumb_only_appears_on_article_pages(): void
    {
        $article = $this->publishedArticle();

        $this->get(route('articolo', $article->slug))
            ->assertOk()
            ->assertSee('article-premium__breadcrumb', false);

        $this->get(route('home'))->assertOk()->assertDontSee('article-premium__breadcrumb', false);
        $this->get(route('notizie'))->assertOk()->assertDontSee('article-premium__breadcrumb', false);
        $this->get('/turing')->assertOk()->assertDontSee('article-premium__breadcrumb', false);
    }
}
