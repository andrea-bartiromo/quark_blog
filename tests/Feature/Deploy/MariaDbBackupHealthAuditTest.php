<?php

namespace Tests\Feature\Deploy;

use App\Services\Deploy\MariaDbBackupHealthAudit;
use Tests\TestCase;

/**
 * Cantiere 19 (programma 100-cantieri Kairus).
 * App\Services\Deploy\MariaDbBackupHealthAudit segnala se esiste almeno un
 * backup MariaDB (Backup V2) valido PER L'IDENTITÀ DATABASE CORRENTE e,
 * quando configurata, se il più recente supera la soglia di età — senza
 * mai creare, pianificare o eliminare alcun backup.
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
        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertTrue($report['applicable']);
        $this->assertFalse($report['ok']);
        $this->assertNull($report['latest']);
    }

    public function test_reports_not_ok_when_the_directory_has_no_valid_backup_pair(): void
    {
        mkdir($this->directory, 0700, true);
        // Artefatto senza il .json di metadata associato: non è una coppia valida.
        file_put_contents($this->directory.'/mariadb-'.$this->identityHash().'-20260101T000000Z-periodic-orphan.sql', 'dump');

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertFalse($report['ok']);
        $this->assertNull($report['latest']);
    }

    public function test_reports_ok_when_a_valid_recent_backup_pair_exists(): void
    {
        $this->writeValidPair('fresh', now('UTC')->toIso8601String());

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertTrue($report['ok']);
        $this->assertNotNull($report['latest']);
        $this->assertLessThan(1, $report['latest']['age_hours']);
    }

    public function test_ignores_a_pair_whose_hash_no_longer_matches_the_recorded_metadata(): void
    {
        $artifact = $this->writeValidPair('tampered', now('UTC')->toIso8601String());
        // Corrompe l'artefatto dopo la pubblicazione della metadata: la coppia
        // non è più "conosciuta buona" (stesso principio di
        // MariaDbBackupService::isKnownGoodPair()).
        file_put_contents($artifact, 'tampered contents');

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertFalse($report['ok']);
        $this->assertNull($report['latest']);
    }

    public function test_falls_back_to_an_older_valid_pair_when_the_newest_is_tampered(): void
    {
        $older = $this->writeValidPair('older', now('UTC')->subHours(2)->toIso8601String());
        $newest = $this->writeValidPair('newest', now('UTC')->toIso8601String());
        file_put_contents($newest, 'tampered contents');

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertTrue($report['ok']);
        $this->assertSame($older, $report['latest']['path']);
    }

    public function test_selects_the_most_recent_of_several_valid_pairs(): void
    {
        $this->writeValidPair('older', '2020-01-01T00:00:00+00:00');
        $newest = $this->writeValidPair('newer', now('UTC')->toIso8601String());

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertSame($newest, $report['latest']['path']);
    }

    /**
     * Finding Codex (P1, PR #567): `MariaDbBackupService` incorpora un hash
     * dell'identità database corrente (connessione, host/socket, porta,
     * database) nel nome file e nei metadata di ogni backup. Un vecchio
     * backup per un'identità DIVERSA (es. produzione che cambia nome
     * database mantenendo la stessa directory persistente) non deve mai
     * essere considerato un backup valido per il database ATTUALE.
     */
    public function test_ignores_a_valid_backup_belonging_to_a_different_database_identity(): void
    {
        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0700, true);
        }
        $otherIdentity = 'deadbeefdeadbeef';
        $artifact = $this->directory."/mariadb-{$otherIdentity}-20260101T000000Z-periodic-other.sql";
        file_put_contents($artifact, "-- MariaDB dump\nCREATE TABLE example (id INT);\n");
        file_put_contents($artifact.'.json', json_encode([
            'created_at_utc' => now('UTC')->toIso8601String(),
            'sha256' => hash_file('sha256', $artifact),
            'size_bytes' => filesize($artifact),
        ], JSON_THROW_ON_ERROR));

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertFalse($report['ok']);
        $this->assertNull($report['latest']);
    }

    public function test_does_not_flag_staleness_when_no_max_age_is_configured(): void
    {
        config(['backup.v2.max_age_hours' => null]);
        $this->writeValidPair('ancient', '2020-01-01T00:00:00+00:00');

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertTrue($report['ok']);
        $this->assertFalse($report['stale']);
        $this->assertFalse($report['max_age_invalid']);
    }

    public function test_flags_staleness_when_the_latest_backup_exceeds_the_configured_max_age(): void
    {
        config(['backup.v2.max_age_hours' => 24]);
        $this->writeValidPair('ancient', now('UTC')->subHours(48)->toIso8601String());

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertFalse($report['ok']);
        $this->assertTrue($report['stale']);
    }

    public function test_does_not_flag_staleness_when_the_latest_backup_is_within_the_configured_max_age(): void
    {
        config(['backup.v2.max_age_hours' => 24]);
        $this->writeValidPair('recent', now('UTC')->subHours(2)->toIso8601String());

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertTrue($report['ok']);
        $this->assertFalse($report['stale']);
    }

    /**
     * Finding Codex (P2, PR #567): un valore configurato ma malformato
     * (es. "-3") veniva prima silenziosamente trattato come "non
     * configurato", disabilitando il controllo di staleness invece di
     * segnalare l'errore — a differenza della retention analoga
     * (MariaDbBackupService::retentionLimit()), che rifiuta esplicitamente
     * un valore non valido invece di ignorarlo.
     */
    public function test_flags_an_invalid_max_age_configuration_instead_of_silently_ignoring_it(): void
    {
        config(['backup.v2.max_age_hours' => '-3']);
        $this->writeValidPair('recent', now('UTC')->toIso8601String());

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertFalse($report['ok']);
        $this->assertTrue($report['max_age_invalid']);
        $this->assertNull($report['max_age_hours']);
    }

    private function identityHash(): string
    {
        return substr(hash('sha256', 'mariadb|127.0.0.1|3306|kairus_test'), 0, 16);
    }

    private function writeValidPair(string $suffix, string $createdAtUtc): string
    {
        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0700, true);
        }
        $artifact = $this->directory.'/mariadb-'.$this->identityHash()."-20260101T000000Z-periodic-{$suffix}.sql";
        file_put_contents($artifact, "-- MariaDB dump\nCREATE TABLE example (id INT);\n");
        file_put_contents($artifact.'.json', json_encode([
            'created_at_utc' => $createdAtUtc,
            'sha256' => hash_file('sha256', $artifact),
            'size_bytes' => filesize($artifact),
        ], JSON_THROW_ON_ERROR));

        return $artifact;
    }
}
