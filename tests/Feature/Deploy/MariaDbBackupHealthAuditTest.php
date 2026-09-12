<?php

namespace Tests\Feature\Deploy;

use App\Services\Deploy\MariaDbBackupHealthAudit;
use Tests\TestCase;

/**
 * Cantiere 19 (programma 100-cantieri Kairus).
 * App\Services\Deploy\MariaDbBackupHealthAudit segnala se esiste almeno un
 * backup MariaDB (Backup V2) valido e, quando configurata, se il più
 * recente supera la soglia di età — senza mai creare, pianificare o
 * eliminare alcun backup.
 */
class MariaDbBackupHealthAuditTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/backup-health-'.bin2hex(random_bytes(4)));
        config(['backup.v2.directory' => $this->directory]);
        config(['backup.v2.max_age_hours' => null]);
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

    public function test_reports_not_applicable_when_the_current_connection_is_not_mysql_or_mariadb(): void
    {
        config(['database.default' => 'sqlite']);

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertFalse($report['applicable']);
        $this->assertTrue($report['ok']);
        $this->assertNull($report['latest']);
    }

    public function test_reports_not_ok_when_no_backup_directory_exists_at_all(): void
    {
        config(['database.default' => 'mariadb']);

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertTrue($report['applicable']);
        $this->assertFalse($report['ok']);
        $this->assertNull($report['latest']);
    }

    public function test_reports_not_ok_when_the_directory_has_no_valid_backup_pair(): void
    {
        config(['database.default' => 'mariadb']);
        mkdir($this->directory, 0700, true);
        // Artefatto senza il .json di metadata associato: non è una coppia valida.
        file_put_contents($this->directory.'/mariadb-deadbeefdeadbeef-20260101T000000Z-periodic-orphan.sql', 'dump');

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertFalse($report['ok']);
        $this->assertNull($report['latest']);
    }

    public function test_reports_ok_when_a_valid_recent_backup_pair_exists(): void
    {
        config(['database.default' => 'mariadb']);
        $this->writeValidPair('fresh', now('UTC')->toIso8601String());

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertTrue($report['ok']);
        $this->assertNotNull($report['latest']);
        $this->assertLessThan(1, $report['latest']['age_hours']);
    }

    public function test_ignores_a_pair_whose_hash_no_longer_matches_the_recorded_metadata(): void
    {
        config(['database.default' => 'mariadb']);
        $artifact = $this->writeValidPair('tampered', now('UTC')->toIso8601String());
        // Corrompe l'artefatto dopo la pubblicazione della metadata: la coppia
        // non è più "conosciuta buona" (stesso principio di
        // MariaDbBackupService::isKnownGoodPair()).
        file_put_contents($artifact, 'tampered contents');

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertFalse($report['ok']);
        $this->assertNull($report['latest']);
    }

    public function test_selects_the_most_recent_of_several_valid_pairs(): void
    {
        config(['database.default' => 'mariadb']);
        $this->writeValidPair('older', '2020-01-01T00:00:00+00:00');
        $newest = $this->writeValidPair('newer', now('UTC')->toIso8601String());

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertSame($newest, $report['latest']['path']);
    }

    public function test_does_not_flag_staleness_when_no_max_age_is_configured(): void
    {
        config(['database.default' => 'mariadb']);
        config(['backup.v2.max_age_hours' => null]);
        $this->writeValidPair('ancient', '2020-01-01T00:00:00+00:00');

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertTrue($report['ok']);
        $this->assertFalse($report['stale']);
    }

    public function test_flags_staleness_when_the_latest_backup_exceeds_the_configured_max_age(): void
    {
        config(['database.default' => 'mariadb']);
        config(['backup.v2.max_age_hours' => 24]);
        $this->writeValidPair('ancient', now('UTC')->subHours(48)->toIso8601String());

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertFalse($report['ok']);
        $this->assertTrue($report['stale']);
    }

    public function test_does_not_flag_staleness_when_the_latest_backup_is_within_the_configured_max_age(): void
    {
        config(['database.default' => 'mariadb']);
        config(['backup.v2.max_age_hours' => 24]);
        $this->writeValidPair('recent', now('UTC')->subHours(2)->toIso8601String());

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertTrue($report['ok']);
        $this->assertFalse($report['stale']);
    }

    private function writeValidPair(string $suffix, string $createdAtUtc): string
    {
        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0700, true);
        }
        $artifact = $this->directory."/mariadb-deadbeefdeadbeef-20260101T000000Z-periodic-{$suffix}.sql";
        file_put_contents($artifact, "-- MariaDB dump\nCREATE TABLE example (id INT);\n");
        file_put_contents($artifact.'.json', json_encode([
            'created_at_utc' => $createdAtUtc,
            'sha256' => hash_file('sha256', $artifact),
            'size_bytes' => filesize($artifact),
        ], JSON_THROW_ON_ERROR));

        return $artifact;
    }
}
