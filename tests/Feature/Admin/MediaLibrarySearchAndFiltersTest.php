<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

class MediaLibrarySearchAndFiltersTest extends TestCase
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

    // 1. Ricerca per filename
    public function test_search_matches_filename(): void
    {
        $editor = $this->editor();
        $this->media($editor, 'panorama-montagna.jpg', ['filename' => 'Panorama di montagna.jpg']);
        $this->media($editor, 'altro.jpg', ['filename' => 'Altra immagine.jpg']);

        $this->actingAs($editor)->get(route('admin.media', ['q' => 'montagna']))
            ->assertOk()
            ->assertSee('Panorama di montagna.jpg')
            ->assertDontSee('Altra immagine.jpg');
    }

    // 2. Ricerca per disk_name
    public function test_search_matches_disk_name(): void
    {
        $editor = $this->editor();
        $this->media($editor, 'cover-scienza-2026.jpg', ['filename' => 'Copertina.jpg']);
        $this->media($editor, 'altro-file.jpg', ['filename' => 'Altro.jpg']);

        $this->actingAs($editor)->get(route('admin.media', ['q' => 'cover-scienza']))
            ->assertOk()
            ->assertSee('Copertina.jpg')
            ->assertDontSee('Altro.jpg');
    }

    // 3. Ricerca per alt_text
    public function test_search_matches_alt_text(): void
    {
        $editor = $this->editor();
        $this->media($editor, 'foto-uno.jpg', ['filename' => 'Foto Uno.jpg', 'alt_text' => 'Illustrazione sul cambiamento climatico']);
        $this->media($editor, 'foto-due.jpg', ['filename' => 'Foto Due.jpg', 'alt_text' => 'Robot chirurgico']);

        $this->actingAs($editor)->get(route('admin.media', ['q' => 'cambiamento climatico']))
            ->assertOk()
            ->assertSee('Foto Uno.jpg')
            ->assertDontSee('Foto Due.jpg');
    }

    // 4. Ricerca limitata alla cartella corrente
    public function test_search_is_limited_to_the_current_folder(): void
    {
        $editor = $this->editor();
        $folder = MediaFolder::create(['name' => 'Archivio', 'slug' => 'archivio', 'path' => 'archivio']);
        $this->media($editor, 'archivio/energia-solare.jpg', ['filename' => 'Energia solare (archivio).jpg']);
        $this->media($editor, 'energia-eolica.jpg', ['filename' => 'Energia eolica (radice).jpg']);

        $this->actingAs($editor)->get(route('admin.media', ['folder' => $folder->id, 'q' => 'energia']))
            ->assertOk()
            ->assertSee('Energia solare (archivio).jpg')
            ->assertDontSee('Energia eolica (radice).jpg');

        $this->actingAs($editor)->get(route('admin.media', ['q' => 'energia']))
            ->assertOk()
            ->assertSee('Energia eolica (radice).jpg')
            ->assertDontSee('Energia solare (archivio).jpg');
    }

    // 5-8. Filtro per formato
    public function test_type_filter_jpeg(): void
    {
        $editor = $this->editor();
        $this->media($editor, 'foto.jpg', ['filename' => 'FotoJpeg.jpg', 'mime_type' => 'image/jpeg']);
        $this->media($editor, 'foto.png', ['filename' => 'FotoPng.png', 'mime_type' => 'image/png']);

        $this->actingAs($editor)->get(route('admin.media', ['type' => 'jpeg']))
            ->assertOk()
            ->assertSee('FotoJpeg.jpg')
            ->assertDontSee('FotoPng.png');
    }

    public function test_type_filter_png(): void
    {
        $editor = $this->editor();
        $this->media($editor, 'foto.png', ['filename' => 'FotoPng.png', 'mime_type' => 'image/png']);
        $this->media($editor, 'foto.jpg', ['filename' => 'FotoJpeg.jpg', 'mime_type' => 'image/jpeg']);

        $this->actingAs($editor)->get(route('admin.media', ['type' => 'png']))
            ->assertOk()
            ->assertSee('FotoPng.png')
            ->assertDontSee('FotoJpeg.jpg');
    }

    public function test_type_filter_webp(): void
    {
        $editor = $this->editor();
        $this->media($editor, 'foto.webp', ['filename' => 'FotoWebp.webp', 'mime_type' => 'image/webp']);
        $this->media($editor, 'foto.gif', ['filename' => 'FotoGif.gif', 'mime_type' => 'image/gif']);

        $this->actingAs($editor)->get(route('admin.media', ['type' => 'webp']))
            ->assertOk()
            ->assertSee('FotoWebp.webp')
            ->assertDontSee('FotoGif.gif');
    }

    public function test_type_filter_gif(): void
    {
        $editor = $this->editor();
        $this->media($editor, 'foto.gif', ['filename' => 'FotoGif.gif', 'mime_type' => 'image/gif']);
        $this->media($editor, 'foto.webp', ['filename' => 'FotoWebp.webp', 'mime_type' => 'image/webp']);

        $this->actingAs($editor)->get(route('admin.media', ['type' => 'gif']))
            ->assertOk()
            ->assertSee('FotoGif.gif')
            ->assertDontSee('FotoWebp.webp');
    }

    // 9. Ordinamento per data crescente e decrescente
    public function test_sort_newest_and_oldest(): void
    {
        $editor = $this->editor();
        $old = $this->media($editor, 'vecchia.jpg', ['filename' => 'Vecchia.jpg']);
        $new = $this->media($editor, 'nuova.jpg', ['filename' => 'Nuova.jpg']);
        Media::whereKey($old->id)->update(['created_at' => now()->subDays(5)]);
        Media::whereKey($new->id)->update(['created_at' => now()]);

        $newest = $this->actingAs($editor)->get(route('admin.media', ['sort' => 'newest']));
        $newest->assertOk();
        $this->assertLessThan(
            strpos($newest->getContent(), 'Vecchia.jpg'),
            strpos($newest->getContent(), 'Nuova.jpg')
        );

        $oldest = $this->actingAs($editor)->get(route('admin.media', ['sort' => 'oldest']));
        $oldest->assertOk();
        $this->assertLessThan(
            strpos($oldest->getContent(), 'Nuova.jpg'),
            strpos($oldest->getContent(), 'Vecchia.jpg')
        );
    }

    // 10. Ordinamento alfabetico
    public function test_sort_alphabetically_ascending_and_descending(): void
    {
        $editor = $this->editor();
        $this->media($editor, 'banana.jpg', ['filename' => 'Banana.jpg']);
        $this->media($editor, 'ananas.jpg', ['filename' => 'Ananas.jpg']);

        $asc = $this->actingAs($editor)->get(route('admin.media', ['sort' => 'name_asc']));
        $asc->assertOk();
        $this->assertLessThan(
            strpos($asc->getContent(), 'Banana.jpg'),
            strpos($asc->getContent(), 'Ananas.jpg')
        );

        $desc = $this->actingAs($editor)->get(route('admin.media', ['sort' => 'name_desc']));
        $desc->assertOk();
        $this->assertLessThan(
            strpos($desc->getContent(), 'Ananas.jpg'),
            strpos($desc->getContent(), 'Banana.jpg')
        );
    }

    // 11. Ordinamento per dimensione
    public function test_sort_by_size(): void
    {
        $editor = $this->editor();
        $this->media($editor, 'piccola.jpg', ['filename' => 'Piccola.jpg', 'size' => 100]);
        $this->media($editor, 'grande.jpg', ['filename' => 'Grande.jpg', 'size' => 900000]);

        $desc = $this->actingAs($editor)->get(route('admin.media', ['sort' => 'size_desc']));
        $desc->assertOk();
        $this->assertLessThan(
            strpos($desc->getContent(), 'Piccola.jpg'),
            strpos($desc->getContent(), 'Grande.jpg')
        );

        $asc = $this->actingAs($editor)->get(route('admin.media', ['sort' => 'size_asc']));
        $asc->assertOk();
        $this->assertLessThan(
            strpos($asc->getContent(), 'Grande.jpg'),
            strpos($asc->getContent(), 'Piccola.jpg')
        );
    }

    // 12. Combinazione ricerca + formato + cartella
    public function test_search_type_and_folder_combine_correctly(): void
    {
        $editor = $this->editor();
        $folder = MediaFolder::create(['name' => 'Archivio', 'slug' => 'archivio', 'path' => 'archivio']);

        $this->media($editor, 'archivio/robot-chirurgico.png', ['filename' => 'Robot chirurgico.png', 'mime_type' => 'image/png']);
        $this->media($editor, 'archivio/robot-chirurgico.jpg', ['filename' => 'Robot chirurgico (jpg).jpg', 'mime_type' => 'image/jpeg']);
        $this->media($editor, 'robot-fuori-cartella.png', ['filename' => 'Robot fuori cartella.png', 'mime_type' => 'image/png']);

        $this->actingAs($editor)
            ->get(route('admin.media', ['folder' => $folder->id, 'q' => 'robot', 'type' => 'png']))
            ->assertOk()
            ->assertSee('Robot chirurgico.png')
            ->assertDontSee('Robot chirurgico (jpg).jpg')
            ->assertDontSee('Robot fuori cartella.png');
    }

    // 13. Conservazione dei parametri durante la paginazione
    public function test_pagination_preserves_search_type_sort_and_folder(): void
    {
        $editor = $this->editor();
        $folder = MediaFolder::create(['name' => 'Archivio', 'slug' => 'archivio', 'path' => 'archivio']);

        foreach (range(1, 30) as $index) {
            $this->media($editor, "archivio/energia-{$index}.png", [
                'filename' => "Energia {$index}.png",
                'mime_type' => 'image/png',
            ]);
        }

        $response = $this->actingAs($editor)->get(route('admin.media', [
            'folder' => $folder->id, 'q' => 'energia', 'type' => 'png', 'sort' => 'name_asc',
        ]));

        $response->assertOk();
        $response->assertSee('folder='.$folder->id, false);
        $response->assertSee('q=energia', false);
        $response->assertSee('type=png', false);
        $response->assertSee('sort=name_asc', false);
    }

    // 14. Rifiuto dei valori non validi di type
    public function test_invalid_type_value_is_rejected(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)
            ->from(route('admin.media'))
            ->get(route('admin.media', ['type' => 'bmp']))
            ->assertSessionHasErrors('type');
    }

    // 15. Rifiuto dei valori non validi di sort
    public function test_invalid_sort_value_is_rejected(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)
            ->from(route('admin.media'))
            ->get(route('admin.media', ['sort' => 'random']))
            ->assertSessionHasErrors('sort');
    }

    // 16. Presenza della toolbar
    public function test_toolbar_is_present(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->get(route('admin.media'))
            ->assertOk()
            ->assertSee('Ricerca e filtri nella libreria media', false)
            ->assertSee('Cerca')
            ->assertSee('Formato')
            ->assertSee('Ordina per')
            ->assertSee('Filtra');
    }

    // 17. Presenza del pulsante "Azzera filtri" solo quando necessario
    public function test_reset_filters_button_only_appears_when_a_filter_is_active(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->get(route('admin.media'))
            ->assertOk()
            ->assertDontSee('Azzera filtri');

        $this->actingAs($editor)->get(route('admin.media', ['q' => 'qualcosa']))
            ->assertOk()
            ->assertSee('Azzera filtri');

        $this->actingAs($editor)->get(route('admin.media', ['sort' => 'oldest']))
            ->assertOk()
            ->assertSee('Azzera filtri');
    }

    // 18. Assenza delle informazioni duplicate nella card
    public function test_card_does_not_repeat_the_same_name_when_filename_and_disk_name_match(): void
    {
        $editor = $this->editor();
        $this->media($editor, 'foto-unica.jpg', ['filename' => 'foto-unica.jpg']);

        $response = $this->actingAs($editor)->get(route('admin.media'));

        $response->assertOk();
        $response->assertDontSee('media-card__path', false);
    }

    public function test_card_shows_the_full_path_only_when_it_differs_from_the_filename(): void
    {
        $editor = $this->editor();
        $folder = MediaFolder::create(['name' => 'Archivio', 'slug' => 'archivio', 'path' => 'archivio']);
        $this->media($editor, 'archivio/foto-nidificata.jpg', ['filename' => 'foto-nidificata.jpg']);

        $response = $this->actingAs($editor)->get(route('admin.media', ['folder' => $folder->id]));

        $response->assertOk();
        $response->assertSee('media-card__path', false);
        $response->assertSee('archivio/foto-nidificata.jpg');
    }

    // Copertura del caso realmente riscontrato: un'anteprima che non carica
    // (file mancante sul disco) non deve produrre un secondo testo identico
    // al titolo. L'immagine rotta viene nascosta via JS (hook
    // js-media-preview, gestito in resources/views/admin/media.blade.php)
    // cosi il fallback nativo del browser (icona rotta + testo alt, spesso
    // uguale al filename) non compare mai accanto al titolo della card.
    public function test_preview_image_has_the_broken_image_fallback_hook(): void
    {
        $editor = $this->editor();
        $this->media($editor, 'file-mancante-sul-disco.jpg', ['filename' => 'file-mancante-sul-disco.jpg']);

        $response = $this->actingAs($editor)->get(route('admin.media'));

        $response->assertOk();
        $response->assertSee('js-media-preview', false);
    }

    public function test_card_shows_both_title_and_path_when_disk_name_differs_from_filename_without_a_subfolder(): void
    {
        $editor = $this->editor();
        $this->media($editor, 'hero-ai-premium-20260507195549-f08dba.png', [
            'filename' => 'hero-ai-premium.png',
        ]);

        $response = $this->actingAs($editor)->get(route('admin.media'));

        $response->assertOk();
        $response->assertSee('media-card__path', false);
        $response->assertSee('hero-ai-premium.png');
        $response->assertSee('hero-ai-premium-20260507195549-f08dba.png');
    }

    // 20. L'URL pubblico generato per un media reale punta a un file
    // effettivamente presente sul disco pubblico. Nota architetturale: il
    // client di test di Laravel dispaccia le richieste attraverso il Kernel
    // HTTP dell'applicazione, che gestisce solo le rotte registrate. Un
    // asset statico sotto public/assets/img non ha una rotta propria: in
    // produzione viene servito direttamente dal webserver (o, in sviluppo,
    // dal server integrato di "php artisan serve"), bypassando il router di
    // Laravel. Richiedere quell'URL con $this->get() restituirebbe quindi
    // sempre 404 dal Kernel, indipendentemente dalla reale esistenza del
    // file: non sarebbe un test significativo. La verifica equivalente e
    // affidabile in questa architettura e che il file esista fisicamente nel
    // percorso che asset()/Media::url risolvono (stessa tecnica gia usata
    // nei test di upload, es. AdminMediaUploadTest).
    public function test_the_public_url_of_a_real_media_resolves_to_a_file_that_exists_on_disk(): void
    {
        $editor = $this->editor();
        $this->createRealFile('verifica-url-pubblico.jpg');
        $media = $this->media($editor, 'verifica-url-pubblico.jpg', ['filename' => 'Verifica URL pubblico.jpg']);

        $this->assertSame(asset('assets/img/'.$media->disk_name), $media->url);
        $this->assertFileExists(public_path('assets/img/'.$media->disk_name));

        $response = $this->actingAs($editor)->get(route('admin.media'));
        $response->assertOk();
        $response->assertSee($media->url, false);
    }

    private function createRealFile(string $relativeDiskName): void
    {
        $path = public_path('assets/img/'.$relativeDiskName);
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $image = imagecreatetruecolor(10, 10);
        imagejpeg($image, $path);
        imagedestroy($image);
    }

    // 19. Stato vuoto specifico quando i filtri non producono risultati
    public function test_empty_state_when_filters_produce_no_results(): void
    {
        $editor = $this->editor();
        $this->media($editor, 'unica.jpg', ['filename' => 'Unica.jpg']);

        $this->actingAs($editor)->get(route('admin.media', ['q' => 'termine-inesistente-xyz']))
            ->assertOk()
            ->assertSee('Nessuna immagine corrisponde ai filtri selezionati.')
            ->assertSee('Azzera filtri');
    }

    public function test_empty_state_differs_when_no_filters_are_active(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->get(route('admin.media'))
            ->assertOk()
            ->assertDontSee('Nessuna immagine corrisponde ai filtri selezionati.')
            ->assertSee('Nessuna immagine nella radice.');
    }
}
