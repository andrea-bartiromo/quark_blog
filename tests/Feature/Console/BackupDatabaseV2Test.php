<?php

namespace Tests\Feature\Console;

use App\Contracts\DatabaseDumpRunner;
use App\Services\Backup\MariaDbBackupService;
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
        config()->set('backup.v2.lock_store', 'file');
        config()->set('backup.v2.lock_seconds', 900);
        config()->set('backup.v2.revision_file', $this->directory.'/REVISION');
        config()->set('database.default', 'mariadb');
        config()->set('database.connections.mariadb.host', '127.0.0.1');
        config()->set('database.connections.mariadb.port', '3306');
        config()->set('database.connections.mariadb.database', 'kairus_test');
        config()->set('database.connections.mariadb.username', 'kairus');
        config()->set('database.connections.mariadb.password', 'super-secret-test-password');
        config()->set('database.connections.mariadb.unix_socket', '');
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

    public function test_rejects_invalid_retention_before_dump(): void
    {
        config()->set('backup.v2.retention', '-1');
        $runner = new RecordingDumpRunner;
        $this->app->instance(DatabaseDumpRunner::class, $runner);
        $this->artisan('backup:database-v2')->assertFailed();
        $this->assertSame(0, $runner->calls);
    }

    public function test_rejects_process_local_lock_store_before_dump(): void
    {
        config()->set('backup.v2.lock_store', 'array');
        $runner = new RecordingDumpRunner;
        $this->app->instance(DatabaseDumpRunner::class, $runner);
        $this->artisan('backup:database-v2')->assertFailed();
        $this->assertSame(0, $runner->calls);
    }

    public function test_rejects_non_positive_lock_duration_before_dump(): void
    {
        config()->set('backup.v2.lock_seconds', 0);
        $runner = new RecordingDumpRunner;
        $this->app->instance(DatabaseDumpRunner::class, $runner);
        $this->artisan('backup:database-v2')->assertFailed();
        $this->assertSame(0, $runner->calls);
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
        $this->assertSame([], glob($this->directory.'/mariadb-*.sql') ?: []);
    }

    public function test_dump_failure_preserves_previous_valid_backup_and_redacts_secret_even_from_exception(): void
    {
        $this->app->instance(DatabaseDumpRunner::class, new RecordingDumpRunner($this->validDump()));
        $this->artisan('backup:database-v2')->assertSuccessful();
        $previous = (glob($this->directory.'/mariadb-*.sql') ?: [])[0];
        $this->assertFileExists($previous.'.json');

        $this->app->instance(DatabaseDumpRunner::class, new ThrowingDumpRunner('dump failed with super-secret-test-password'));
        $this->artisan('backup:database-v2')
            ->expectsOutputToContain('Database dump process failed.')
            ->doesntExpectOutputToContain('super-secret-test-password')
            ->assertFailed();

        $this->assertFileExists($previous);
        $this->assertFileExists($previous.'.json');
    }

    public function test_success_atomically_publishes_dump_and_metadata_without_secret(): void
    {
        $runner = new RecordingDumpRunner($this->validDump());
        $this->app->instance(DatabaseDumpRunner::class, $runner);
        $this->artisan('backup:database-v2')->assertSuccessful();

        $artifacts = glob($this->directory.'/mariadb-*.sql') ?: [];
        $this->assertCount(1, $artifacts);
        $this->assertFileExists($artifacts[0].'.json');
        $metadata = json_decode((string) file_get_contents($artifacts[0].'.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(hash_file('sha256', $artifacts[0]), $metadata['sha256']);
        $this->assertSame(filesize($artifacts[0]), $metadata['size_bytes']);
        $this->assertSame('mariadb', $metadata['engine']);
        $this->assertSame('periodic', $metadata['mode']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $metadata['database_identity']);
        $this->assertStringNotContainsString('super-secret-test-password', file_get_contents($artifacts[0].'.json'));
        $this->assertSame(0600, $runner->optionMode);
        $this->assertFalse(file_exists($runner->optionPath));

        if (PHP_OS_FAMILY !== 'Windows') {
            $this->assertSame(0600, fileperms($artifacts[0]) & 0777);
            $this->assertSame(0600, fileperms($artifacts[0].'.json') & 0777);
        }
    }

    public function test_unix_socket_configuration_omits_host_and_port_from_option_file(): void
    {
        config()->set('database.connections.mariadb.unix_socket', '/tmp/mariadb.sock');
        config()->set('database.connections.mariadb.host', null);
        config()->set('database.connections.mariadb.port', null);
        $runner = new RecordingDumpRunner($this->validDump());
        $this->app->instance(DatabaseDumpRunner::class, $runner);
        $this->artisan('backup:database-v2')->assertSuccessful();
        $this->assertStringContainsString('socket="/tmp/mariadb.sock"', $runner->optionContents);
        $this->assertStringNotContainsString('host=', $runner->optionContents);
        $this->assertStringNotContainsString('port=', $runner->optionContents);
    }

    public function test_pre_migration_mode_requires_exact_release_sha(): void
    {
        $this->app->instance(DatabaseDumpRunner::class, new RecordingDumpRunner($this->validDump()));
        $this->artisan('backup:database-v2', ['--mode' => 'pre-migration', '--release-sha' => 'short'])->assertFailed();
    }

    public function test_pre_migration_metadata_records_exact_release_sha(): void
    {
        $sha = str_repeat('a', 40);
        $this->app->instance(DatabaseDumpRunner::class, new RecordingDumpRunner($this->validDump()));
        $this->artisan('backup:database-v2', ['--mode' => 'pre-migration', '--release-sha' => $sha])->assertSuccessful();
        $artifact = (glob($this->directory.'/mariadb-*.sql') ?: [])[0];
        $metadata = json_decode((string) file_get_contents($artifact.'.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($sha, $metadata['application_revision']);
    }

    public function test_existing_shared_lock_blocks_backup(): void
    {
        $identity = 'mariadb|127.0.0.1|3306|kairus_test';
        $key = 'backup:v2:'.substr(hash('sha256', $identity), 0, 16);
        $lock = Cache::store('file')->lock($key, 900);
        $this->assertTrue($lock->get());
        try {
            $this->app->instance(DatabaseDumpRunner::class, new RecordingDumpRunner($this->validDump()));
            $this->artisan('backup:database-v2')->assertFailed();
        } finally {
            $lock->release();
        }
    }

    public function test_destination_failure_happens_before_dump(): void
    {
        $runner = new RecordingDumpRunner($this->validDump());
        $service = new DestinationFailureBackupService($runner);
        try {
            $service->create();
            $this->fail('Expected destination failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('Backup destination is not writable.', $e->getMessage());
        }
        $this->assertSame(0, $runner->calls);
    }

    public function test_forced_promotion_failure_cleans_temp_and_skips_retention(): void
    {
        $runner = new RecordingDumpRunner($this->validDump());
        $service = new PromotionFailureBackupService($runner);
        try {
            $service->create();
            $this->fail('Expected promotion failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('Forced atomic promotion failure.', $e->getMessage());
        }
        $this->assertFalse($service->retentionCalled);
        $this->assertSame([], glob($this->directory.'/.mariadb-*.tmp') ?: []);
        $this->assertSame([], glob($this->directory.'/mariadb-*.sql') ?: []);
        $this->assertFalse(file_exists($runner->optionPath));
    }

    public function test_retention_failure_keeps_valid_new_backup_and_returns_success_with_warning(): void
    {
        config()->set('backup.v2.retention', '1');
        $identity = $this->identityHash();
        $old = $this->writeKnownGoodPair("mariadb-{$identity}-20260101T000000Z-periodic-old.sql");
        touch($old, time() - 3600);
        touch($old.'.json', time() - 3600);

        $service = new RetentionFailureBackupService(new RecordingDumpRunner($this->validDump()));
        $this->app->instance(MariaDbBackupService::class, $service);
        $this->artisan('backup:database-v2')
            ->expectsOutputToContain('Backup retention cleanup could not remove one older validated backup pair.')
            ->assertSuccessful();

        $this->assertFileExists($old);
        $this->assertGreaterThanOrEqual(2, count(glob($this->directory.'/mariadb-*.sql') ?: []));
    }

    public function test_retention_preserves_other_mode_and_database_identity(): void
    {
        config()->set('backup.v2.retention', '1');
        $identity = $this->identityHash();
        $preMigration = $this->writeKnownGoodPair("mariadb-{$identity}-20260101T000000Z-pre-migration-aaaaaaaaaaaa-old.sql");
        $otherDatabase = $this->writeKnownGoodPair('mariadb-deadbeefdeadbeef-20260101T000000Z-periodic-old.sql');
        $this->app->instance(DatabaseDumpRunner::class, new RecordingDumpRunner($this->validDump()));

        $this->artisan('backup:database-v2')->assertSuccessful();

        $this->assertFileExists($preMigration);
        $this->assertFileExists($otherDatabase);
    }

    public function test_retention_ignores_unpaired_or_unrelated_files(): void
    {
        config()->set('backup.v2.retention', '1');
        mkdir($this->directory, 0700, true);
        $legacy = $this->directory.'/database-legacy.sqlite';
        $unpaired = $this->directory.'/mariadb-'.$this->identityHash().'-20260101T000000Z-periodic-unpaired.sql';
        file_put_contents($legacy, 'legacy');
        file_put_contents($unpaired, $this->validDump());
        $this->app->instance(DatabaseDumpRunner::class, new RecordingDumpRunner($this->validDump()));
        $this->artisan('backup:database-v2')->assertSuccessful();
        $this->assertFileExists($legacy);
        $this->assertFileExists($unpaired);
    }

    private function identityHash(): string
    {
        return substr(hash('sha256', 'mariadb|127.0.0.1|3306|kairus_test'), 0, 16);
    }

    private function writeKnownGoodPair(string $name): string
    {
        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0700, true);
        }
        $artifact = $this->directory.'/'.$name;
        file_put_contents($artifact, $this->validDump());
        file_put_contents($artifact.'.json', json_encode([
            'sha256' => hash_file('sha256', $artifact),
            'size_bytes' => filesize($artifact),
        ], JSON_THROW_ON_ERROR));
        return $artifact;
    }

    private function validDump(): string
    {
        return "-- MariaDB dump 10.19 Distrib 10.11\nCREATE TABLE `example` (`id` bigint NOT NULL);\nINSERT INTO `example` VALUES (1);\n";
    }
}

class RecordingDumpRunner implements DatabaseDumpRunner
{
    public int $calls = 0;
    public string $optionContents = '';
    public string $optionPath = '';
    public int $optionMode = 0;

    public function __construct(private readonly string $dump = "-- MariaDB dump\nCREATE TABLE example (id INT);") {}

    public function dump(string $binary, string $optionFile, string $database, string $outputPath): void
    {
        $this->calls++;
        $this->optionPath = $optionFile;
        $this->optionContents = (string) file_get_contents($optionFile);
        $this->optionMode = fileperms($optionFile) & 0777;
        file_put_contents($outputPath, $this->dump);
    }
}

class ThrowingDumpRunner implements DatabaseDumpRunner
{
    public function __construct(private readonly string $message = 'Database dump process failed.') {}
    public function dump(string $binary, string $optionFile, string $database, string $outputPath): void
    {
        throw new RuntimeException($this->message);
    }
}

class DestinationFailureBackupService extends MariaDbBackupService
{
    protected function prepareDirectory(string $directory): void
    {
        throw new RuntimeException('Backup destination is not writable.');
    }
}

class PromotionFailureBackupService extends MariaDbBackupService
{
    public bool $retentionCalled = false;
    protected function promoteValidatedBackup(string $temporary, string $final): void
    {
        throw new RuntimeException('Forced atomic promotion failure.');
    }
    protected function applyRetention(string $directory, string $current, string $identityHash, string $mode, ?int $retention): array
    {
        $this->retentionCalled = true;
        return [];
    }
}

class RetentionFailureBackupService extends MariaDbBackupService
{
    protected function deleteRetentionPair(string $artifact): bool
    {
        return false;
    }
}
