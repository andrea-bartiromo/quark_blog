<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Copre il tag di feed discovery <link rel="alternate" type="application/
 * rss+xml"> nell'head condiviso — mancava del tutto (verificato prima della
 * modifica: il feed /feed.xml esisteva ed era raggiungibile e linkato nel
 * footer, ma senza questo tag i feed reader non potevano scoprirlo
 * automaticamente dalle pagine).
 */
class RssFeedDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private function expectedTag(): string
    {
        return '<link rel="alternate" type="application/rss+xml" title="'
            .config('laboratorio.name')
            .'" href="'.route('feed').'">';
    }

    public function test_home_page_declares_the_rss_alternate_link(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee($this->expectedTag(), false);
    }

    public function test_category_page_declares_the_rss_alternate_link(): void
    {
        $this->get('/categoria/intelligenza-artificiale')
            ->assertOk()
            ->assertSee($this->expectedTag(), false);
    }

    public function test_article_page_declares_the_rss_alternate_link(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo di prova RSS',
            'slug' => 'articolo-di-prova-rss-'.uniqid(),
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'intelligenza-artificiale',
            'cover_image' => 'copertina.jpg',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->get(route('articolo', $article->slug))
            ->assertOk()
            ->assertSee($this->expectedTag(), false);
    }

    public function test_feed_route_referenced_by_the_alternate_link_is_reachable(): void
    {
        $this->get(route('feed'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/rss+xml; charset=utf-8');
    }

    public function test_feed_channel_image_points_to_a_file_that_actually_exists_on_disk(): void
    {
        $xml = $this->get(route('feed'))->getContent();

        $this->assertMatchesRegularExpression('#<image><url>(.*?)</url>#', $xml);
        preg_match('#<image><url>(.*?)</url>#', $xml, $matches);

        $imageUrl = $matches[1];
        $relativePath = ltrim(parse_url($imageUrl, PHP_URL_PATH), '/');

        $this->assertStringNotContainsString('logo.png', $imageUrl);
        $this->assertFileExists(public_path($relativePath));
    }

    /**
     * Cantiere 9 (programma 100-cantieri Kairus): audit "categorie non
     * pubbliche isolate ovunque" — SeoController::feed() usa
     * Category::options(false) (non publicOptions()) per il tag
     * <category>, deliberatamente: qui è un'etichetta testuale su un
     * articolo già pubblicato (Article::published() filtra la query),
     * mai un link cliccabile. Una categoria disattivata DOPO la
     * pubblicazione dell'articolo deve continuare a mostrare il proprio
     * nome nel feed, non sparire né mostrare lo slug grezzo.
     */
    public function test_feed_category_label_survives_the_categorys_own_deactivation(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        Category::where('slug', 'intelligenza-artificiale')->delete();
        Category::create([
            'name' => 'Intelligenza Artificiale',
            'slug' => 'intelligenza-artificiale',
            'is_active' => true,
            'status' => Category::STATUS_PUBLISHED,
        ]);

        $article = Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo di prova feed categoria disattivata',
            'slug' => 'articolo-feed-categoria-disattivata-'.uniqid(),
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'intelligenza-artificiale',
            'status' => 'published',
            'published_at' => now(),
        ]);

        Category::where('slug', 'intelligenza-artificiale')->update(['is_active' => false]);

        $xml = $this->get(route('feed'))->getContent();

        $this->assertStringContainsString('<category>Intelligenza Artificiale</category>', $xml);
        $this->assertStringContainsString($article->title, $xml);
    }

    /**
     * Come sopra, per news-sitemap.xml (<news:genres>): stessa
     * convenzione di etichetta testuale, mai un link.
     */
    public function test_news_sitemap_genre_label_survives_the_categorys_own_deactivation(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        Category::where('slug', 'intelligenza-artificiale')->delete();
        Category::create([
            'name' => 'Intelligenza Artificiale',
            'slug' => 'intelligenza-artificiale',
            'is_active' => true,
            'status' => Category::STATUS_PUBLISHED,
        ]);

        Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo di prova news-sitemap categoria disattivata',
            'slug' => 'articolo-news-sitemap-categoria-disattivata-'.uniqid(),
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'intelligenza-artificiale',
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);

        Category::where('slug', 'intelligenza-artificiale')->update(['is_active' => false]);

        $xml = $this->get(route('news-sitemap'))->getContent();

        $this->assertStringContainsString('<news:genres>Intelligenza Artificiale</news:genres>', $xml);
    }
}
