<?php

namespace Tests\Feature\Console;

use App\Models\Media;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

class ImportLegacyMediaTest extends TestCase
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

    public function test_it_imports_a_new_image_with_the_expected_metadata(): void
    {
        $user = User::factory()->create();
        $path = $this->createImage('legacy.png');

        $this->artisan('media:import-legacy', ['--user' => (string) $user->id])
            ->assertExitCode(Command::SUCCESS);

        $media = Media::where('disk_name', 'legacy.png')->firstOrFail();

        $this->assertSame($user->id, $media->user_id);
        $this->assertSame('legacy.png', $media->filename);
        $this->assertSame('legacy.png', $media->disk_name);
        $this->assertSame('image/png', $media->mime_type);
        $this->assertSame(filesize($path), $media->size);
        $this->assertNull($media->alt_text);
    }

    public function test_it_imports_recursively_and_keeps_disk_name_relative_to_assets_img(): void
    {
        $user = User::factory()->create();
        $this->createImage('categories/nested/legacy.jpg');

        $this->artisan('media:import-legacy', [
            '--user' => $user->email,
            '--path' => 'assets/img/categories',
        ])->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseHas('media', [
            'user_id' => $user->id,
            'filename' => 'legacy.jpg',
            'disk_name' => 'categories/nested/legacy.jpg',
            'mime_type' => 'image/jpeg',
        ]);
    }

    public function test_it_ignores_non_image_files(): void
    {
        $user = User::factory()->create();
        file_put_contents(public_path('assets/img/notes.txt'), 'not an image');

        $this->artisan('media:import-legacy', ['--user' => (string) $user->id])
            ->expectsOutputToContain('Ignorati: 1')
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame(0, Media::count());
    }

    public function test_it_does_not_duplicate_or_reassign_an_existing_record(): void
    {
        $owner = User::factory()->create();
        $requestedOwner = User::factory()->create();
        $this->createImage('existing.png');

        $existing = Media::create([
            'user_id' => $owner->id,
            'filename' => 'original-name.png',
            'disk_name' => 'existing.png',
            'mime_type' => 'image/png',
            'size' => 123,
            'alt_text' => 'Esistente',
        ]);

        $this->artisan('media:import-legacy', ['--user' => (string) $requestedOwner->id])
            ->expectsOutputToContain('Gia registrati: 1')
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame(1, Media::count());
        $existing->refresh();
        $this->assertSame($owner->id, $existing->user_id);
        $this->assertSame('original-name.png', $existing->filename);
        $this->assertSame(123, $existing->size);
    }

    public function test_dry_run_reports_files_without_writing_to_the_database(): void
    {
        $user = User::factory()->create();
        $this->createImage('dry-run.png');

        $this->artisan('media:import-legacy', [
            '--user' => (string) $user->id,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('[DRY-RUN] DA IMPORTARE dry-run.png')
            ->expectsOutputToContain('Importati: 0')
            ->expectsOutputToContain('Da importare: 1')
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame(0, Media::count());
    }

    public function test_it_fails_when_the_user_does_not_exist(): void
    {
        $this->createImage('legacy.png');

        $this->artisan('media:import-legacy', ['--user' => 'missing@example.test'])
            ->expectsOutputToContain('Utente non trovato')
            ->assertExitCode(Command::FAILURE);

        $this->assertSame(0, Media::count());
    }

    public function test_it_fails_when_user_option_is_missing(): void
    {
        $this->artisan('media:import-legacy')
            ->expectsOutputToContain('L\'opzione --user e obbligatoria')
            ->assertExitCode(Command::FAILURE);

        $this->assertSame(0, Media::count());
    }

    public function test_it_fails_when_the_path_does_not_exist(): void
    {
        $user = User::factory()->create();

        $this->artisan('media:import-legacy', [
            '--user' => (string) $user->id,
            '--path' => 'assets/img/missing',
        ])
            ->expectsOutputToContain('La directory richiesta non esiste')
            ->assertExitCode(Command::FAILURE);

        $this->assertSame(0, Media::count());
    }

    public function test_it_rejects_paths_outside_public(): void
    {
        $user = User::factory()->create();

        $this->artisan('media:import-legacy', [
            '--user' => (string) $user->id,
            '--path' => '..',
        ])
            ->expectsOutputToContain('esce dalla directory public')
            ->assertExitCode(Command::FAILURE);

        $this->assertSame(0, Media::count());
    }

    public function test_it_continues_after_a_file_with_invalid_mime_type(): void
    {
        $user = User::factory()->create();
        file_put_contents(public_path('assets/img/not-really-an-image.jpg'), 'plain text');
        $this->createImage('valid.png');

        $this->artisan('media:import-legacy', ['--user' => (string) $user->id])
            ->expectsOutputToContain('MIME type non valido: not-really-an-image.jpg')
            ->expectsOutputToContain('Errori: 1')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseHas('media', ['disk_name' => 'valid.png']);
        $this->assertDatabaseMissing('media', ['disk_name' => 'not-really-an-image.jpg']);
    }

    private function createImage(string $relativePath): string
    {
        $path = public_path('assets/img/'.$relativePath);
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        UploadedFile::fake()
            ->image(basename($path), 20, 20)
            ->move($directory, basename($path));

        return $path;
    }
}
