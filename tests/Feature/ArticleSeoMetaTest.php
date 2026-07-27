<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleSeoMetaTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function publishedArticle(User $author, array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $author->id,
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

    // 1. Fallback: SEO title -> Article title
    public function test_meta_title_falls_back_to_article_title_when_seo_title_is_empty(): void
    {
        $article = $this->publishedArticle($this->author(), [
            'title' => 'Titolo di fallback',
            'seo_title' => null,
        ]);

        $this->assertSame('Titolo di fallback', $article->metaTitle());

        $response = $this->get(route('articolo', $article->slug));
        $response->assertOk();
        $response->assertSee('<title>Titolo di fallback — '.config('laboratorio.name').'</title>', false);
    }

    public function test_meta_title_uses_seo_title_when_set(): void
    {
        $article = $this->publishedArticle($this->author(), [
            'title' => 'Titolo originale',
            'seo_title' => 'Titolo SEO personalizzato',
        ]);

        $this->assertSame('Titolo SEO personalizzato', $article->metaTitle());

        $response = $this->get(route('articolo', $article->slug));
        $response->assertOk();
        $response->assertSee('<title>Titolo SEO personalizzato — '.config('laboratorio.name').'</title>', false);
    }

    // 2. Fallback: SEO description -> excerpt -> prime 160 lettere del corpo
    public function test_meta_description_uses_seo_description_when_set(): void
    {
        $article = $this->publishedArticle($this->author(), [
            'seo_description' => 'Descrizione SEO personalizzata',
            'excerpt' => 'Sommario che non deve essere usato',
        ]);

        $this->assertSame('Descrizione SEO personalizzata', $article->metaDescription());
    }

    public function test_meta_description_falls_back_to_excerpt_when_seo_description_is_empty(): void
    {
        $article = $this->publishedArticle($this->author(), [
            'seo_description' => null,
            'excerpt' => 'Sommario usato come fallback',
        ]);

        $this->assertSame('Sommario usato come fallback', $article->metaDescription());
    }

    public function test_meta_description_falls_back_to_first_160_characters_of_body_when_seo_description_and_excerpt_are_empty(): void
    {
        $plainText = 'Questo è un corpo di articolo molto lungo, usato per verificare che il fallback della meta description prenda esattamente le prime centosessanta lettere del contenuto quando sia il campo SEO description sia il sommario sono vuoti, senza troncare a metà una parola in modo scorretto o lasciare tag HTML visibili nel testo estratto.';

        $article = $this->publishedArticle($this->author(), [
            'seo_description' => null,
            'excerpt' => null,
            'body' => '<p>'.$plainText.'</p>',
        ]);

        $expected = mb_substr($plainText, 0, 160);
        $this->assertSame($expected, $article->metaDescription());
        $this->assertSame(160, mb_strlen($article->metaDescription()));
        $this->assertStringNotContainsString('<p>', $article->metaDescription());
    }

    // 3. Canonical
    public function test_canonical_defaults_to_the_article_url(): void
    {
        $article = $this->publishedArticle($this->author(), ['canonical_url' => null]);

        $this->assertSame(route('articolo', $article->slug), $article->metaCanonicalUrl());

        $response = $this->get(route('articolo', $article->slug));
        $response->assertOk();
        $response->assertSee('<link rel="canonical" href="'.route('articolo', $article->slug).'">', false);
    }

    public function test_canonical_uses_the_custom_value_when_set(): void
    {
        $article = $this->publishedArticle($this->author(), [
            'canonical_url' => 'https://example.com/originale',
        ]);

        $this->assertSame('https://example.com/originale', $article->metaCanonicalUrl());

        $response = $this->get(route('articolo', $article->slug));
        $response->assertOk();
        $response->assertSee('<link rel="canonical" href="https://example.com/originale">', false);
    }

    // 4. Robots
    public function test_robots_meta_defaults_to_index_follow(): void
    {
        $article = $this->publishedArticle($this->author(), ['robots' => null]);

        $this->assertSame('index,follow', $article->metaRobots());

        $response = $this->get(route('articolo', $article->slug));
        $response->assertOk();
        $response->assertSee('<meta name="robots" content="index,follow">', false);
    }

    public function test_robots_meta_uses_the_custom_value_when_set(): void
    {
        $article = $this->publishedArticle($this->author(), ['robots' => 'noindex,nofollow']);

        $response = $this->get(route('articolo', $article->slug));
        $response->assertOk();
        $response->assertSee('<meta name="robots" content="noindex,nofollow">', false);
    }

    // 5. Open Graph
    public function test_og_tags_fall_back_to_seo_and_cover_values(): void
    {
        $article = $this->publishedArticle($this->author(), [
            'title' => 'Titolo per Open Graph',
            'cover_image' => 'copertina-og.jpg',
            'og_title' => null,
            'og_description' => null,
            'og_image' => null,
        ]);

        $response = $this->get(route('articolo', $article->slug));
        $response->assertOk();
        $response->assertSee('<meta property="og:title" content="Titolo per Open Graph">', false);
        $response->assertSee('<meta property="og:image" content="'.asset('assets/img/copertina-og.jpg').'">', false);
    }

    public function test_og_tags_use_explicit_overrides_when_set(): void
    {
        $article = $this->publishedArticle($this->author(), [
            'og_title' => 'Titolo Open Graph personalizzato',
            'og_description' => 'Descrizione Open Graph personalizzata',
            'og_image' => 'og-custom.jpg',
        ]);

        $response = $this->get(route('articolo', $article->slug));
        $response->assertOk();
        $response->assertSee('<meta property="og:title" content="Titolo Open Graph personalizzato">', false);
        $response->assertSee('<meta property="og:description" content="Descrizione Open Graph personalizzata">', false);
        $response->assertSee('<meta property="og:image" content="'.asset('assets/img/og-custom.jpg').'">', false);
    }

    public function test_og_image_falls_back_to_placeholder_when_no_cover_image_is_set(): void
    {
        $article = $this->publishedArticle($this->author(), [
            'cover_image' => null,
            'og_image' => null,
        ]);

        $response = $this->get(route('articolo', $article->slug));
        $response->assertOk();
        $response->assertSee('<meta property="og:image" content="'.asset('assets/img/hero-placeholder.svg').'">', false);
    }

    // 6. Twitter Card
    public function test_twitter_tags_fall_back_to_seo_and_cover_values(): void
    {
        $article = $this->publishedArticle($this->author(), [
            'title' => 'Titolo per Twitter',
            'cover_image' => 'copertina-twitter.jpg',
            'twitter_title' => null,
            'twitter_description' => null,
            'twitter_image' => null,
        ]);

        $response = $this->get(route('articolo', $article->slug));
        $response->assertOk();
        $response->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
        $response->assertSee('<meta name="twitter:title" content="Titolo per Twitter">', false);
        $response->assertSee('<meta name="twitter:image" content="'.asset('assets/img/copertina-twitter.jpg').'">', false);
    }

    public function test_twitter_tags_use_explicit_overrides_when_set(): void
    {
        $article = $this->publishedArticle($this->author(), [
            'twitter_title' => 'Titolo Twitter personalizzato',
            'twitter_description' => 'Descrizione Twitter personalizzata',
            'twitter_image' => 'twitter-custom.jpg',
        ]);

        $response = $this->get(route('articolo', $article->slug));
        $response->assertOk();
        $response->assertSee('<meta name="twitter:title" content="Titolo Twitter personalizzato">', false);
        $response->assertSee('<meta name="twitter:description" content="Descrizione Twitter personalizzata">', false);
        $response->assertSee('<meta name="twitter:image" content="'.asset('assets/img/twitter-custom.jpg').'">', false);
    }

    // 7. Nessun doppio escaping HTML nei meta tag
    public function test_meta_tags_do_not_double_escape_special_characters(): void
    {
        $article = $this->publishedArticle($this->author(), [
            'title' => 'Prova & Test "SEO"',
        ]);

        $response = $this->get(route('articolo', $article->slug));
        $response->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('Prova &amp; Test &quot;SEO&quot;', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('&amp;quot;', $html);
    }

    // 8. Regressione: le pagine non-articolo non sono influenzate
    public function test_home_page_og_title_still_mirrors_the_page_title(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
        $response->assertDontSee('<meta property="og:image"', false);
    }
}
