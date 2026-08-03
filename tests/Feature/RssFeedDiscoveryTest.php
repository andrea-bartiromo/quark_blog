<?php

namespace Tests\Feature;

use App\Models\Article;
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
}
