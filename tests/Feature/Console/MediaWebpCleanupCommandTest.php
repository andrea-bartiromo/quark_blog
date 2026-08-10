<?php

namespace Tests\Feature\Console;

use App\Models\Media;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

class MediaWebpCleanupCommandTest extends TestCase
{
    use RefreshDatabase;
    use UsesIsolatedPublicPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedPublicPath();
        Config::set('media.webp_cleanup_source_directories', []);
    }

    protected function tearDown(): void
    {
        $this->tearDownIsolatedPublicPath();
        parent::tearDown();
    }

    private function mediaDir(): string
    {
        return public_path('assets/img');
    }

    private function putImage(string $relativePath, string $ext = 'jpg'): string
    {
        $path = $this->mediaDir().'/'.$relativePath;
        @mkdir(dirname($path), 0775, true);
        $image = imagecreatetruecolor(50, 50);
        imagefill($image, 0, 0, imagecolorallocate($image, 90, 60, 180));

        match ($ext) {
            'jpg', 'jpeg' => imagejpeg($image, $path, 90),
            'png' => imagepng($image, $path),
            'webp' => imagewebp($image, $path, 90),
        };
        imagedestroy($image);

        return $path;
    }

    private function migratedPair(string $baseName, int $ageDays = 30): Media
    {
        $this->putImage($baseName.'.jpg');
        $this->putImage($baseName.'.webp', 'webp');

        $media = Media::create([
            'user_id' => User::factory()->create()->id,
            'filename' => basename($baseName).'.webp',
            'disk_name' => $baseName.'.webp',
            'mime_type' => 'image/webp',
            'size' => filesize($this->mediaDir().'/'.$baseName.'.webp'),
        ]);

        $media->timestamps = false;
        $media->updated_at = now()->subDays($ageDays);
        $media->save();
        $media->timestamps = true;

        return $media;
    }

    public function test_command_runs_successfully_on_an_empty_installation(): void
    {
        $this->artisan('media:webp-cleanup')->assertExitCode(Command::SUCCESS);
    }

    public function test_text_report_contains_expected_sections(): void
    {
        $this->migratedPair('photo', ageDays: 30);

        $this->artisan('media:webp-cleanup')
            ->expectsOutputToContain('CLEANUP WEBP')
            ->expectsOutputToContain('sola lettura')
            ->expectsOutputToContain('Candidati: 1')
            ->expectsOutputToContain('Esclusi')
            ->expectsOutputToContain('backup locale')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_json_output_is_valid_and_contains_expected_keys(): void
    {
        $this->migratedPair('photo', ageDays: 30);

        $exitCode = Artisan::call('media:webp-cleanup', ['--json' => true]);
        $decoded = json_decode(Artisan::output(), true);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('candidates', $decoded);
        $this->assertArrayHasKey('excluded', $decoded);
        $this->assertArrayHasKey('backup_confirmation_required', $decoded);
        $this->assertSame(1, $decoded['candidates']['count']);
    }

    public function test_min_age_days_option_is_forwarded_to_the_service(): void
    {
        $this->migratedPair('photo', ageDays: 2);

        $exitCode = Artisan::call('media:webp-cleanup', ['--json' => true, '--min-age-days' => 1]);
        $decoded = json_decode(Artisan::output(), true);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame(1, $decoded['candidates']['count']);
    }

    public function test_path_option_is_forwarded_to_the_service(): void
    {
        $this->migratedPair('categories/a', ageDays: 30);
        $this->migratedPair('other/b', ageDays: 30);

        $exitCode = Artisan::call('media:webp-cleanup', ['--json' => true, '--path' => 'categories']);
        $decoded = json_decode(Artisan::output(), true);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame(1, $decoded['scanned_count']);
    }

    public function test_command_never_writes_to_the_production_media_directory(): void
    {
        $this->migratedPair('photo', ageDays: 30);
        $before = array_diff(scandir($this->mediaDir()), ['.', '..']);

        $this->artisan('media:webp-cleanup')->assertExitCode(Command::SUCCESS);

        $after = array_diff(scandir($this->mediaDir()), ['.', '..']);
        sort($before);
        sort($after);
        $this->assertSame($before, $after);
    }

    public function test_command_never_modifies_the_database(): void
    {
        $media = $this->migratedPair('photo', ageDays: 30);
        $originalUpdatedAt = $media->fresh()->updated_at;

        $this->artisan('media:webp-cleanup')->assertExitCode(Command::SUCCESS);

        $this->assertEquals($originalUpdatedAt, $media->fresh()->updated_at);
    }

    public function test_command_has_no_execute_or_force_flag_to_delete_anything(): void
    {
        $definition = $this->app->make(Kernel::class)
            ->all()['media:webp-cleanup']
            ->getDefinition();

        $this->assertFalse($definition->hasOption('execute'));
        $this->assertFalse($definition->hasOption('force'));
    }
}
