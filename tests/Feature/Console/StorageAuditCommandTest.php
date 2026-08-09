<?php

namespace Tests\Feature\Console;

use App\Models\Media;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\Concerns\UsesIsolatedStoragePath;
use Tests\TestCase;

class StorageAuditCommandTest extends TestCase
{
    use RefreshDatabase;
    use UsesIsolatedPublicPath;
    use UsesIsolatedStoragePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedPublicPath();
        $this->setUpIsolatedStoragePath();
    }

    protected function tearDown(): void
    {
        $this->tearDownIsolatedPublicPath();
        $this->tearDownIsolatedStoragePath();
        parent::tearDown();
    }

    public function test_command_runs_successfully_on_an_empty_installation(): void
    {
        $this->artisan('storage:audit')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_text_report_contains_expected_sections(): void
    {
        $this->artisan('storage:audit')
            ->expectsOutputToContain('STORAGE KAIRUS')
            ->expectsOutputToContain('Database:')
            ->expectsOutputToContain('Backup:')
            ->expectsOutputToContain('Media:')
            ->expectsOutputToContain('Media registrati nel DB:')
            ->expectsOutputToContain('Possibili orfani')
            ->expectsOutputToContain('Possibili record con file mancante')
            ->expectsOutputToContain('Log:')
            ->expectsOutputToContain('Totale misurabile:')
            ->expectsOutputToContain('Budget hosting:')
            ->expectsOutputToContain('Top 10 immagini più pesanti')
            ->expectsOutputToContain('Top 10 directory più pesanti')
            ->expectsOutputToContain('MEDIA_PUBLIC_ROOT')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_json_output_is_valid_and_contains_expected_keys(): void
    {
        $exitCode = Artisan::call('storage:audit', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(Command::SUCCESS, $exitCode);

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('database', $decoded);
        $this->assertArrayHasKey('backup', $decoded);
        $this->assertArrayHasKey('media', $decoded);
        $this->assertArrayHasKey('logs', $decoded);
        $this->assertArrayHasKey('total_measurable_bytes', $decoded);
        $this->assertArrayHasKey('budget', $decoded);
    }

    public function test_command_never_writes_creates_or_deletes_media_files(): void
    {
        $path = public_path('assets/img/cover.jpg');
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, 'original-bytes');
        $originalMtime = filemtime($path);

        User::factory()->create();
        $media = Media::create([
            'user_id' => User::query()->first()->id,
            'filename' => 'cover.jpg',
            'disk_name' => 'cover.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 15,
        ]);

        $this->artisan('storage:audit')->assertExitCode(Command::SUCCESS);

        $this->assertFileExists($path);
        $this->assertSame('original-bytes', file_get_contents($path));
        $this->assertSame($originalMtime, filemtime($path));
        $this->assertSame('cover.jpg', $media->fresh()->disk_name);
        $this->assertDirectoryDoesNotExist(public_path('assets/img/orphaned-by-audit'));
    }

    public function test_command_creates_no_new_backup_or_log_files(): void
    {
        $backupsBefore = scandir(storage_path('backups'));
        $logsBefore = scandir(storage_path('logs'));

        $this->artisan('storage:audit')->assertExitCode(Command::SUCCESS);

        $this->assertSame($backupsBefore, scandir(storage_path('backups')));
        $this->assertSame($logsBefore, scandir(storage_path('logs')));
    }
}
