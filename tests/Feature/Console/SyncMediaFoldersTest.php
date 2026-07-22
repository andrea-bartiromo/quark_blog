<?php

namespace Tests\Feature\Console;

use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

class SyncMediaFoldersTest extends TestCase
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

    public function test_it_creates_missing_parent_hierarchy_without_changing_media_or_files(): void
    {
        $media = $this->media('turing/enigma/example.jpg');
        $originalUpdatedAt = $media->updated_at;

        $this->artisan('media:sync-folders')
            ->expectsOutputToContain('Categorie create: 2')
            ->assertExitCode(Command::SUCCESS);

        $turing = MediaFolder::where('path', 'turing')->firstOrFail();
        $enigma = MediaFolder::where('path', 'turing/enigma')->firstOrFail();
        $this->assertSame($turing->id, $enigma->parent_id);
        $media->refresh();
        $this->assertSame('turing/enigma/example.jpg', $media->disk_name);
        $this->assertTrue($media->updated_at->equalTo($originalUpdatedAt));
        $this->assertDirectoryExists(public_path('assets/img/turing/enigma'));
    }

    public function test_it_is_idempotent_and_ignores_root_disk_names(): void
    {
        $this->media('root.jpg');
        $this->media('articles/covers/example.jpg');

        $this->artisan('media:sync-folders')->assertExitCode(Command::SUCCESS);
        $count = MediaFolder::count();

        $this->artisan('media:sync-folders')
            ->expectsOutputToContain('Categorie create: 0')
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame($count, MediaFolder::count());
        $this->assertDatabaseMissing('media_folders', ['path' => 'root']);
    }

    public function test_dry_run_has_no_persistent_effects(): void
    {
        $this->media('turing/enigma/example.jpg');

        $this->artisan('media:sync-folders', ['--dry-run' => true])
            ->expectsOutputToContain('[DRY-RUN] DA CREARE turing')
            ->expectsOutputToContain('Categorie da creare: 2')
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame(0, MediaFolder::count());
        $this->assertDirectoryDoesNotExist(public_path('assets/img/turing'));
    }

    public function test_invalid_paths_are_reported_without_modifying_media(): void
    {
        $media = $this->media('turing/../../escape.jpg');

        $this->artisan('media:sync-folders')
            ->expectsOutputToContain('PERCORSO NON VALIDO')
            ->expectsOutputToContain('Percorsi non validi: 1')
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame('turing/../../escape.jpg', $media->fresh()->disk_name);
        $this->assertSame(0, MediaFolder::count());
    }

    private function media(string $diskName): Media
    {
        $user = User::factory()->create();

        return Media::create([
            'user_id' => $user->id,
            'filename' => basename($diskName),
            'disk_name' => $diskName,
            'mime_type' => 'image/jpeg',
            'size' => 10,
        ]);
    }
}
