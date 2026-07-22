<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use App\Services\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaServiceTest extends TestCase
{
    use RefreshDatabase;

    private MediaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new MediaService;
    }

    public function test_register_creates_a_new_media_record(): void
    {
        $user = User::factory()->create();

        $media = $this->service->register(
            $user,
            'foto.jpg',
            'foto-abc123.jpg',
            'image/jpeg',
            12345
        );

        $this->assertInstanceOf(Media::class, $media);
        $this->assertDatabaseHas('media', [
            'id' => $media->id,
            'disk_name' => 'foto-abc123.jpg',
        ]);
    }

    public function test_register_with_an_existing_disk_name_returns_the_existing_record(): void
    {
        $user = User::factory()->create();

        $first = $this->service->register($user, 'foto.jpg', 'foto-abc123.jpg', 'image/jpeg', 12345);
        $second = $this->service->register($user, 'foto-duplicata.jpg', 'foto-abc123.jpg', 'image/png', 99999);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Media::count());
    }

    public function test_register_with_different_disk_names_creates_two_distinct_records(): void
    {
        $user = User::factory()->create();

        $first = $this->service->register($user, 'foto-uno.jpg', 'foto-uno.jpg', 'image/jpeg', 111);
        $second = $this->service->register($user, 'foto-due.jpg', 'foto-due.jpg', 'image/jpeg', 222);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, Media::count());
    }

    public function test_register_saves_the_owning_user_and_metadata_correctly(): void
    {
        $user = User::factory()->create();

        $media = $this->service->register(
            $user,
            'immagine-originale.png',
            'immagine-originale-xyz789.png',
            'image/png',
            54321
        );

        $fresh = Media::findOrFail($media->id);

        $this->assertSame($user->id, $fresh->user_id);
        $this->assertSame('immagine-originale.png', $fresh->filename);
        $this->assertSame('immagine-originale-xyz789.png', $fresh->disk_name);
        $this->assertSame('image/png', $fresh->mime_type);
        $this->assertSame(54321, $fresh->size);
    }
}
