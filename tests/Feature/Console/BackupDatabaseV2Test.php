<?php

namespace Tests\Feature\Console;

use App\Contracts\DatabaseDumpRunner;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class BackupDatabaseV2Test extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('framework/testing/backup-v2-'.bin2hex(random_bytes(4)));
        config()->set('backup.v2.directory', $this->directory);
        config()->set('backup.v2.binary', PHP_BINARY);
        config()->set('backup.v2.retention', null);
        config()->set('database.default', 'mariadb');
        config()->set('database.connections.mariadb.host', '127.0.0.1');
        config()->set('database.connections.mariadb.port', '3306');
        config()->set('database.connections.mariadb.database', 'kairus_test');
        config()->set('database.connections.mariadb.username', 'kairus');
        config()->set('database.connections.mariadb.password', 'super-secret-test-password');
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            foreach (glob($this->directory.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->directory);
        }

        parent::tearDown();
    }

    public function test_rejects_unsupported_database_driver_before_dump(): void
    {
        config()->set('database.default', 'sqlite');
        $runner = new RecordingDumpRunner;
        $this->app->instance(DatabaseDumpRunner::class, $runner);

        $this->artisan('backup:database-v2')->assertFailed();
        $this->assertSame(0, $runner->calls);
    }

    public function test_rejects_missing_dump_binary(): void
    {
        config()->set('backup.v2.binary', '/definitely/missing/mariadb-dump');

        $this->artisan('backup:database-v2')->assertFailed();
    }

    public function test_rejects_zero_byte_dump_and_cleans_temporary_artifact(): void
    {
        $runner = new RecordingDumpRunner('');
        $this->app->instance(DatabaseDumpRunner::class, $runner);

        $this->artisan('backup:database-v2')->assertFailed();
        $this->assertSame([], glob($this->directory.'/*') ?: []);
    }

    public function test_rejects_invalid_dump_structure(): void
    {
        $this->app->instance(DatabaseDumpRunner::class, new RecordingDumpRunner('<html>upstream error</html>'));

        $this->artisan('backup:database-v2')->assertFailed();
        $this->assertSame([], glob($this->directory.'/*.sql') ?: []);
    }

    public function test_dump_failure_preserves_previous_valid_backup_and_redacts_secret(): void
    {
        mkdir($this->directory, 0700, true);
        $previous = $this->directory.'/mariadb-previous.sql';
        file_put_contents($previous, '-- MariaDB dump\nCREATE TABLE previous (id INT);');
        $this->app->instance(DatabaseDumpRunner::class, new ThrowingDumpRunner);

        $this->artisan('backup:database-v2')
            ->expectsOutputToContain('Database dump process failed.')
            ->doesntExpectOutputToContain('super-secret-test-password')
            ->assertFailed();

        $this->assertFileExists($previous);
    }

    public function test_success_atomically_publishes_dump_and_metadata_without_secret(): void
    {
        $runner = new RecordingDumpRunner("-- MariaDB dump\nCREATE TABLE example (id INT);\nINSERT INTO example VALUES (1);\n");
        $this->app->instance(DatabaseDumpRunner::class, $runner);

        $this->artisan('backup:database-v2')->assertSuccessful();

        $artifacts = glob($this->directory.'/mariadb-*.sql') ?: [];
        $this->assertCount(1, $artifacts);
        $this->assertFileExists($artifacts[0].'.json');
        $this->assertSame([], glob($this->directory.'/*.tmp') ?: []);
        $this->assertStringNotContainsString('super-secret-test-password', file_get_contents($artifacts[0].'.json'));
        $this->assertSame(1, $runner->calls);
        $this->assertStringContainsString('password="super-secret-test-password"', $runner->optionContents);
        $this->assertFalse(file_exists($runner->optionPath));
    }

    public function test_pre_migration_mode_requires_exact_release_sha(): void
    {
        $this->app->instance(DatabaseDumpRunner::class, new RecordingDumpRunner("-- MariaDB dump\nCREATE TABLE example (id INT);"));

        $this->artisan('backup:database-v2', ['--mode' => 'pre-migration', '--release-sha' => 'short'])->assertFailed();
    }

    public function test_existing_lock_blocks_concurrent_backup(): void
    {
        $key = 'backup:v2:'.sha1('mariadb|127.0.0.1|3306|kairus_test');
        $lock = Cache::lock($key, 900);
        $this->assertTrue($lock->get());

        try {
            $this->app->instance(DatabaseDumpRunner::class, new RecordingDumpRunner("-- MariaDB dump\nCREATE TABLE example (id INT);"));
            $this->artisan('backup:database-v2')->assertFailed();
        } finally {
            $lock->release();
        }
    }
}

class RecordingDumpRunner implements DatabaseDumpRunner
{
    public int $calls = 0;
    public string $optionContents = '';
    public string $optionPath = '';

    public function __construct(private readonly string $dump = "-- MariaDB dump\nCREATE TABLE example (id INT);") {}

    public function dump(string $binary, string $optionFile, string $database, string $outputPath): void
    {
        $this->calls++;
        $this->optionPath = $optionFile;
        $this->optionContents = (string) file_get_contents($optionFile);
        file_put_contents($outputPath, $this->dump);
    }
}

class ThrowingDumpRunner implements DatabaseDumpRunner
{
    public function dump(string $binary, string $optionFile, string $database, string $outputPath): void
    {
        throw new RuntimeException('Database dump process failed.');
    }
}
