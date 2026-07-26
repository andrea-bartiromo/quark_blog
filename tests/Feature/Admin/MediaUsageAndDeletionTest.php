<?php

namespace Tests\Feature\Admin;

use App\Models\Ad;
use App\Models\Article;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\User;
use App\Services\MediaMoveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

class MediaUsageAndDeletionTest extends TestCase
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

    private function mediaWithFile(User $owner, string $diskName, string $content = 'fake-bytes'): Media
    {
        $path = public_path('assets/img/'.$diskName);
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, $content);

        return Media::create([
            'user_id' => $owner->id,
            'filename' => basename($diskName),
            'disk_name' => $diskName,
            'mime_type' => 'image/jpeg',
            'size' => strlen($content),
        ]);
    }

    private function articleWithCover(string $diskName, array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'body' => 'Testo generico.',
            'category' => 'scienza',
            'cover_image' => $diskName,
            'status' => 'published',
            'read_minutes' => 2,
            'verification_status' => 'unverified',
        ], $overrides));
    }

    // 1. Badge "Non utilizzata"
    public function test_unused_media_shows_the_unused_badge(): void
    {
        $editor = $this->editor();
        $this->mediaWithFile($editor, 'inutilizzata.jpg');

        $this->actingAs($editor)->get(route('admin.media'))
            ->assertOk()
            ->assertSee('Non utilizzata')
            ->assertSee('badge--usage-none', false);
    }

    // Badge "Usata in 1 contenuto" / "Usata in N contenuti"
    public function test_used_media_shows_the_correct_usage_count_badge(): void
    {
        $editor = $this->editor();
        $this->mediaWithFile($editor, 'una-copertina.jpg');
        $this->articleWithCover('una-copertina.jpg');

        $this->actingAs($editor)->get(route('admin.media'))
            ->assertOk()
            ->assertSee('Usata in 1 contenuto')
            ->assertSee('badge--usage-used', false);
    }

    public function test_media_used_in_multiple_contents_shows_the_plural_badge(): void
    {
        $editor = $this->editor();
        $this->mediaWithFile($editor, 'doppio-uso.jpg');
        $this->articleWithCover('doppio-uso.jpg', ['title' => 'Primo articolo']);
        Ad::create([
            'name' => 'Banner doppio uso',
            'position' => 'sidebar',
            'type' => 'banner',
            'active' => true,
            'priority' => 1,
            'banner_image' => 'doppio-uso.jpg',
        ]);

        $this->actingAs($editor)->get(route('admin.media'))
            ->assertOk()
            ->assertSee('Usata in 2 contenuti');
    }

    // 6. Dettaglio degli utilizzi
    public function test_usage_detail_panel_lists_each_usage_with_title_and_edit_link(): void
    {
        $editor = $this->editor();
        $this->mediaWithFile($editor, 'con-dettaglio.jpg');
        $article = $this->articleWithCover('con-dettaglio.jpg', ['title' => 'Articolo con dettaglio utilizzo']);

        $response = $this->actingAs($editor)->get(route('admin.media'));

        $response->assertOk();
        $response->assertSee('Mostra utilizzi');
        $response->assertSee('Articolo con dettaglio utilizzo');
        $response->assertSee('Copertina');
        $response->assertSee(route('admin.articles.edit', $article), false);
    }

    public function test_usage_detail_panel_shows_no_usage_message_for_unused_media(): void
    {
        $editor = $this->editor();
        $this->mediaWithFile($editor, 'senza-dettaglio.jpg');

        $this->actingAs($editor)->get(route('admin.media'))
            ->assertOk()
            ->assertSee('Nessun utilizzo rilevato.');
    }

    // 7. Eliminazione consentita per media inutilizzato
    public function test_unused_media_can_be_deleted(): void
    {
        $editor = $this->editor();
        $media = $this->mediaWithFile($editor, 'da-eliminare.jpg');
        $path = public_path('assets/img/da-eliminare.jpg');
        $this->assertFileExists($path);

        $response = $this->actingAs($editor)->delete(route('admin.media.destroy', $media));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        $this->assertFileDoesNotExist($path);
    }

    // 8. Eliminazione bloccata per media utilizzato
    public function test_used_media_cannot_be_deleted(): void
    {
        $editor = $this->editor();
        $media = $this->mediaWithFile($editor, 'protetta-da-uso.jpg');
        $this->articleWithCover('protetta-da-uso.jpg');

        $response = $this->actingAs($editor)->delete(route('admin.media.destroy', $media));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Questa immagine è utilizzata in 1 contenuto e non può essere eliminata finché è ancora collegata.');
        $this->assertDatabaseHas('media', ['id' => $media->id]);
        $this->assertFileExists(public_path('assets/img/protetta-da-uso.jpg'));
    }

    public function test_used_media_blocked_message_uses_plural_when_multiple_usages(): void
    {
        $editor = $this->editor();
        $media = $this->mediaWithFile($editor, 'multi-blocco.jpg');
        $this->articleWithCover('multi-blocco.jpg');
        Ad::create([
            'name' => 'Banner multi blocco',
            'position' => 'sidebar',
            'type' => 'banner',
            'active' => true,
            'priority' => 1,
            'banner_image' => 'multi-blocco.jpg',
        ]);

        $response = $this->actingAs($editor)->delete(route('admin.media.destroy', $media));

        $response->assertSessionHas('error', 'Questa immagine è utilizzata in 2 contenuti e non può essere eliminata finché è ancora collegata.');
    }

    // 9. Eliminazione ancora bloccata per media protetto (anche se inutilizzato)
    public function test_protected_media_cannot_be_deleted_even_when_unused(): void
    {
        $editor = $this->editor();
        $media = $this->mediaWithFile($editor, 'placeholder-1.svg');

        $response = $this->actingAs($editor)->delete(route('admin.media.destroy', $media));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('protetto', session('error'));
        $this->assertDatabaseHas('media', ['id' => $media->id]);
        $this->assertFileExists(public_path('assets/img/placeholder-1.svg'));
    }

    public function test_protected_media_shows_protected_badge_and_disabled_delete(): void
    {
        $editor = $this->editor();
        $this->mediaWithFile($editor, 'hero-placeholder.svg');

        $response = $this->actingAs($editor)->get(route('admin.media'));

        $response->assertOk();
        $response->assertSee('Protetta');
        $response->assertSee('disabled', false);
    }

    // 10. Nessuna regressione per ricerca, filtri, ordinamento e paginazione
    public function test_search_filters_sort_and_pagination_still_work_alongside_usage_badges(): void
    {
        $editor = $this->editor();
        $this->mediaWithFile($editor, 'ricerca-speciale.jpg');
        $this->mediaWithFile($editor, 'altro-file.jpg');
        $this->articleWithCover('ricerca-speciale.jpg');

        $response = $this->actingAs($editor)->get(route('admin.media', ['q' => 'ricerca-speciale']));

        $response->assertOk();
        $response->assertSee('ricerca-speciale.jpg');
        $response->assertDontSee('altro-file.jpg');
        $response->assertSee('Usata in 1 contenuto');
    }

    public function test_pagination_still_works_with_many_media_items(): void
    {
        $editor = $this->editor();
        foreach (range(1, 30) as $i) {
            $this->mediaWithFile($editor, "pagina-{$i}.jpg");
        }

        $response = $this->actingAs($editor)->get(route('admin.media'));

        $response->assertOk();
        $response->assertSee('Non utilizzata');
        $response->assertViewHas('files', function ($files) {
            return $files->hasPages();
        });
    }

    // 11. Spostamento di un media utilizzato senza rottura dei riferimenti
    public function test_moving_a_used_media_keeps_usage_detection_correct_after_the_move(): void
    {
        $editor = $this->editor();
        $media = $this->mediaWithFile($editor, 'da-spostare.jpg');
        $article = $this->articleWithCover('da-spostare.jpg');
        $folder = MediaFolder::create(['name' => 'Archivio', 'slug' => 'archivio', 'path' => 'archivio']);
        @mkdir(public_path('assets/img/archivio'), 0775, true);

        app(MediaMoveService::class)->move($media->id, $folder->id, $editor->id);

        $media->refresh();
        $article->refresh();

        $this->assertSame('archivio/da-spostare.jpg', $media->disk_name);
        $this->assertSame('archivio/da-spostare.jpg', $article->cover_image);

        $response = $this->actingAs($editor)->get(route('admin.media', ['folder' => $folder->id]));
        $response->assertOk();
        $response->assertSee('Usata in 1 contenuto');

        // Ancora bloccata dall'uso dopo lo spostamento: nessuna finestra in
        // cui il riferimento aggiornato smette di essere rilevato.
        $deleteResponse = $this->actingAs($editor)->delete(route('admin.media.destroy', $media));
        $deleteResponse->assertSessionHas('error');
        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }

    // 12. Autorizzazioni e accesso amministrativo
    public function test_author_cannot_delete_media(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $media = $this->mediaWithFile($author, 'di-un-autore.jpg');

        $response = $this->actingAs($author)->delete(route('admin.media.destroy', $media));

        $response->assertRedirect(route('redazione.dashboard'));
        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }

    public function test_guest_cannot_access_the_media_library(): void
    {
        $this->get(route('admin.media'))->assertRedirect(route('login'));
    }
}
