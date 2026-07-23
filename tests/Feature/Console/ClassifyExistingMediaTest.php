<?php

namespace Tests\Feature\Console;

use App\Models\Ad;
use App\Models\Article;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

class ClassifyExistingMediaTest extends TestCase
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

    private function mediaWithFile(string $diskName, string $content = 'fake-bytes'): Media
    {
        $user = User::factory()->create();
        $path = public_path('assets/img/'.$diskName);
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, $content);

        return Media::create([
            'user_id' => $user->id,
            'filename' => basename($diskName),
            'disk_name' => $diskName,
            'mime_type' => 'image/jpeg',
            'size' => strlen($content),
        ]);
    }

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo di test',
            'slug' => 'articolo-di-test-'.uniqid(),
            'body' => 'Testo generico.',
            'category' => 'scienza',
            'status' => 'draft',
            'read_minutes' => 1,
            'verification_status' => 'unverified',
        ], $overrides));
    }

    public function test_dry_run_makes_no_changes_to_file_directory_database_or_references(): void
    {
        $media = $this->mediaWithFile('cover.jpg');
        $article = $this->article(['cover_image' => 'cover.jpg']);
        $originalUpdatedAt = $media->updated_at;

        $this->artisan('media:classify-existing')
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame('cover.jpg', $media->fresh()->disk_name);
        $this->assertEquals($originalUpdatedAt, $media->fresh()->updated_at);
        $this->assertSame('cover.jpg', $article->fresh()->cover_image);
        $this->assertFileExists(public_path('assets/img/cover.jpg'));
        $this->assertFileDoesNotExist(public_path('assets/img/articles/covers/cover.jpg'));
        $this->assertDatabaseMissing('media_folders', ['path' => 'articles/covers']);
    }

    public function test_apply_moves_only_safe_media_and_updates_references(): void
    {
        $movable = $this->mediaWithFile('cover-movable.jpg');
        $this->article(['cover_image' => 'cover-movable.jpg']);

        $blocked = $this->mediaWithFile('cover-blocked.jpg');
        $this->article(['cover_image' => 'cover-blocked.jpg', 'body' => 'contiene cover-blocked.jpg nel testo']);

        $ambiguous = $this->mediaWithFile('condivisa.jpg');
        $this->article(['cover_image' => 'condivisa.jpg']);
        Ad::create([
            'name' => 'Banner ambiguo',
            'position' => 'sidebar',
            'type' => 'banner',
            'active' => true,
            'priority' => 1,
            'banner_image' => 'condivisa.jpg',
        ]);

        $unclassified = $this->mediaWithFile('senza-usi.jpg');

        $this->artisan('media:classify-existing', ['--apply' => true, '--force' => true])
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame('articles/covers/cover-movable.jpg', $movable->fresh()->disk_name);
        $this->assertSame('cover-blocked.jpg', $blocked->fresh()->disk_name);
        $this->assertSame('condivisa.jpg', $ambiguous->fresh()->disk_name);
        $this->assertSame('senza-usi.jpg', $unclassified->fresh()->disk_name);
    }

    public function test_a_technical_error_on_one_media_does_not_stop_the_others(): void
    {
        $good = $this->mediaWithFile('buono.jpg');
        $this->article(['cover_image' => 'buono.jpg']);

        $missing = Media::create([
            'user_id' => User::factory()->create()->id,
            'filename' => 'fantasma.jpg',
            'disk_name' => 'fantasma.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 10,
        ]);
        $this->article(['cover_image' => 'fantasma.jpg']);

        $this->artisan('media:classify-existing', ['--apply' => true, '--force' => true]);

        $this->assertSame('articles/covers/buono.jpg', $good->fresh()->disk_name);
        $this->assertSame('fantasma.jpg', $missing->fresh()->disk_name);
    }

    public function test_a_second_run_is_idempotent(): void
    {
        $media = $this->mediaWithFile('idempotente.jpg');
        $this->article(['cover_image' => 'idempotente.jpg']);

        $this->artisan('media:classify-existing', ['--apply' => true, '--force' => true])
            ->assertExitCode(Command::SUCCESS);
        $movedDiskName = $media->fresh()->disk_name;
        $folderCountAfterFirstRun = MediaFolder::where('path', 'articles/covers')->count();

        $this->artisan('media:classify-existing', ['--apply' => true, '--force' => true])
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame($movedDiskName, $media->fresh()->disk_name);
        $this->assertSame($folderCountAfterFirstRun, MediaFolder::where('path', 'articles/covers')->count());
        $this->assertSame(1, MediaFolder::where('path', 'articles/covers')->count());
    }

    public function test_media_id_option_restricts_the_analysis(): void
    {
        $target = $this->mediaWithFile('target.jpg');
        $this->article(['cover_image' => 'target.jpg']);
        $other = $this->mediaWithFile('altro.jpg');
        $this->article(['cover_image' => 'altro.jpg']);

        $this->artisan('media:classify-existing', ['--apply' => true, '--force' => true, '--media-id' => [$target->id]])
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame('articles/covers/target.jpg', $target->fresh()->disk_name);
        $this->assertSame('altro.jpg', $other->fresh()->disk_name);
    }

    public function test_limit_option_caps_the_number_of_media_analyzed(): void
    {
        foreach (range(1, 5) as $i) {
            $this->mediaWithFile("file-{$i}.jpg");
        }

        $this->artisan('media:classify-existing', ['--limit' => 2])
            ->expectsOutputToContain('Media analizzati')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_chunk_option_does_not_change_the_result(): void
    {
        foreach (range(1, 5) as $i) {
            $media = $this->mediaWithFile("chunk-{$i}.jpg");
            $this->article(['cover_image' => "chunk-{$i}.jpg"]);
        }

        $this->artisan('media:classify-existing', ['--apply' => true, '--force' => true, '--chunk' => 2])
            ->assertExitCode(Command::SUCCESS);

        foreach (range(1, 5) as $i) {
            $this->assertDatabaseHas('media', ['disk_name' => "articles/covers/chunk-{$i}.jpg"]);
        }
    }

    public function test_report_option_writes_a_json_plan_with_the_expected_structure(): void
    {
        $media = $this->mediaWithFile('report-me.jpg');
        $this->article(['cover_image' => 'report-me.jpg']);
        $reportPath = 'storage/app/reports/test-classification-report.json';

        $this->artisan('media:classify-existing', ['--report' => $reportPath])
            ->assertExitCode(Command::SUCCESS);

        $fullPath = base_path($reportPath);
        $this->assertFileExists($fullPath);

        $data = json_decode(file_get_contents($fullPath), true);
        $this->assertArrayHasKey('generated_at', $data);
        $this->assertArrayHasKey('plan_hash', $data);
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('results', $data);
        $this->assertArrayHasKey('unregistered_files', $data);
        $this->assertSame(1, $data['summary']['movable']);

        @unlink($fullPath);
    }

    public function test_resume_option_skips_media_already_moved_in_a_prior_report(): void
    {
        $already = $this->mediaWithFile('gia-spostato.jpg');
        $this->article(['cover_image' => 'gia-spostato.jpg']);
        $stillPending = $this->mediaWithFile('da-spostare.jpg');
        $this->article(['cover_image' => 'da-spostare.jpg']);

        $reportPath = 'storage/app/reports/test-resume-report.json';
        $this->artisan('media:classify-existing', [
            '--apply' => true, '--force' => true, '--media-id' => [$already->id], '--report' => $reportPath,
        ])->assertExitCode(Command::SUCCESS);

        $this->assertSame('articles/covers/gia-spostato.jpg', $already->fresh()->disk_name);

        $this->artisan('media:classify-existing', ['--resume' => $reportPath])
            ->expectsOutputToContain('Media analizzati')
            ->assertExitCode(Command::SUCCESS);

        // Il media gia spostato non deve comparire piu tra gli analizzati:
        // la seconda esecuzione, filtrata da --resume, ne conta solo 1.
        $output = Artisan::output();

        @unlink(base_path($reportPath));
        $this->assertStringNotContainsString('| Media analizzati | 2', $output);
    }

    public function test_declining_the_confirmation_prompt_applies_nothing(): void
    {
        $media = $this->mediaWithFile('da-confermare.jpg');
        $this->article(['cover_image' => 'da-confermare.jpg']);

        $this->artisan('media:classify-existing', ['--apply' => true])
            ->expectsConfirmation('Procedere con lo spostamento di 1 media?', 'no')
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame('da-confermare.jpg', $media->fresh()->disk_name);
    }

    public function test_accepting_the_confirmation_prompt_applies_the_move(): void
    {
        $media = $this->mediaWithFile('confermato.jpg');
        $this->article(['cover_image' => 'confermato.jpg']);

        $this->artisan('media:classify-existing', ['--apply' => true])
            ->expectsConfirmation('Procedere con lo spostamento di 1 media?', 'yes')
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame('articles/covers/confermato.jpg', $media->fresh()->disk_name);
    }

    public function test_force_option_skips_the_confirmation_prompt(): void
    {
        $media = $this->mediaWithFile('forzato.jpg');
        $this->article(['cover_image' => 'forzato.jpg']);

        $this->artisan('media:classify-existing', ['--apply' => true, '--force' => true])
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame('articles/covers/forzato.jpg', $media->fresh()->disk_name);
    }

    public function test_force_never_bypasses_a_blocking_reference(): void
    {
        $media = $this->mediaWithFile('protetta.jpg');
        $this->article(['cover_image' => 'protetta.jpg', 'body' => 'menziona protetta.jpg nel testo']);

        $this->artisan('media:classify-existing', ['--apply' => true, '--force' => true])
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame('protetta.jpg', $media->fresh()->disk_name);
    }

    public function test_manual_move_from_the_dashboard_still_works_after_introducing_the_classification_command(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $media = $this->mediaWithFile('manuale.jpg');
        $folder = MediaFolder::create(['name' => 'Archivio', 'slug' => 'archivio', 'path' => 'archivio']);
        @mkdir(public_path('assets/img/archivio'), 0775, true);

        $this->actingAs($editor)
            ->patch(route('admin.media.move', $media), ['media_folder_id' => $folder->id])
            ->assertSessionHas('success');

        $this->assertSame('archivio/manuale.jpg', $media->fresh()->disk_name);
    }
}
