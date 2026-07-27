<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

class MediaMetadataUpdateTest extends TestCase
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
    private function mediaWithFile(User $owner, string $diskName, array $overrides = []): Media
    {
        $path = public_path('assets/img/'.$diskName);
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, 'fake-bytes');

        return Media::create(array_merge([
            'user_id' => $owner->id,
            'filename' => basename($diskName),
            'disk_name' => $diskName,
            'mime_type' => 'image/jpeg',
            'size' => 9,
        ], $overrides));
    }

    // 1. Visualizzazione dei metadati esistenti (precompilati nel form)
    public function test_existing_metadata_is_prefilled_in_the_details_form(): void
    {
        $editor = $this->editor();
        $media = $this->mediaWithFile($editor, 'foto.jpg', [
            'alt_text' => 'Testo alternativo esistente',
            'caption' => 'Didascalia esistente',
            'credit' => 'Credito esistente',
            'source' => 'Fonte esistente',
            'source_url' => 'https://esempio.it/fonte',
            'license' => 'CC BY 4.0',
        ]);

        $response = $this->actingAs($editor)->get(route('admin.media'));

        $response->assertOk();
        $response->assertSee('value="Testo alternativo esistente"', false);
        $response->assertSee('Didascalia esistente');
        $response->assertSee('value="Credito esistente"', false);
        $response->assertSee('value="Fonte esistente"', false);
        $response->assertSee('value="https://esempio.it/fonte"', false);
        $response->assertSee('value="CC BY 4.0"', false);
    }

    // 2. Aggiornamento corretto dei campi + 3. Persistenza dopo il salvataggio
    public function test_editor_can_update_metadata_and_changes_persist(): void
    {
        $editor = $this->editor();
        $media = $this->mediaWithFile($editor, 'foto.jpg', ['alt_text' => 'Vecchio testo']);

        $response = $this->actingAs($editor)->patch(route('admin.media.update', $media), [
            'alt_text' => 'Nuovo testo alternativo',
            'caption' => 'Nuova didascalia',
            'credit' => 'Mario Rossi',
            'source' => 'Agenzia Fotografica',
            'source_url' => 'https://example.com/foto-originale',
            'license' => 'CC BY-SA 4.0',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $media->refresh();
        $this->assertSame('Nuovo testo alternativo', $media->alt_text);
        $this->assertSame('Nuova didascalia', $media->caption);
        $this->assertSame('Mario Rossi', $media->credit);
        $this->assertSame('Agenzia Fotografica', $media->source);
        $this->assertSame('https://example.com/foto-originale', $media->source_url);
        $this->assertSame('CC BY-SA 4.0', $media->license);

        // Riaprendo la pagina, i valori aggiornati sono immediatamente visibili.
        $reloaded = $this->actingAs($editor)->get(route('admin.media'));
        $reloaded->assertSee('value="Nuovo testo alternativo"', false);
        $reloaded->assertSee('Nuova didascalia');
    }

    // 4. Nessun record duplicato creato dal salvataggio
    public function test_update_does_not_create_a_duplicate_media_record(): void
    {
        $editor = $this->editor();
        $media = $this->mediaWithFile($editor, 'foto.jpg');

        $this->assertSame(1, Media::count());

        $this->actingAs($editor)->patch(route('admin.media.update', $media), [
            'alt_text' => 'Aggiornato',
        ]);

        $this->assertSame(1, Media::count());
        $this->assertSame($media->id, Media::first()->id);
    }

    // 5. I campi facoltativi possono essere lasciati vuoti
    public function test_optional_metadata_fields_can_be_left_empty(): void
    {
        $editor = $this->editor();
        $media = $this->mediaWithFile($editor, 'foto.jpg', [
            'alt_text' => 'Qualcosa',
            'caption' => 'Qualcosa',
            'credit' => 'Qualcosa',
            'source' => 'Qualcosa',
            'source_url' => 'https://esempio.it',
            'license' => 'Qualcosa',
        ]);

        $response = $this->actingAs($editor)->patch(route('admin.media.update', $media), [
            'alt_text' => '',
            'caption' => '',
            'credit' => '',
            'source' => '',
            'source_url' => '',
            'license' => '',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $media->refresh();
        $this->assertNull($media->alt_text);
        $this->assertNull($media->caption);
        $this->assertNull($media->credit);
        $this->assertNull($media->source);
        $this->assertNull($media->source_url);
        $this->assertNull($media->license);
    }

    // 6. Validazione dell'URL fonte: valida solo quando valorizzato
    public function test_invalid_source_url_is_rejected(): void
    {
        $editor = $this->editor();
        $media = $this->mediaWithFile($editor, 'foto.jpg');

        $response = $this->actingAs($editor)
            ->from(route('admin.media'))
            ->patch(route('admin.media.update', $media), [
                'source_url' => 'non-e-un-url',
            ]);

        $response->assertSessionHasErrors('source_url');
        $this->assertNull($media->fresh()->source_url);
    }

    public function test_empty_source_url_passes_validation(): void
    {
        $editor = $this->editor();
        $media = $this->mediaWithFile($editor, 'foto.jpg');

        $response = $this->actingAs($editor)->patch(route('admin.media.update', $media), [
            'source_url' => '',
        ]);

        $response->assertSessionHasNoErrors();
    }

    // 7. Autorizzazioni
    public function test_guest_cannot_update_media_metadata(): void
    {
        $editor = $this->editor();
        $media = $this->mediaWithFile($editor, 'foto.jpg', ['alt_text' => 'Originale']);

        $this->patch(route('admin.media.update', $media), ['alt_text' => 'Hackerato'])
            ->assertRedirect(route('login'));

        $this->assertSame('Originale', $media->fresh()->alt_text);
    }

    public function test_author_without_editorial_access_cannot_update_media_metadata(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $editor = $this->editor();
        $media = $this->mediaWithFile($editor, 'foto.jpg', ['alt_text' => 'Originale']);

        $this->actingAs($author)
            ->patch(route('admin.media.update', $media), ['alt_text' => 'Hackerato'])
            ->assertRedirect(route('redazione.dashboard'));

        $this->assertSame('Originale', $media->fresh()->alt_text);
    }

    // 8. Gestione di immagini, documenti e altri file (nessun errore per tipi non-immagine)
    public function test_metadata_can_be_updated_for_a_document(): void
    {
        $editor = $this->editor();
        $media = $this->mediaWithFile($editor, 'report.pdf', ['mime_type' => 'application/pdf']);

        $response = $this->actingAs($editor)->patch(route('admin.media.update', $media), [
            'caption' => 'Report annuale',
            'credit' => 'Ufficio studi',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertSame('Report annuale', $media->fresh()->caption);
    }

    public function test_metadata_can_be_updated_for_an_other_file_type_without_errors(): void
    {
        $editor = $this->editor();
        $media = $this->mediaWithFile($editor, 'archivio.zip', ['mime_type' => 'application/zip']);

        $response = $this->actingAs($editor)->patch(route('admin.media.update', $media), [
            'alt_text' => '',
            'license' => 'Uso interno',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertSame('Uso interno', $media->fresh()->license);
    }

    // Azione "Modifica dettagli" presente nel menu Altre azioni
    public function test_edit_details_action_is_present_in_the_more_actions_menu(): void
    {
        $editor = $this->editor();
        $this->mediaWithFile($editor, 'foto.jpg');

        $this->actingAs($editor)->get(route('admin.media'))
            ->assertOk()
            ->assertSee('Modifica dettagli');
    }
}
