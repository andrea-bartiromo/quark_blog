<?php

namespace Tests\Feature\Console;

use App\Models\Media;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

class RealignMediaMimeTypesTest extends TestCase
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

    // 1. Rileva correttamente il MIME reale confrontandolo col contenuto del file
    public function test_it_detects_a_png_file_incorrectly_registered_with_a_jpg_extension_and_mime(): void
    {
        $user = User::factory()->create();
        $this->writePng('hero-spazio.jpg');
        $media = Media::create($this->mediaAttributes($user, 'hero-spazio.jpg', 'image/jpeg'));

        $this->artisan('media:realign-mime')
            ->expectsOutputToContain('INCOERENTE hero-spazio.jpg (id '.$media->id.'): image/jpeg -> image/png')
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame('image/png', $media->refresh()->mime_type);
    }

    // 2. Il dry-run non scrive nulla nel database
    public function test_dry_run_reports_the_mismatch_without_writing_to_the_database(): void
    {
        $user = User::factory()->create();
        $this->writePng('hero-spazio.jpg');
        $media = Media::create($this->mediaAttributes($user, 'hero-spazio.jpg', 'image/jpeg'));

        $this->artisan('media:realign-mime', ['--dry-run' => true])
            ->expectsOutputToContain('[DRY-RUN] INCOERENTE hero-spazio.jpg (id '.$media->id.'): image/jpeg -> image/png')
            ->expectsOutputToContain('Da aggiornare (dry-run): 1')
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame('image/jpeg', $media->refresh()->mime_type);
    }

    // 3. Aggiorna un record realmente incoerente
    public function test_it_updates_a_genuinely_incoherent_record(): void
    {
        $user = User::factory()->create();
        $this->writePng('incoerente.jpg');
        $media = Media::create($this->mediaAttributes($user, 'incoerente.jpg', 'image/jpeg'));

        $this->artisan('media:realign-mime')->assertExitCode(Command::SUCCESS);

        $this->assertSame('image/png', $media->refresh()->mime_type);
    }

    // 4. Segnala un file mancante senza modificare il record ne' fallire
    public function test_it_reports_a_missing_file_without_touching_the_record(): void
    {
        $user = User::factory()->create();
        $media = Media::create($this->mediaAttributes($user, 'fantasma.jpg', 'image/jpeg'));

        $this->artisan('media:realign-mime')
            ->expectsOutputToContain('FILE MANCANTE: fantasma.jpg (id '.$media->id.')')
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame('image/jpeg', $media->refresh()->mime_type);
    }

    // 5. Non tocca mai disk_name, filename o il file su disco
    public function test_it_preserves_disk_name_filename_and_the_file_itself(): void
    {
        $user = User::factory()->create();
        $path = $this->writePng('articles/covers/hero-spazio.jpg');
        $originalBytes = file_get_contents($path);
        $media = Media::create($this->mediaAttributes($user, 'articles/covers/hero-spazio.jpg', 'image/jpeg', 'hero-spazio-original.jpg'));

        $this->artisan('media:realign-mime')->assertExitCode(Command::SUCCESS);
        $media->refresh();

        $this->assertSame('articles/covers/hero-spazio.jpg', $media->disk_name);
        $this->assertSame('hero-spazio-original.jpg', $media->filename);
        $this->assertSame('image/png', $media->mime_type);
        $this->assertSame($originalBytes, file_get_contents($path));
    }

    // 6. Non modifica record gia coerenti con il contenuto reale
    public function test_it_leaves_already_coherent_records_untouched(): void
    {
        $user = User::factory()->create();
        $this->writePng('coerente.png');
        $media = Media::create($this->mediaAttributes($user, 'coerente.png', 'image/png'));

        $this->artisan('media:realign-mime')
            ->expectsOutputToContain('Gia coerenti: 1')
            ->expectsOutputToContain('Incoerenti: 0')
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame('image/png', $media->refresh()->mime_type);
    }

    private function writePng(string $relativePath): string
    {
        $path = public_path('assets/img/'.$relativePath);
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $image = imagecreatetruecolor(10, 10);
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function mediaAttributes(User $user, string $diskName, string $mimeType, ?string $filename = null): array
    {
        return [
            'user_id' => $user->id,
            'filename' => $filename ?? basename($diskName),
            'disk_name' => $diskName,
            'mime_type' => $mimeType,
            'size' => 100,
        ];
    }
}
