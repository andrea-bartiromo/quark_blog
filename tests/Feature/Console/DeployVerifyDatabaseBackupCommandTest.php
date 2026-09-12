<?php

namespace Tests\Feature\Console;

use Tests\TestCase;

/**
 * Cantiere 19 (programma 100-cantieri Kairus): deploy:verify-database-backup
 * è solo informativo (deploy.sh lo invoca senza `|| fail`), ma il comando
 * stesso deve comunque distinguere stato ok/stantio/assente/configurazione
 * non valida tramite l'exit code, per chi volesse usarlo come gate in un
 * contesto diverso.
 */
class DeployVerifyDatabaseBackupCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/backup-health-cmd-'.bin2hex(random_bytes(4)));
        config(['backup.v2.directory' => $this->directory]);
        config(['backup.v2.max_age_hours' => null]);
        config(['database.default' => 'mariadb']);
        config(['database.connections.mariadb.host' => '127.0.0.1']);
        config(['database.connections.mariadb.port' => '3306']);
        config(['database.connections.mariadb.database' => 'kairus_test']);
        config(['database.connections.mariadb.username' => 'kairus']);
        config(['database.connections.mariadb.unix_socket' => '']);
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

    public function test_is_not_applicable_for_a_non_mariadb_connection(): void
    {
        config(['database.default' => 'sqlite']);

        $this->artisan('deploy:verify-database-backup')
            ->assertExitCode(0)
            ->expectsOutputToContain('non applicabile');
    }

    public function test_fails_when_no_valid_backup_exists(): void
    {
        $this->artisan('deploy:verify-database-backup')
            ->assertExitCode(1)
            ->expectsOutputToContain('Nessun backup MariaDB valido trovato');
    }

    public function test_passes_when_a_valid_recent_backup_exists(): void
    {
        $this->writeValidPair(now('UTC')->toIso8601String());

        $this->artisan('deploy:verify-database-backup')
            ->assertExitCode(0)
            ->expectsOutputToContain('Backup MariaDB valido trovato');
    }

    public function test_fails_when_the_latest_backup_exceeds_the_configured_max_age(): void
    {
        config(['backup.v2.max_age_hours' => 24]);
        $this->writeValidPair(now('UTC')->subHours(48)->toIso8601String());

        $this->artisan('deploy:verify-database-backup')
            ->assertExitCode(1)
            ->expectsOutputToContain('DB_BACKUP_MAX_AGE_HOURS');
    }

    public function test_fails_when_max_age_configuration_is_invalid(): void
    {
        config(['backup.v2.max_age_hours' => 'not-a-number']);
        $this->writeValidPair(now('UTC')->toIso8601String());

        $this->artisan('deploy:verify-database-backup')
            ->assertExitCode(1)
            ->expectsOutputToContain('non è un intero positivo valido');
    }

    private function identityHash(): string
    {
        return substr(hash('sha256', 'mariadb|127.0.0.1|3306|kairus_test'), 0, 16);
    }

    private function writeValidPair(string $createdAtUtc): string
    {
        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0700, true);
        }
        $artifact = $this->directory.'/mariadb-'.$this->identityHash().'-20260101T000000Z-periodic-fixture.sql';
        file_put_contents($artifact, "-- MariaDB dump\nCREATE TABLE example (id INT);\n");
        file_put_contents($artifact.'.json', json_encode([
            'created_at_utc' => $createdAtUtc,
            'sha256' => hash_file('sha256', $artifact),
            'size_bytes' => filesize($artifact),
        ], JSON_THROW_ON_ERROR));

        return $artifact;
    }
}
