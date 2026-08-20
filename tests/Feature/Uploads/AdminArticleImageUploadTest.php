<?php

namespace Tests\Feature\Uploads;

use App\Models\Article;
use App\Models\Media;
use App\Models\User;
use App\Services\ArticleLinkSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use RuntimeException;
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
        // La copertina viene convertita automaticamente in WebP per i nuovi
        // upload (FASE 5): il nome file originale resta 'cover.jpg' (solo
        // un'etichetta), ma il disk_name/mime_type reali sono WebP.
        $this->assertSame('image/webp', $media->mime_type);
        $this->assertStringEndsWith('.webp', $article->cover_image);
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

    public function test_auto_webp_on_upload_can_be_disabled_via_config(): void
    {
        config(['media.auto_webp_on_upload' => false]);

        $editor = $this->editor();
        $cover = UploadedFile::fake()->image('cover.jpg', 800, 600);

        $this->actingAs($editor)->post(route('admin.articles.store'), $this->articlePayload([
            'cover_image_upload' => $cover,
        ]));

        $article = Article::where('title', 'Articolo con copertina')->firstOrFail();
        $media = Media::where('disk_name', $article->cover_image)->firstOrFail();

        $this->assertStringEndsWith('.jpg', $article->cover_image);
        $this->assertSame('image/jpeg', $media->mime_type);
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

    // Codex (PR #165, round 18): applyBusinessRules() carica e registra la nuova
    // copertina PRIMA che la transazione DB::transaction() (attorno a
    // $article->update()+markAccepted()) inizi — se markAccepted() fallisce, la
    // transazione annulla solo l'update() dell'Article, non quei side effect già
    // scritti su filesystem/Media. Il file e la riga Media devono essere ripuliti.
    public function test_update_cleans_up_the_new_cover_if_the_transaction_rolls_back(): void
    {
        $editor = $this->editor();

        $article = Article::create([
            'user_id' => $editor->id,
            'title' => 'Titolo originale',
            'slug' => 'titolo-originale-'.uniqid(),
            'excerpt' => 'Sommario di prova',
            'body' => 'Corpo articolo di prova.',
            'category' => 'energia',
            'status' => 'draft',
        ]);

        $this->mock(ArticleLinkSuggestionService::class, function ($mock) {
            $mock->shouldReceive('markAccepted')->andThrow(new RuntimeException('guasto simulato'));
        });

        $newCover = UploadedFile::fake()->image('nuova.jpg', 800, 600);

        try {
            $this->actingAs($editor)->put(route('admin.articles.update', $article), $this->articlePayload([
                'title' => 'Titolo originale',
                'cover_image_upload' => $newCover,
            ]));
        } catch (RuntimeException $exception) {
            $this->assertSame('guasto simulato', $exception->getMessage());
        }

        // Nessun riferimento persistito alla nuova copertina (rollback).
        $this->assertNull($article->fresh()->cover_image);

        // Ma senza la pulizia, il file caricato e la sua riga Media
        // sopravvivrebbero comunque, orfani, alla transazione annullata.
        $this->assertSame(0, Media::where('filename', 'nuova.jpg')->count());

        // Directory isolata per questo test (UsesIsolatedPublicPath): nessun
        // file deve restare, orfano, dopo la pulizia.
        $this->assertSame([], glob(public_path('assets/img/*.webp')) ?: []);
    }

    // Codex (PR #165, round 19): il ritiro sul rollback (round 18) deve limitarsi a un
    // disk_name DAVVERO caricato in QUESTA richiesta — se l'admin invia invece un
    // cover_image già esistente (es. un asset scelto dalla libreria media, non ancora
    // riferito da alcun articolo, senza allegare cover_image_upload), quell'asset non ha
    // alcuna relazione con questo fallimento e non deve mai essere ritirato.
    public function test_update_never_retires_an_unrelated_existing_cover_image_on_rollback(): void
    {
        $editor = $this->editor();

        $article = Article::create([
            'user_id' => $editor->id,
            'title' => 'Titolo originale',
            'slug' => 'titolo-originale-'.uniqid(),
            'excerpt' => 'Sommario di prova',
            'body' => 'Corpo articolo di prova.',
            'category' => 'energia',
            'status' => 'draft',
        ]);

        // Un asset già presente in libreria media, non riferito da alcun
        // articolo — esattamente lo scenario del finding.
        $libraryDiskName = 'libreria-esistente.webp';
        file_put_contents(public_path('assets/img/'.$libraryDiskName), 'contenuto finto');
        $libraryMedia = Media::create([
            'user_id' => $editor->id,
            'filename' => 'libreria-esistente.webp',
            'disk_name' => $libraryDiskName,
            'mime_type' => 'image/webp',
            'size' => 100,
        ]);

        $this->mock(ArticleLinkSuggestionService::class, function ($mock) {
            $mock->shouldReceive('markAccepted')->andThrow(new RuntimeException('guasto simulato'));
        });

        try {
            $this->actingAs($editor)->put(route('admin.articles.update', $article), $this->articlePayload([
                'title' => 'Titolo originale',
                'cover_image' => $libraryDiskName,
            ]));
        } catch (RuntimeException $exception) {
            $this->assertSame('guasto simulato', $exception->getMessage());
        }

        $this->assertNotNull(Media::find($libraryMedia->id));
        $this->assertFileExists(public_path('assets/img/'.$libraryDiskName));
    }

    // S9 — applyBusinessRules() carica e registra la nuova copertina PRIMA
    // che Article::create() venga chiamato in store(): a differenza di
    // update() (Codex, PR #165, round 18), store() non aveva ALCUNA
    // protezione equivalente — un fallimento di Article::create() (DB
    // irraggiungibile, vincolo violato, ecc.) dopo che il file e la riga
    // Media erano gia' stati creati e pubblicati lasciava un file live,
    // pubblicamente raggiungibile, mai riferito da alcun articolo.
    public function test_store_cleans_up_the_new_cover_if_article_creation_fails(): void
    {
        $editor = $this->editor();

        Article::creating(function () {
            throw new RuntimeException('guasto simulato');
        });

        $newCover = UploadedFile::fake()->image('nuova-store.jpg', 800, 600);

        try {
            $this->actingAs($editor)->post(route('admin.articles.store'), $this->articlePayload([
                'title' => 'Articolo mai creato',
                'cover_image_upload' => $newCover,
            ]));
        } catch (RuntimeException $exception) {
            $this->assertSame('guasto simulato', $exception->getMessage());
        }

        $this->assertSame(0, Article::where('title', 'Articolo mai creato')->count());

        // Senza la pulizia, il file caricato e la sua riga Media
        // sopravvivrebbero orfani, live e pubblicamente raggiungibili,
        // senza che alcun articolo li referenzi mai.
        $this->assertSame(0, Media::where('filename', 'nuova-store.jpg')->count());
        $this->assertSame([], glob(public_path('assets/img/*.webp')) ?: []);
    }
}
