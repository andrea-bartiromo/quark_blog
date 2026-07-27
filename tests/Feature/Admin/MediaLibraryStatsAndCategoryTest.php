<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\User;
use App\Services\MediaStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

class MediaLibraryStatsAndCategoryTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function media(User $user, string $diskName, array $overrides = []): Media
    {
        return Media::create(array_merge([
            'user_id' => $user->id,
            'filename' => basename($diskName),
            'disk_name' => $diskName,
            'mime_type' => 'image/jpeg',
            'size' => 1000,
        ], $overrides));
    }

    // 1. Statistiche: file totali, spazio, immagini, documenti
    public function test_stats_report_totals_across_the_library(): void
    {
        $editor = $this->editor();
        $this->media($editor, 'foto-uno.jpg', ['mime_type' => 'image/jpeg', 'size' => 1000]);
        $this->media($editor, 'foto-due.png', ['mime_type' => 'image/png', 'size' => 2000]);
        $this->media($editor, 'documento.pdf', ['mime_type' => 'application/pdf', 'size' => 3000]);
        $this->media($editor, 'archivio.zip', ['mime_type' => 'application/zip', 'size' => 4000]);

        $stats = app(MediaStatsService::class)->global();

        $this->assertSame(4, $stats['total_files']);
        $this->assertSame(10000, $stats['total_size']);
        $this->assertSame('9.8 KB', $stats['total_size_human']);
        $this->assertSame(2, $stats['image_count']);
        $this->assertSame(1, $stats['document_count']);
    }

    // 2. Le statistiche compaiono nella pagina e coprono l'intera libreria,
    // non solo la cartella o i filtri correnti.
    public function test_stats_are_visible_on_the_page_and_are_not_scoped_to_the_current_folder_or_filters(): void
    {
        $editor = $this->editor();
        $folder = MediaFolder::create(['name' => 'Archivio', 'slug' => 'archivio', 'path' => 'archivio']);
        $this->media($editor, 'archivio/dentro.jpg');
        $this->media($editor, 'fuori.jpg');

        $response = $this->actingAs($editor)->get(route('admin.media', ['folder' => $folder->id, 'q' => 'termine-inesistente']));

        $response->assertOk();

        $stats = $this->statsBlockHtml($response->getContent());
        // 2 file in libreria, anche se la cartella+ricerca corrente non ne mostra nessuno.
        $this->assertStringContainsString('<dd>2</dd>', $stats);
    }

    private function statsBlockHtml(string $fullHtml): string
    {
        $start = strpos($fullHtml, 'media-stats');
        $end = strpos($fullHtml, '</dl>', $start);

        $this->assertNotFalse($start, 'Blocco statistiche non trovato nella pagina.');

        return substr($fullHtml, $start, $end - $start);
    }

    // 3. Filtro per tipo: immagini
    public function test_category_filter_images(): void
    {
        $editor = $this->editor();
        $this->media($editor, 'foto.jpg', ['filename' => 'Foto.jpg', 'mime_type' => 'image/jpeg']);
        $this->media($editor, 'file.pdf', ['filename' => 'File.pdf', 'mime_type' => 'application/pdf']);

        $this->actingAs($editor)->get(route('admin.media', ['category' => 'images']))
            ->assertOk()
            ->assertSee('Foto.jpg')
            ->assertDontSee('File.pdf');
    }

    // 4. Filtro per tipo: documenti
    public function test_category_filter_documents(): void
    {
        $editor = $this->editor();
        $this->media($editor, 'file.pdf', ['filename' => 'File.pdf', 'mime_type' => 'application/pdf']);
        $this->media($editor, 'foto.jpg', ['filename' => 'Foto.jpg', 'mime_type' => 'image/jpeg']);

        $this->actingAs($editor)->get(route('admin.media', ['category' => 'documents']))
            ->assertOk()
            ->assertSee('File.pdf')
            ->assertDontSee('Foto.jpg');
    }

    // 5. Filtro per tipo: altro (ne' immagine ne' documento)
    public function test_category_filter_others(): void
    {
        $editor = $this->editor();
        $this->media($editor, 'archivio.zip', ['filename' => 'Archivio.zip', 'mime_type' => 'application/zip']);
        $this->media($editor, 'foto.jpg', ['filename' => 'Foto.jpg', 'mime_type' => 'image/jpeg']);
        $this->media($editor, 'file.pdf', ['filename' => 'File.pdf', 'mime_type' => 'application/pdf']);

        $this->actingAs($editor)->get(route('admin.media', ['category' => 'others']))
            ->assertOk()
            ->assertSee('Archivio.zip')
            ->assertDontSee('Foto.jpg')
            ->assertDontSee('File.pdf');
    }

    // 6. Rifiuto dei valori non validi di category
    public function test_invalid_category_value_is_rejected(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)
            ->from(route('admin.media'))
            ->get(route('admin.media', ['category' => 'video']))
            ->assertSessionHasErrors('category');
    }

    // 7. Ricerca case-insensitive
    public function test_search_is_case_insensitive(): void
    {
        $editor = $this->editor();
        $this->media($editor, 'foto-montagna.jpg', ['filename' => 'Foto Montagna.jpg']);
        $this->media($editor, 'altro.jpg', ['filename' => 'Altro.jpg']);

        $this->actingAs($editor)->get(route('admin.media', ['q' => 'MONTAGNA']))
            ->assertOk()
            ->assertSee('Foto Montagna.jpg')
            ->assertDontSee('Altro.jpg');

        $this->actingAs($editor)->get(route('admin.media', ['q' => 'montagna']))
            ->assertOk()
            ->assertSee('Foto Montagna.jpg');
    }

    // 8. Regressione: categoria, formato, cartella, ricerca e ordinamento
    // continuano a combinarsi correttamente tutti insieme.
    public function test_category_combines_correctly_with_existing_filters(): void
    {
        $editor = $this->editor();
        $folder = MediaFolder::create(['name' => 'Archivio', 'slug' => 'archivio', 'path' => 'archivio']);

        $this->media($editor, 'archivio/robot-immagine.png', [
            'filename' => 'Robot immagine.png', 'mime_type' => 'image/png',
        ]);
        $this->media($editor, 'archivio/robot-documento.pdf', [
            'filename' => 'Robot documento.pdf', 'mime_type' => 'application/pdf',
        ]);
        $this->media($editor, 'robot-fuori-cartella.png', [
            'filename' => 'Robot fuori cartella.png', 'mime_type' => 'image/png',
        ]);

        $this->actingAs($editor)
            ->get(route('admin.media', [
                'folder' => $folder->id, 'q' => 'robot', 'category' => 'images', 'type' => 'png', 'sort' => 'name_asc',
            ]))
            ->assertOk()
            ->assertSee('Robot immagine.png')
            ->assertDontSee('Robot documento.pdf')
            ->assertDontSee('Robot fuori cartella.png');
    }

    // 9. La query string conserva anche il nuovo filtro category
    public function test_pagination_preserves_the_category_filter(): void
    {
        $editor = $this->editor();

        foreach (range(1, 30) as $index) {
            $this->media($editor, "energia-{$index}.png", [
                'filename' => "Energia {$index}.png",
                'mime_type' => 'image/png',
            ]);
        }

        $response = $this->actingAs($editor)->get(route('admin.media', ['category' => 'images', 'sort' => 'name_asc']));

        $response->assertOk();
        $response->assertSee('category=images', false);
    }

    // 10. Il badge "Tipo" compare solo quando il filtro e' attivo
    public function test_category_filter_badge_only_appears_when_active(): void
    {
        $editor = $this->editor();
        $this->media($editor, 'foto.jpg', ['filename' => 'Foto.jpg']);

        $this->actingAs($editor)->get(route('admin.media'))
            ->assertOk()
            ->assertDontSee('Tipo: Immagini');

        $this->actingAs($editor)->get(route('admin.media', ['category' => 'images']))
            ->assertOk()
            ->assertSee('Tipo: Immagini');
    }
}
