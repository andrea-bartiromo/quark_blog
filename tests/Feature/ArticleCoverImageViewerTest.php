<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleCoverImageViewerTest extends TestCase
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
            'body' => 'Corpo articolo di prova.',
            'category' => 'intelligenza-artificiale',
            'cover_image' => 'copertina.jpg',
            'status' => 'published',
            'published_at' => now(),
        ], $overrides));
    }

    public function test_article_page_includes_the_viewer_stylesheet(): void
    {
        $article = $this->publishedArticle($this->author());

        $this->get(route('articolo', $article->slug))
            ->assertOk()
            ->assertSee('css/media-lightbox.css', false);
    }

    public function test_cover_has_a_lightbox_trigger_pointing_at_the_same_cover_url(): void
    {
        $author = $this->author();
        $article = $this->publishedArticle($author, ['cover_image' => 'copertina-unica.jpg']);

        $html = $this->get(route('articolo', $article->slug))->getContent();
        $expectedUrl = asset('assets/img/copertina-unica.jpg');

        // La <img> ritagliata dell'hero e la <img> del dialog puntano
        // esattamente alla stessa risorsa: nessuna seconda immagine pesante
        // caricata apposta per il lightbox.
        preg_match('/<header class="article-premium__hero">\s*<img src="([^"]+)"/', $html, $heroImg);
        preg_match('/<img\b[^>]*src="([^"]+)"[^>]*data-media-viewer-image/s', $html, $dialogImg);

        $this->assertNotEmpty($heroImg, 'La <img> della hero non e\' stata trovata.');
        $this->assertNotEmpty($dialogImg, 'La <img> del dialog non e\' stata trovata.');
        $this->assertSame($expectedUrl, $heroImg[1]);
        $this->assertSame($heroImg[1], $dialogImg[1]);

        $this->assertStringContainsString('data-media-viewer-target=', $html);
    }

    public function test_trigger_degrades_to_a_real_link_to_the_image_without_javascript(): void
    {
        $author = $this->author();
        $article = $this->publishedArticle($author, ['cover_image' => 'copertina-fallback.jpg']);

        $html = $this->get(route('articolo', $article->slug))->getContent();
        $expectedUrl = asset('assets/img/copertina-fallback.jpg');

        $this->assertMatchesRegularExpression(
            '/<a\b[^>]*href="'.preg_quote($expectedUrl, '/').'"[^>]*data-media-viewer-target=/',
            $html
        );
    }

    public function test_lightbox_shows_cover_metadata_only_when_present(): void
    {
        $author = $this->author();

        $withoutMeta = $this->publishedArticle($author, ['title' => 'Senza metadati copertina lightbox']);
        $response = $this->get(route('articolo', $withoutMeta->slug));
        $response->assertOk();
        $response->assertDontSee('media-viewer__dl', false);

        $withMeta = $this->publishedArticle($author, [
            'title' => 'Con metadati copertina lightbox',
            'cover_caption' => 'Vista aerea del laboratorio',
            'cover_credit' => 'Foto di Jane Doe',
            'cover_source' => 'Wikimedia Commons',
            'cover_source_url' => 'https://commons.wikimedia.org/example',
            'cover_license' => 'CC BY-SA 4.0',
        ]);
        $response = $this->get(route('articolo', $withMeta->slug));
        $response->assertOk();
        $response->assertSee('media-viewer__dl', false);
        $response->assertSee('Vista aerea del laboratorio', false);
        $response->assertSee('Foto di Jane Doe', false);
        $response->assertSee('Wikimedia Commons', false);
        $response->assertSee('CC BY-SA 4.0', false);
        $response->assertSee('rel="noopener noreferrer"', false);
    }

    public function test_lightbox_title_falls_back_to_article_title(): void
    {
        $author = $this->author();
        $article = $this->publishedArticle($author, ['title' => 'Titolo usato nel dialog']);

        $this->get(route('articolo', $article->slug))
            ->assertOk()
            ->assertSee('class="media-viewer__title">Titolo usato nel dialog<', false);
    }

    public function test_existing_article_without_any_cover_metadata_still_renders_correctly(): void
    {
        $author = $this->author();
        $article = $this->publishedArticle($author);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertSee($article->title, false);
        $response->assertSee('data-media-viewer-target=', false);
    }

    public function test_article_without_cover_image_falls_back_to_placeholder_and_still_renders_the_viewer(): void
    {
        $author = $this->author();
        $article = $this->publishedArticle($author, ['cover_image' => null]);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertSee('data-media-viewer-target=', false);
    }
}
