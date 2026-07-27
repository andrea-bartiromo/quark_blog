<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

class MediaLookupForCoverPrefillTest extends TestCase
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

    private function mediaWithFile(User $owner, string $diskName): Media
    {
        $path = public_path('assets/img/'.$diskName);
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, 'fake-bytes');

        return Media::create([
            'user_id' => $owner->id,
            'filename' => basename($diskName),
            'disk_name' => $diskName,
            'mime_type' => 'image/jpeg',
            'size' => 9,
            'alt_text' => 'Alt esistente',
            'caption' => 'Didascalia esistente',
            'credit' => 'Credito esistente',
            'source' => 'Fonte esistente',
            'source_url' => 'https://esempio.it/foto',
            'license' => 'CC BY 4.0',
        ]);
    }

    public function test_lookup_returns_metadata_for_an_existing_media_file(): void
    {
        $editor = $this->editor();
        $this->mediaWithFile($editor, 'copertina-articolo.jpg');

        $response = $this->actingAs($editor)
            ->getJson(route('admin.media.lookup', ['disk_name' => 'copertina-articolo.jpg']));

        $response->assertOk();
        $response->assertJson([
            'found' => true,
            'alt_text' => 'Alt esistente',
            'caption' => 'Didascalia esistente',
            'credit' => 'Credito esistente',
            'source' => 'Fonte esistente',
            'source_url' => 'https://esempio.it/foto',
            'license' => 'CC BY 4.0',
        ]);
    }

    public function test_lookup_reports_not_found_for_an_unknown_disk_name(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)
            ->getJson(route('admin.media.lookup', ['disk_name' => 'non-esiste.jpg']));

        $response->assertOk();
        $response->assertExactJson(['found' => false]);
    }

    public function test_lookup_requires_the_disk_name_parameter(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)
            ->getJson(route('admin.media.lookup'))
            ->assertStatus(422);
    }

    public function test_guest_cannot_use_the_lookup_endpoint(): void
    {
        $this->getJson(route('admin.media.lookup', ['disk_name' => 'foto.jpg']))
            ->assertStatus(401);
    }

    public function test_author_without_editorial_access_cannot_use_the_lookup_endpoint(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)
            ->getJson(route('admin.media.lookup', ['disk_name' => 'foto.jpg']))
            ->assertRedirect(route('redazione.dashboard'));
    }
}
