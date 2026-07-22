<?php

namespace Tests\Feature\Uploads;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

class AdminProfilePhotoUploadTest extends TestCase
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

    public function test_authorized_editor_can_upload_a_profile_photo_and_register_it_as_media(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $photo = UploadedFile::fake()->image('profile.jpg', 400, 400);

        $response = $this->actingAs($editor)->post(route('admin.profile.photo'), [
            'photo' => $photo,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Foto aggiornata.');

        $editor->refresh();
        $this->assertNotNull($editor->photo);

        $fullPath = public_path('assets/img/'.$editor->photo);
        $this->assertFileExists($fullPath);

        $media = Media::where('disk_name', $editor->photo)->firstOrFail();

        $this->assertSame($editor->id, $media->user_id);
        $this->assertSame('profile.jpg', $media->filename);
        $this->assertSame($editor->photo, $media->disk_name);
        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertSame(filesize($fullPath), $media->size);
    }
}
