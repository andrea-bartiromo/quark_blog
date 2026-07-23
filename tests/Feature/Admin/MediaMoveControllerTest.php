<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

class MediaMoveControllerTest extends TestCase
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

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
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

    private function folder(string $path): MediaFolder
    {
        @mkdir(public_path('assets/img/'.$path), 0775, true);

        return MediaFolder::create(['name' => ucfirst($path), 'slug' => $path, 'path' => $path]);
    }

    public function test_guest_cannot_move_media(): void
    {
        $editor = $this->editor();
        $media = $this->mediaWithFile($editor, 'foto.jpg');
        $folder = $this->folder('archivio');

        $this->patch(route('admin.media.move', $media), ['media_folder_id' => $folder->id])
            ->assertRedirect(route('login'));

        $this->assertSame('foto.jpg', $media->fresh()->disk_name);
    }

    public function test_user_without_editorial_access_cannot_move_media(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $editor = $this->editor();
        $media = $this->mediaWithFile($editor, 'foto.jpg');
        $folder = $this->folder('archivio');

        $this->actingAs($author)
            ->patch(route('admin.media.move', $media), ['media_folder_id' => $folder->id])
            ->assertRedirect(route('redazione.dashboard'));

        $this->assertSame('foto.jpg', $media->fresh()->disk_name);
    }

    public function test_editor_can_move_media_to_a_folder(): void
    {
        $editor = $this->editor();
        $media = $this->mediaWithFile($editor, 'foto.jpg');
        $folder = $this->folder('archivio');

        $response = $this->actingAs($editor)
            ->patch(route('admin.media.move', $media), ['media_folder_id' => $folder->id]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame('archivio/foto.jpg', $media->fresh()->disk_name);
    }

    public function test_admin_can_move_media_to_root(): void
    {
        $admin = $this->admin();
        $folder = $this->folder('archivio');
        $media = $this->mediaWithFile($admin, 'archivio/foto.jpg');

        $response = $this->actingAs($admin)
            ->patch(route('admin.media.move', $media), ['media_folder_id' => '']);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame('foto.jpg', $media->fresh()->disk_name);
    }

    public function test_validation_rejects_a_nonexistent_destination_folder(): void
    {
        $editor = $this->editor();
        $media = $this->mediaWithFile($editor, 'foto.jpg');

        $this->actingAs($editor)
            ->patch(route('admin.media.move', $media), ['media_folder_id' => 999999])
            ->assertSessionHasErrors('media_folder_id');

        $this->assertSame('foto.jpg', $media->fresh()->disk_name);
    }

    public function test_blocked_move_shows_an_error_message_and_leaves_the_file_untouched(): void
    {
        $editor = $this->editor();
        $media = $this->mediaWithFile($editor, 'protetta.jpg');
        $folder = $this->folder('archivio');
        Article::create([
            'user_id' => $editor->id,
            'title' => 'Con html inline',
            'slug' => 'con-html-inline',
            'body' => 'Vedi <img src="/assets/img/protetta.jpg">',
            'category' => 'scienza',
            'status' => 'draft',
            'read_minutes' => 1,
            'verification_status' => 'unverified',
        ]);

        $response = $this->actingAs($editor)
            ->patch(route('admin.media.move', $media), ['media_folder_id' => $folder->id]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame('protetta.jpg', $media->fresh()->disk_name);
        $this->assertFileExists(public_path('assets/img/protetta.jpg'));
    }

    public function test_preflight_endpoint_has_no_side_effects(): void
    {
        $editor = $this->editor();
        $media = $this->mediaWithFile($editor, 'foto.jpg');
        $folder = $this->folder('archivio');

        $response = $this->actingAs($editor)
            ->getJson(route('admin.media.move-preflight', $media).'?media_folder_id='.$folder->id);

        $response->assertOk();
        $response->assertJsonStructure([
            'old_disk_name', 'new_disk_name', 'is_noop', 'can_move',
            'updatable_references', 'blocking_references', 'informational_references', 'total_usage_count',
        ]);
        $this->assertSame('archivio/foto.jpg', $response->json('new_disk_name'));

        // Nessuna scrittura: ne su disco ne nel database.
        $this->assertSame('foto.jpg', $media->fresh()->disk_name);
        $this->assertFileExists(public_path('assets/img/foto.jpg'));
        $this->assertFileDoesNotExist(public_path('assets/img/archivio/foto.jpg'));
    }

    public function test_preflight_endpoint_reports_noop_for_the_current_destination(): void
    {
        $editor = $this->editor();
        $folder = $this->folder('archivio');
        $media = $this->mediaWithFile($editor, 'archivio/foto.jpg');

        $response = $this->actingAs($editor)
            ->getJson(route('admin.media.move-preflight', $media).'?media_folder_id='.$folder->id);

        $response->assertOk();
        $this->assertTrue($response->json('is_noop'));
    }

    public function test_move_action_is_visible_in_the_dashboard(): void
    {
        $editor = $this->editor();
        $this->mediaWithFile($editor, 'foto-visibile.jpg');

        $this->actingAs($editor)->get(route('admin.media'))
            ->assertOk()
            ->assertSee('Sposta');
    }

    public function test_folder_navigation_still_works_after_introducing_the_move_feature(): void
    {
        $editor = $this->editor();
        $folder = $this->folder('archivio');
        $this->mediaWithFile($editor, 'root-direct.jpg');
        $this->mediaWithFile($editor, 'archivio/dentro.jpg');

        $this->actingAs($editor)->get(route('admin.media'))
            ->assertOk()
            ->assertSee('root-direct.jpg')
            ->assertDontSee('dentro.jpg');

        $this->actingAs($editor)->get(route('admin.media', ['folder' => $folder->id]))
            ->assertOk()
            ->assertSee('dentro.jpg');
    }

    public function test_upload_into_a_folder_still_works_after_introducing_the_move_feature(): void
    {
        $editor = $this->editor();
        $folder = $this->folder('archivio');
        $image = UploadedFile::fake()->image('nuova.jpg', 100, 100);

        $response = $this->actingAs($editor)->post(route('admin.media.store'), [
            'image' => $image,
            'media_folder_id' => $folder->id,
        ]);

        $response->assertRedirect();
        $media = Media::latest('id')->firstOrFail();
        $this->assertStringStartsWith('archivio/', $media->disk_name);
        $this->assertFileExists(public_path('assets/img/'.$media->disk_name));
    }
}
