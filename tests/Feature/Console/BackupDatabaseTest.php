<?php

namespace Tests\Feature\Console;

use App\Console\Commands\BackupDatabase;
use Illuminate\Support\Carbon;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

class BackupDatabaseTest extends TestCase
{
    private string $source;

    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = database_path('database.sqlite');
        $this->backupDir = storage_path('backups');

        if (! is_dir(dirname($this->source))) {
            mkdir(dirname($this->source), 0755, true);
        }
        file_put_contents($this->source, 'sqlite-backup-fixture');

        if (is_dir($this->backupDir)) {
            foreach (glob($this->backupDir.'/*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        } else {
            mkdir($this->backupDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->backupDir.'/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (file_exists($this->source)) {
            unlink($this->source);
        }

        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_success_creates_a_non_empty_final_backup_without_temp_files(): void
    {
        $tester = $this->tester();

        $this->assertSame(SymfonyCommand::SUCCESS, $tester->execute(['--keep' => 7]));

        $files = glob($this->backupDir.'/database-*.sqlite') ?: [];
        $this->assertCount(1, $files);
        $this->assertGreaterThan(0, filesize($files[0]));
        $this->assertSame([], glob($this->backupDir.'/*.tmp') ?: []);
    }

    public function test_zero_byte_source_fails_without_leaving_a_final_backup(): void
    {
        file_put_contents($this->source, '');

        $tester = $this->tester();

        $this->assertSame(SymfonyCommand::FAILURE, $tester->execute(['--keep' => 7]));
        $this->assertSame([], glob($this->backupDir.'/database-*.sqlite') ?: []);
        $this->assertSame([], glob($this->backupDir.'/*.tmp') ?: []);
    }

    public function test_two_runs_in_the_same_second_do_not_overwrite_each_other(): void
    {
        Carbon::setTestNow('2026-08-11 20:00:00');

        $this->assertSame(SymfonyCommand::SUCCESS, $this->tester()->execute(['--keep' => 7]));
        $this->assertSame(SymfonyCommand::SUCCESS, $this->tester()->execute(['--keep' => 7]));

        $files = glob($this->backupDir.'/database-*.sqlite') ?: [];
        $this->assertCount(2, $files);
        $this->assertNotSame(basename($files[0]), basename($files[1]));
    }

    public function test_failed_new_backup_preserves_existing_valid_backups(): void
    {
        $old = $this->backupDir.'/database-2026-08-10-020000-old.sqlite';
        file_put_contents($old, 'valid-old-backup');
        file_put_contents($this->source, '');

        $this->assertSame(SymfonyCommand::FAILURE, $this->tester()->execute(['--keep' => 1]));
        $this->assertFileExists($old);
        $this->assertSame('valid-old-backup', file_get_contents($old));
    }

    private function tester(): CommandTester
    {
        $command = app(BackupDatabase::class);
        $command->setLaravel(app());

        return new CommandTester($command);
    }
}
