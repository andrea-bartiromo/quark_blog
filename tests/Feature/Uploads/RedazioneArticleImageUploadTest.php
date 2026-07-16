<?php

namespace Tests\Feature\Uploads;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

class RedazioneArticleImageUploadTest extends TestCase
{
    use RefreshDatabase;
    use UsesIsolatedPublicPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedPublicPath();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        $this->tearDownIsolatedPublicPath();
        parent::tearDown();
    }

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function articlePayload(array $overrides = []): array
    {
        return array_merge([
            'title'    => 'Articolo redazione',
            'excerpt'  => 'Sommario di prova',
            'body'     => 'Corpo articolo di prova.',
            'category' => 'energia',
        ], $overrides);
    }

    public function test_an_authorized_author_can_create_an_article_with_a_cover(): void
    {
        $author = $this->author();
        $cover = UploadedFile::fake()->image('cover.jpg', 800, 600);

        $response = $this->actingAs($author)->post(route('redazione.articles.store'), $this->articlePayload([
            'cover_image_upload' => $cover,
        ]));

        $response->assertRedirect(route('redazione.articles'));
        $response->assertSessionHas('success');

        $article = Article::where('title', 'Articolo redazione')->firstOrFail();

        $this->assertNotNull($article->cover_image);
        $this->assertFileExists(public_path('assets/img/' . $article->cover_image));
        $this->assertSame('review', $article->status);
    }

    public function test_the_cover_is_not_resized_by_the_redazione_flow(): void
    {
        // A differenza dei flussi Admin, Redazione\ArticleController non
        // chiama mai resizeAndCompress(): le dimensioni originali devono
        // essere preservate anche oltre i preset usati altrove (1600/1200px).
        $author = $this->author();
        $cover = UploadedFile::fake()->image('grande.jpg', 2400, 1200);

        $this->actingAs($author)->post(route('redazione.articles.store'), $this->articlePayload([
            'cover_image_upload' => $cover,
        ]));

        $article = Article::where('title', 'Articolo redazione')->firstOrFail();
        [$w, $h] = getimagesize(public_path('assets/img/' . $article->cover_image));

        $this->assertSame(2400, $w);
        $this->assertSame(1200, $h);
    }

    public function test_cover_upload_is_optional(): void
    {
        $author = $this->author();

        $response = $this->actingAs($author)->post(route('redazione.articles.store'), $this->articlePayload());

        $response->assertRedirect(route('redazione.articles'));

        $article = Article::where('title', 'Articolo redazione')->firstOrFail();
        $this->assertNull($article->cover_image);
    }

    public function test_validation_rejects_an_unsupported_image_format(): void
    {
        $author = $this->author();
        $bmp = UploadedFile::fake()->create('cover.bmp', 100, 'image/bmp');

        $response = $this->actingAs($author)->post(route('redazione.articles.store'), $this->articlePayload([
            'cover_image_upload' => $bmp,
        ]));

        $response->assertSessionHasErrors('cover_image_upload');
        $this->assertDatabaseMissing('articles', ['title' => 'Articolo redazione']);
    }

    public function test_the_author_of_the_article_can_update_it_with_a_new_cover(): void
    {
        $author = $this->author();
        $original = UploadedFile::fake()->image('originale.jpg', 800, 600);

        $this->actingAs($author)->post(route('redazione.articles.store'), $this->articlePayload([
            'cover_image_upload' => $original,
        ]));
        $article = Article::where('title', 'Articolo redazione')->firstOrFail();
        $oldCover = $article->cover_image;

        $newCover = UploadedFile::fake()->image('nuova.jpg', 800, 600);
        $response = $this->actingAs($author)->put(route('redazione.articles.update', $article), $this->articlePayload([
            'cover_image_upload' => $newCover,
        ]));

        $response->assertRedirect(route('redazione.articles'));

        $article->refresh();
        $this->assertNotSame($oldCover, $article->cover_image);
        $this->assertFileExists(public_path('assets/img/' . $article->cover_image));
    }

    public function test_updating_without_a_new_cover_keeps_the_existing_one(): void
    {
        $author = $this->author();
        $original = UploadedFile::fake()->image('originale.jpg', 800, 600);

        $this->actingAs($author)->post(route('redazione.articles.store'), $this->articlePayload([
            'cover_image_upload' => $original,
        ]));
        $article = Article::where('title', 'Articolo redazione')->firstOrFail();
        $oldCover = $article->cover_image;

        // Il form redazione reale precompila il campo testuale "cover_image"
        // con il nome file attuale: replichiamo lo stesso comportamento
        // osservato per il flusso Admin, senza allegare un nuovo file.
        $response = $this->actingAs($author)->put(route('redazione.articles.update', $article), $this->articlePayload([
            'cover_image' => $oldCover,
        ]));

        $response->assertRedirect(route('redazione.articles'));

        $article->refresh();
        $this->assertSame($oldCover, $article->cover_image);
    }

    public function test_the_article_stays_in_review_status_after_creation_and_update(): void
    {
        $author = $this->author();

        $this->actingAs($author)->post(route('redazione.articles.store'), $this->articlePayload());
        $article = Article::where('title', 'Articolo redazione')->firstOrFail();
        $this->assertSame('review', $article->status);

        $this->actingAs($author)->put(route('redazione.articles.update', $article), $this->articlePayload([
            'cover_image' => $article->cover_image,
        ]));

        $article->refresh();
        $this->assertSame('review', $article->status);
    }

    public function test_a_user_without_editorial_access_cannot_reach_the_redazione_upload_flow(): void
    {
        $reader = User::factory()->create(['role' => 'user']);
        $cover = UploadedFile::fake()->image('cover.jpg', 800, 600);

        $response = $this->actingAs($reader)->post(route('redazione.articles.store'), $this->articlePayload([
            'cover_image_upload' => $cover,
        ]));

        $response->assertForbidden();
        $this->assertDatabaseMissing('articles', ['title' => 'Articolo redazione']);
    }

    public function test_a_different_author_cannot_update_someone_elses_article(): void
    {
        $author = $this->author();
        $this->actingAs($author)->post(route('redazione.articles.store'), $this->articlePayload());
        $article = Article::where('title', 'Articolo redazione')->firstOrFail();

        $otherAuthor = $this->author();
        $newCover = UploadedFile::fake()->image('nuova.jpg', 800, 600);

        $response = $this->actingAs($otherAuthor)->put(route('redazione.articles.update', $article), $this->articlePayload([
            'cover_image_upload' => $newCover,
        ]));

        $response->assertForbidden();
    }

    public function test_creating_an_article_does_not_fail_even_though_the_editor_notification_is_attempted(): void
    {
        // notifyEditor() usa Mail::send([], [], closure) "raw" (non un
        // Mailable): Mail::fake() intercetta comunque l'invio reale (nessuna
        // chiamata esterna durante i test) ma, non trattandosi di un
        // Mailable, non viene registrato da assertSent(). Qui verifichiamo
        // il comportamento osservabile e reale: la creazione dell'articolo
        // ha successo indipendentemente dal tentativo di notifica, grazie al
        // try/catch silenzioso già presente nel controller.
        $editor = User::factory()->create(['role' => 'editor']);
        $author = $this->author();

        $response = $this->actingAs($author)->post(route('redazione.articles.store'), $this->articlePayload());

        $response->assertRedirect(route('redazione.articles'));
        $this->assertDatabaseHas('articles', ['title' => 'Articolo redazione']);
    }
}
