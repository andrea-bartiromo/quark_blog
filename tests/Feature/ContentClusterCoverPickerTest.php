<?php

namespace Tests\Feature;

use App\Models\ContentCluster;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentClusterCoverPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_edit_forms_expose_library_upload_preview_and_remove_cover_actions(): void
    {
        $editor = $this->editor();

        $create = $this->actingAs($editor)->get(route('admin.content-clusters.create'));
        $create->assertOk()
            ->assertSee('Scegli dalla libreria')
            ->assertSee('Carica immagine')
            ->assertSee('Cambia immagine')
            ->assertSee('Rimuovi cover')
            ->assertSee(route('admin.content-clusters.media-picker'), false)
            ->assertSee(route('admin.media.upload'), false)
            ->assertSee('Cover media path')
            ->assertSee('Avanzate');

        $cluster = ContentCluster::factory()->create(['cover_image' => 'percorsi/esistente.webp']);
        $edit = $this->actingAs($editor)->get(route('admin.content-clusters.edit', $cluster));
        $edit->assertOk()
            ->assertSee('value="percorsi/esistente.webp"', false)
            ->assertSee(asset('assets/img/percorsi/esistente.webp'), false);
    }

    public function test_media_picker_returns_only_images_and_supports_search(): void
    {
        $editor = $this->editor();
        Media::create([
            'user_id' => $editor->id,
            'filename' => 'galassia.jpg',
            'disk_name' => 'uploads/galassia.webp',
            'mime_type' => 'image/webp',
            'size' => 1200,
            'alt_text' => 'Galassia spirale',
        ]);
        Media::create([
            'user_id' => $editor->id,
            'filename' => 'note.txt',
            'disk_name' => 'uploads/note.txt',
            'mime_type' => 'text/plain',
            'size' => 50,
        ]);

        $response = $this->actingAs($editor)->getJson(route('admin.content-clusters.media-picker', ['q' => 'galassia']));

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.filename', 'galassia.jpg')
            ->assertJsonPath('data.0.disk_name', 'uploads/galassia.webp');
    }

    public function test_cover_path_persistence_remains_unchanged_and_cover_can_be_removed(): void
    {
        $editor = $this->editor();
        $cluster = ContentCluster::factory()->create(['name' => 'Percorso', 'slug' => 'percorso', 'cover_image' => 'uploads/old.webp']);

        $this->actingAs($editor)->put(route('admin.content-clusters.update', $cluster), [
            'name' => 'Percorso',
            'slug' => 'percorso',
            'cover_image' => 'uploads/new.webp',
        ])->assertRedirect(route('admin.content-clusters.edit', $cluster));

        $this->assertSame('uploads/new.webp', $cluster->fresh()->cover_image);

        $this->actingAs($editor)->put(route('admin.content-clusters.update', $cluster), [
            'name' => 'Percorso',
            'slug' => 'percorso',
            'cover_image' => '',
        ])->assertRedirect(route('admin.content-clusters.edit', $cluster));

        $this->assertNull($cluster->fresh()->cover_image);
    }

    public function test_validation_failure_preserves_selected_cover_in_old_input(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)
            ->from(route('admin.content-clusters.create'))
            ->post(route('admin.content-clusters.store'), [
                'name' => '',
                'cover_image' => 'uploads/preserved.webp',
            ]);

        $response->assertRedirect(route('admin.content-clusters.create'))
            ->assertSessionHasErrors('name')
            ->assertSessionHasInput('cover_image', 'uploads/preserved.webp');
    }

    public function test_guest_cannot_use_media_picker_or_direct_media_upload(): void
    {
        $this->get(route('admin.content-clusters.media-picker'))->assertRedirect(route('login'));
        $this->post(route('admin.media.upload'))->assertRedirect(route('login'));
    }

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }
}
