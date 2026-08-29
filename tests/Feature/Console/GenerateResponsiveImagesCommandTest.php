<?php

namespace Tests\Feature\Console;

use App\Models\Media;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

/**
 * FASE 6 (missione S2 responsive images): copre il comando di backfill
 * legacy media:generate-responsive — dry-run di default, --execute,
 * idempotenza, --media-id, --limit, sorgente mancante, GIF esclusa, exit
 * code. Nessuna esecuzione contro dati di produzione: solo fixture locali.
 */
class GenerateResponsiveImagesCommandTest extends TestCase
{
    use RefreshDatabase;
    use UsesIsolatedPublicPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedPublicPath();
        config(['media.responsive_widths' => [480, 960]]);
    }

    protected function tearDown(): void
    {
        $this->tearDownIsolatedPublicPath();
        parent::tearDown();
    }

    private function putMedia(string $diskName, int $width = 2000, int $height = 1200, string $ext = 'webp'): Media
    {
        $path = public_path('assets/img/'.$diskName);
        @mkdir(dirname($path), 0775, true);
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 120, 200));

        match ($ext) {
            'webp' => imagewebp($image, $path, 82),
            'jpg' => imagejpeg($image, $path, 85),
            'gif' => imagegif($image, $path),
        };
        imagedestroy($image);

        return Media::create([
            'user_id' => User::factory()->create()->id,
            'filename' => basename($diskName),
            'disk_name' => $diskName,
            'mime_type' => 'image/'.($ext === 'jpg' ? 'jpeg' : $ext),
            'size' => filesize($path),
        ]);
    }

    private function variantPath(string $diskName, int $width): string
    {
        $dir = dirname($diskName);
        $base = pathinfo($diskName, PATHINFO_FILENAME);
        $ext = pathinfo($diskName, PATHINFO_EXTENSION);
        $prefix = $dir === '.' ? '' : $dir.'/';

        return public_path('assets/img/'.$prefix.$base.'-'.$width.'w.'.$ext);
    }

    public function test_dry_run_reports_missing_widths_without_writing_anything(): void
    {
        $media = $this->putMedia('foto.webp');

        $exitCode = Artisan::call('media:generate-responsive');

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFileDoesNotExist($this->variantPath($media->disk_name, 480));
        $this->assertStringContainsString('mancano: 480w, 960w', Artisan::output());
    }

    public function test_dry_run_classifies_widths_larger_than_the_original_as_not_applicable(): void
    {
        $media = $this->putMedia('quadrata-800.webp', 800, 800);

        $exitCode = Artisan::call('media:generate-responsive');

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFileDoesNotExist($this->variantPath($media->disk_name, 480));
        $this->assertFileDoesNotExist($this->variantPath($media->disk_name, 960));
        $this->assertStringContainsString('mancano: 480w', Artisan::output());
        $this->assertStringNotContainsString('960w', Artisan::output());
    }

    public function test_execute_generates_the_missing_variants(): void
    {
        $media = $this->putMedia('foto.webp');

        $exitCode = Artisan::call('media:generate-responsive', ['--execute' => true, '--force' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFileExists($this->variantPath($media->disk_name, 480));
        $this->assertFileExists($this->variantPath($media->disk_name, 960));
    }

    public function test_execute_is_idempotent_on_a_second_run(): void
    {
        $media = $this->putMedia('foto.webp');

        Artisan::call('media:generate-responsive', ['--execute' => true, '--force' => true]);
        $firstMtime = filemtime($this->variantPath($media->disk_name, 480));

        usleep(1_100_000);
        $exitCode = Artisan::call('media:generate-responsive', ['--execute' => true, '--force' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame($firstMtime, filemtime($this->variantPath($media->disk_name, 480)));
        $this->assertStringContainsString('Gia aggiornati', Artisan::output());
    }

    public function test_media_id_filter_limits_processing_to_the_selected_records(): void
    {
        $target = $this->putMedia('bersaglio.webp');
        $other = $this->putMedia('altro.webp');

        Artisan::call('media:generate-responsive', [
            '--execute' => true,
            '--force' => true,
            '--media-id' => [$target->id],
        ]);

        $this->assertFileExists($this->variantPath($target->disk_name, 480));
        $this->assertFileDoesNotExist($this->variantPath($other->disk_name, 480));
    }

    public function test_limit_option_bounds_the_number_of_media_processed(): void
    {
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $this->putMedia("foto-{$i}.webp")->id;
        }

        Artisan::call('media:generate-responsive', ['--execute' => true, '--force' => true, '--limit' => 2]);

        $generatedCount = 0;
        foreach ($ids as $id) {
            $media = Media::find($id);
            if (is_file($this->variantPath($media->disk_name, 480))) {
                $generatedCount++;
            }
        }

        $this->assertSame(2, $generatedCount, '--limit=2 deve limitare il NUMERO di media processati, non i risultati per media.');
    }

    public function test_gif_media_are_excluded_without_error(): void
    {
        $media = $this->putMedia('animato.gif', 2000, 1200, 'gif');

        $exitCode = Artisan::call('media:generate-responsive', ['--execute' => true, '--force' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFileDoesNotExist($this->variantPath($media->disk_name, 480));
        $this->assertStringContainsString('GIF escluse', Artisan::output());
    }

    public function test_media_with_missing_source_file_is_reported_and_does_not_fail_the_batch(): void
    {
        $missing = Media::create([
            'user_id' => User::factory()->create()->id,
            'filename' => 'fantasma.webp',
            'disk_name' => 'articles/covers/fantasma.webp',
            'mime_type' => 'image/webp',
            'size' => 100,
        ]);
        $existing = $this->putMedia('reale.webp');

        $exitCode = Artisan::call('media:generate-responsive', ['--execute' => true, '--force' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode, 'Un sorgente mancante non e un errore tecnico bloccante.');
        $this->assertFileExists($this->variantPath($existing->disk_name, 480));
        $this->assertStringContainsString('sorgente mancante', Artisan::output());
    }

    public function test_empty_responsive_widths_config_is_a_safe_noop(): void
    {
        config(['media.responsive_widths' => []]);
        $this->putMedia('foto.webp');

        $exitCode = Artisan::call('media:generate-responsive', ['--execute' => true, '--force' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }
}
