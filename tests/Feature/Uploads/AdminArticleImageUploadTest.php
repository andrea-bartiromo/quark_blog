<?php

namespace Tests\Feature\Uploads;

use App\Models\Article;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

class AdminArticleImageUploadTest extends TestCase
{
    use RefreshDatabase;
    use UsesIsolatedPublicPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedPublicPath();
    }

    protected function tearDown(): void
    {
        $this->tearDownIsolatedPublicPath();
        parent::tearDown();
    }

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function articlePayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Articolo con copertina',
            'excerpt' => 'Sommario di prova',
            'body' => 'Corpo articolo di prova.',
            'category' => 'energia',
            'status' => 'draft',
        ], $overrides);
    }

    public function test_authorized_editor_can_create_an_article_with_a_cover(): void
    {
        $editor = $this->editor();
        $cover = UploadedFile::fake()->image('cover.jpg', 2000, 1000);

        $response = $this->actingAs($editor)->post(route('admin.articles.store'), $this->articlePayload([
            'cover_image_upload' => $cover,
        ]));

        $response->assertRedirect(route('admin.articles'));
        $response->assertSessionHas('success', 'Articolo creato.');

        $article = Article::where('title', 'Articolo con copertina')->firstOrFail();
        $fullPath = public_path('assets/img/'.$article->cover_image);

        $this->assertNotNull($article->cover_image);
        $this->assertFileExists($fullPath);

        $media = Media::where('disk_name', $article->cover_image)->firstOrFail();

        $this->assertSame($editor->id, $media->user_id);
        $this->assertSame('cover.jpg', $media->filename);
        $this->assertSame($article->cover_image, $media->disk_name);
        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertSame(filesize($fullPath), $media->size);
    }

    public function test_cover_is_resized_to_the_1600px_limit(): void
    {
        $editor = $this->editor();
        $cover = UploadedFile::fake()->image('big.jpg', 2400, 1200);

        $this->actingAs($editor)->post(route('admin.articles.store'), $this->articlePayload([
            'cover_image_upload' => $cover,
        ]));

        $article = Article::where('title', 'Articolo con copertina')->firstOrFail();
        [$w, $h] = getimagesize(public_path('assets/img/'.$article->cover_image));

        $this->assertSame(1600, $w);
        $this->assertSame(800, $h);
    }

    public function test_cover_upload_is_optional(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->post(route('admin.articles.store'), $this->articlePayload());

        $response->assertRedirect(route('admin.articles'));

        $article = Article::where('title', 'Articolo con copertina')->firstOrFail();
        $this->assertNull($article->cover_image);
    }

    public function test_validation_rejects_an_unsupported_image_format(): void
    {
        $editor = $this->editor();
        $cover = UploadedFile::fake()->create('cover.bmp', 100, 'image/bmp');

        $response = $this->actingAs($editor)->post(route('admin.articles.store'), $this->articlePayload([
            'cover_image_upload' => $cover,
        ]));

        $response->assertSessionHasErrors('cover_image_upload');
        $this->assertDatabaseMissing('articles', ['title' => 'Articolo con copertina']);
    }

    public function test_validation_rejects_a_cover_over_the_size_limit(): void
    {
        $editor = $this->editor();
        $cover = UploadedFile::fake()->image('too-big.jpg')->size(16385);

        $response = $this->actingAs($editor)->post(route('admin.articles.store'), $this->articlePayload([
            'cover_image_upload' => $cover,
        ]));

        $response->assertSessionHasErrors('cover_image_upload');
        $this->assertDatabaseMissing('articles', ['title' => 'Articolo con copertina']);
    }

    public function test_update_with_a_new_cover_replaces_the_reference(): void
    {
        $editor = $this->editor();
        $original = UploadedFile::fake()->image('original.jpg', 800, 600);

        $this->actingAs($editor)->post(route('admin.articles.store'), $this->articlePayload([
            'cover_image_upload' => $original,
        ]));
        $article = Article::where('title', 'Articolo con copertina')->firstOrFail();
        $oldCover = $article->cover_image;

        $newCover = UploadedFile::fake()->image('new.jpg', 800, 600);
        $response = $this->actingAs($editor)->put(route('admin.articles.update', $article), $this->articlePayload([
            'title' => 'Articolo con copertina',
            'cover_image_upload' => $newCover,
        ]));

        $response->assertRedirect(route('admin.articles'));

        $article->refresh();
        $this->assertNotSame($oldCover, $article->cover_image);
        $this->assertFileExists(public_path('assets/img/'.$article->cover_image));
    }

    public function test_update_without_a_new_cover_keeps_the_existing_one(): void
    {
        $editor = $this->editor();
        $original = UploadedFile::fake()->image('original.jpg', 800, 600);

        $this->actingAs($editor)->post(route('admin.articles.store'), $this->articlePayload([
            'cover_image_upload' => $original,
        ]));
        $article = Article::where('title', 'Articolo con copertina')->firstOrFail();
        $oldCover = $article->cover_image;

        // Il form admin reale precompila il campo testuale "cover_image"
        // con il nome file attuale: replichiamo esattamente questo, senza
        // allegare un nuovo cover_image_upload.
        $response = $this->actingAs($editor)->put(route('admin.articles.update', $article), $this->articlePayload([
            'title' => 'Articolo con copertina',
            'cover_image' => $oldCover,
        ]));

        $response->assertRedirect(route('admin.articles'));
        $response->assertSessionHas('success', 'Articolo aggiornato.');

        $article->refresh();
        $this->assertSame($oldCover, $article->cover_image);
    }
}
