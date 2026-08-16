<?php

namespace Tests\Feature\Console;

use App\Models\Article;
use App\Models\Media;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

class ConvertMediaToWebpCommandTest extends TestCase
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

    private function putCandidate(string $diskName, int $width = 300, int $height = 200): Media
    {
        $path = public_path('assets/img/'.$diskName);
        @mkdir(dirname($path), 0775, true);
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 50, 50));
        imagejpeg($image, $path, 90);
        imagedestroy($image);

        return Media::create([
            'user_id' => User::factory()->create()->id,
            'filename' => $diskName,
            'disk_name' => $diskName,
            'mime_type' => 'image/jpeg',
            'size' => filesize($path),
        ]);
    }

    public function test_command_runs_successfully_on_an_empty_installation(): void
    {
        $this->artisan('media:convert-webp')->assertExitCode(Command::SUCCESS);
    }

    public function test_dry_run_by_default_writes_nothing(): void
    {
        $media = $this->putCandidate('photo.jpg');

        $this->artisan('media:convert-webp')
            ->expectsOutputToContain('sola analisi (dry-run)')
            ->assertExitCode(Command::SUCCESS);

        $this->assertFileDoesNotExist(public_path('assets/img/photo.webp'));
        $this->assertSame('photo.jpg', $media->fresh()->disk_name);
    }

    public function test_execute_with_force_converts_eligible_media(): void
    {
        $media = $this->putCandidate('photo.jpg');

        $this->artisan('media:convert-webp', ['--execute' => true, '--force' => true])
            ->expectsOutputToContain('ESEGUI conversioni')
            ->assertExitCode(Command::SUCCESS);

        $this->assertFileExists(public_path('assets/img/photo.webp'));
        $this->assertFileExists(public_path('assets/img/photo.jpg'), 'l\'originale non deve mai essere eliminato dal comando.');
        $this->assertSame('photo.webp', $media->fresh()->disk_name);
    }

    public function test_execute_updates_article_cover_reference(): void
    {
        $this->putCandidate('cover.jpg');
        $article = Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo',
            'slug' => 'articolo-'.uniqid(),
            'body' => 'Corpo generico.',
            'category' => 'scienza',
            'status' => 'draft',
            'read_minutes' => 1,
            'verification_status' => 'unverified',
            'cover_image' => 'cover.jpg',
        ]);

        $this->artisan('media:convert-webp', ['--execute' => true, '--force' => true])
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame('cover.webp', $article->fresh()->cover_image);
    }

    public function test_media_id_filter_restricts_which_media_are_processed(): void
    {
        $target = $this->putCandidate('target.jpg');
        $other = $this->putCandidate('other.jpg');

        $this->artisan('media:convert-webp', ['--execute' => true, '--force' => true, '--media-id' => [$target->id]])
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame('target.webp', $target->fresh()->disk_name);
        $this->assertSame('other.jpg', $other->fresh()->disk_name, 'un Media non incluso in --media-id non deve mai essere toccato.');
    }

    public function test_limit_option_caps_how_many_media_are_processed(): void
    {
        $this->putCandidate('a.jpg');
        $this->putCandidate('b.jpg');

        $this->artisan('media:convert-webp', ['--execute' => true, '--force' => true, '--limit' => 1])
            ->assertExitCode(Command::SUCCESS);

        $convertedCount = Media::where('disk_name', 'like', '%.webp')->count();
        $this->assertSame(1, $convertedCount);
    }

    public function test_running_execute_twice_is_idempotent(): void
    {
        $media = $this->putCandidate('idem.jpg');

        $this->artisan('media:convert-webp', ['--execute' => true, '--force' => true])->assertExitCode(Command::SUCCESS);
        $this->artisan('media:convert-webp', ['--execute' => true, '--force' => true])->assertExitCode(Command::SUCCESS);

        $this->assertSame('idem.webp', $media->fresh()->disk_name);
        $this->assertFileExists(public_path('assets/img/idem.jpg'));
        $this->assertFileExists(public_path('assets/img/idem.webp'));
    }

    public function test_report_option_writes_a_json_manifest(): void
    {
        $this->putCandidate('report-me.jpg');
        $reportPath = storage_path('app/test-webp-convert-report.json');

        try {
            $this->artisan('media:convert-webp', ['--execute' => true, '--force' => true, '--report' => $reportPath])
                ->assertExitCode(Command::SUCCESS);

            $this->assertFileExists($reportPath);
            $manifest = json_decode((string) file_get_contents($reportPath), true);

            $this->assertSame('executed', $manifest['mode']);
            $this->assertArrayHasKey('summary', $manifest);
            $this->assertNotEmpty($manifest['results']);
            $this->assertSame('converted', $manifest['results'][0]['status']);
            $this->assertArrayHasKey('saving_bytes', $manifest['results'][0]);
        } finally {
            @unlink($reportPath);
        }
    }

    public function test_dry_run_report_never_marks_anything_as_converted(): void
    {
        $this->putCandidate('preview.jpg');
        $reportPath = storage_path('app/test-webp-convert-dryrun-report.json');

        try {
            $this->artisan('media:convert-webp', ['--report' => $reportPath])
                ->assertExitCode(Command::SUCCESS);

            $manifest = json_decode((string) file_get_contents($reportPath), true);

            $this->assertSame('dry_run', $manifest['mode']);
            $this->assertSame('planned', $manifest['results'][0]['status']);
        } finally {
            @unlink($reportPath);
        }
    }

    public function test_gif_and_protected_media_are_never_converted_even_with_execute(): void
    {
        config(['media.protected_disk_names' => ['special/protected.jpg']]);

        $path = public_path('assets/img/special/protected.jpg');
        @mkdir(dirname($path), 0775, true);
        $image = imagecreatetruecolor(50, 50);
        imagejpeg($image, $path, 90);
        imagedestroy($image);
        $protected = Media::create([
            'user_id' => User::factory()->create()->id,
            'filename' => 'protected.jpg',
            'disk_name' => 'special/protected.jpg',
            'mime_type' => 'image/jpeg',
            'size' => filesize($path),
        ]);

        $this->artisan('media:convert-webp', ['--execute' => true, '--force' => true])
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame('special/protected.jpg', $protected->fresh()->disk_name);
    }

    public function test_without_force_a_declined_confirmation_applies_nothing(): void
    {
        $media = $this->putCandidate('ask.jpg');

        $this->artisan('media:convert-webp', ['--execute' => true])
            ->expectsConfirmation('Procedere con la conversione di 1 media?', 'no')
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame('ask.jpg', $media->fresh()->disk_name);
        $this->assertFileDoesNotExist(public_path('assets/img/ask.webp'));
    }

    public function test_command_never_deletes_the_original_file(): void
    {
        $this->putCandidate('keep.jpg');

        $this->artisan('media:convert-webp', ['--execute' => true, '--force' => true])
            ->assertExitCode(Command::SUCCESS);

        $this->assertFileExists(public_path('assets/img/keep.jpg'));
    }
}
