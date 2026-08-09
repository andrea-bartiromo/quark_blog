<?php

namespace Tests\Feature\Console;

use App\Models\Article;
use App\Models\Media;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

class MediaWebpAuditCommandTest extends TestCase
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

    private function putCandidate(string $diskName): void
    {
        $path = public_path('assets/img/'.$diskName);
        @mkdir(dirname($path), 0775, true);
        $image = imagecreatetruecolor(300, 300);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 50, 50));
        imagepng($image, $path);
        imagedestroy($image);

        Media::create([
            'user_id' => User::factory()->create()->id,
            'filename' => $diskName,
            'disk_name' => $diskName,
            'mime_type' => 'image/png',
            'size' => filesize($path),
        ]);

        Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo',
            'slug' => 'articolo-'.uniqid(),
            'body' => 'Corpo generico.',
            'category' => 'scienza',
            'status' => 'draft',
            'read_minutes' => 1,
            'verification_status' => 'unverified',
            'cover_image' => $diskName,
        ]);
    }

    public function test_command_runs_successfully_on_an_empty_installation(): void
    {
        $this->artisan('media:webp-audit')->assertExitCode(Command::SUCCESS);
    }

    public function test_text_report_contains_expected_sections(): void
    {
        $this->putCandidate('cover.png');

        $this->artisan('media:webp-audit')
            ->expectsOutputToContain('AUDIT WEBP')
            ->expectsOutputToContain('Immagini analizzate')
            ->expectsOutputToContain('Formati sorgente')
            ->expectsOutputToContain('Candidati alla conversione')
            ->expectsOutputToContain('Escluse dalla conversione automatica')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_json_output_is_valid_and_contains_expected_keys(): void
    {
        $this->putCandidate('cover.png');

        $exitCode = Artisan::call('media:webp-audit', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(Command::SUCCESS, $exitCode);

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('scanned_count', $decoded);
        $this->assertArrayHasKey('candidates', $decoded);
        $this->assertArrayHasKey('excluded', $decoded);
        $this->assertArrayHasKey('safe_duplicates', $decoded);
        $this->assertSame(1, $decoded['candidates']['count']);
    }

    public function test_no_measure_flag_is_forwarded_to_the_service(): void
    {
        $this->putCandidate('cover.png');

        $exitCode = Artisan::call('media:webp-audit', ['--json' => true, '--no-measure' => true]);
        $decoded = json_decode(Artisan::output(), true);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertNull($decoded['candidates']['files'][0]['estimated_webp_size_bytes']);
    }

    public function test_command_never_writes_to_the_production_media_directory(): void
    {
        $this->putCandidate('cover.png');
        $before = array_diff(scandir(public_path('assets/img')), ['.', '..']);

        $this->artisan('media:webp-audit')->assertExitCode(Command::SUCCESS);

        $after = array_diff(scandir(public_path('assets/img')), ['.', '..']);
        sort($before);
        sort($after);
        $this->assertSame($before, $after);
    }

    public function test_command_never_modifies_the_database(): void
    {
        $this->putCandidate('cover.png');
        $media = Media::first();
        $originalDiskName = $media->disk_name;
        $originalUpdatedAt = $media->updated_at;

        $this->artisan('media:webp-audit')->assertExitCode(Command::SUCCESS);

        $this->assertSame($originalDiskName, $media->fresh()->disk_name);
        $this->assertEquals($originalUpdatedAt, $media->fresh()->updated_at);
    }

    public function test_only_option_filters_by_extension(): void
    {
        $this->putCandidate('cover.png');

        $exitCode = Artisan::call('media:webp-audit', ['--json' => true, '--only' => 'jpg']);
        $decoded = json_decode(Artisan::output(), true);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame(0, $decoded['scanned_count']);
    }
}
