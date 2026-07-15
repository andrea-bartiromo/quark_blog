<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleCoverMetadataTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function publishedArticle(User $author, array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id'      => $author->id,
            'title'        => 'Articolo di prova',
            'slug'         => 'articolo-di-prova-' . uniqid(),
            'excerpt'      => 'Sommario di prova',
            'body'         => 'Corpo articolo di prova.',
            'category'     => 'intelligenza-artificiale',
            'cover_image'  => 'copertina.jpg',
            'status'       => 'published',
            'published_at' => now(),
        ], $overrides));
    }

    public function test_admin_can_create_article_with_full_cover_metadata(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->post(route('admin.articles.store'), [
            'title'            => 'Nuovo articolo con metadati',
            'excerpt'          => 'Sommario breve',
            'body'             => 'Corpo articolo di prova.',
            'category'         => 'intelligenza-artificiale',
            'status'           => 'draft',
            'cover_alt'        => 'Testo alternativo',
            'cover_caption'    => 'Una didascalia',
            'cover_credit'     => 'Foto di Mario Rossi',
            'cover_source'     => 'NASA',
            'cover_source_url' => 'https://nasa.gov',
            'cover_license'    => 'CC BY 4.0',
        ]);

        $response->assertRedirect(route('admin.articles'));

        $article = Article::where('title', 'Nuovo articolo con metadati')->firstOrFail();
        $this->assertSame('Testo alternativo', $article->cover_alt);
        $this->assertSame('Una didascalia', $article->cover_caption);
        $this->assertSame('Foto di Mario Rossi', $article->cover_credit);
        $this->assertSame('NASA', $article->cover_source);
        $this->assertSame('https://nasa.gov', $article->cover_source_url);
        $this->assertSame('CC BY 4.0', $article->cover_license);
    }

    public function test_admin_can_update_cover_metadata(): void
    {
        $editor = $this->editor();
        $article = $this->publishedArticle($editor);

        $response = $this->actingAs($editor)->put(route('admin.articles.update', $article), [
            'title'        => $article->title,
            'body'         => $article->body,
            'category'     => $article->category,
            'status'       => $article->status,
            'cover_alt'    => 'Alt aggiornato',
            'cover_credit' => 'Credito aggiornato',
        ]);

        $response->assertRedirect(route('admin.articles'));

        $article->refresh();
        $this->assertSame('Alt aggiornato', $article->cover_alt);
        $this->assertSame('Credito aggiornato', $article->cover_credit);
    }

    public function test_cover_source_url_must_be_a_valid_url(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->post(route('admin.articles.store'), [
            'title'            => 'Articolo con URL non valido',
            'body'             => 'Corpo articolo.',
            'category'         => 'energia',
            'status'           => 'draft',
            'cover_source_url' => 'non-una-url',
        ]);

        $response->assertSessionHasErrors('cover_source_url');
        $this->assertDatabaseMissing('articles', ['title' => 'Articolo con URL non valido']);
    }

    public function test_cover_metadata_fields_are_nullable(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->post(route('admin.articles.store'), [
            'title'    => 'Articolo senza metadati copertina',
            'body'     => 'Corpo articolo.',
            'category' => 'energia',
            'status'   => 'draft',
        ]);

        $response->assertSessionDoesntHaveErrors();

        $article = Article::where('title', 'Articolo senza metadati copertina')->firstOrFail();
        $this->assertNull($article->cover_alt);
        $this->assertNull($article->cover_caption);
        $this->assertNull($article->cover_credit);
        $this->assertNull($article->cover_source);
        $this->assertNull($article->cover_source_url);
        $this->assertNull($article->cover_license);
    }

    public function test_existing_article_without_metadata_stays_backward_compatible(): void
    {
        $author = $this->author();
        $article = $this->publishedArticle($author);

        $this->assertNull($article->cover_alt);

        $response = $this->get(route('articolo', $article->slug));
        $response->assertOk();
        $response->assertSee($article->title, false);
    }

    public function test_public_view_renders_custom_alt_text(): void
    {
        $author = $this->author();
        $article = $this->publishedArticle($author, [
            'cover_alt' => 'Un microscopio elettronico in laboratorio',
        ]);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertSee('alt="Un microscopio elettronico in laboratorio"', false);
    }

    public function test_public_view_falls_back_to_title_when_cover_alt_is_empty(): void
    {
        $author = $this->author();
        $article = $this->publishedArticle($author, [
            'title'     => 'Titolo usato come fallback alt',
            'cover_alt' => null,
        ]);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertSee('alt="Titolo usato come fallback alt"', false);
    }

    public function test_public_view_shows_caption_and_credit_only_when_present(): void
    {
        $author = $this->author();

        $withoutMeta = $this->publishedArticle($author, ['title' => 'Senza metadati copertina']);
        $response = $this->get(route('articolo', $withoutMeta->slug));
        $response->assertOk();
        $response->assertDontSee('<figcaption>', false);

        $withMeta = $this->publishedArticle($author, [
            'title'            => 'Con metadati copertina',
            'cover_caption'    => 'Vista al microscopio di una cellula',
            'cover_credit'     => 'Foto di Jane Doe',
            'cover_source'     => 'Wikimedia Commons',
            'cover_source_url' => 'https://commons.wikimedia.org/example',
            'cover_license'    => 'CC BY-SA 4.0',
        ]);
        $response = $this->get(route('articolo', $withMeta->slug));
        $response->assertOk();
        $response->assertSee('Vista al microscopio di una cellula', false);
        $response->assertSee('Foto di Jane Doe', false);
        $response->assertSee('Wikimedia Commons', false);
        $response->assertSee('CC BY-SA 4.0', false);
        $response->assertSee('rel="noopener noreferrer"', false);
    }
}
