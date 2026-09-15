<?php

namespace Tests\Feature\Deploy;

use App\Services\Deploy\MariaDbBackupHealthAudit;
use Illuminate\Support\Facades\Storage;
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
        config(['backup.v2.offhost.disk' => null]);
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

    // ── Cantiere 72: retention verificabile ─────────────────────

    public function test_does_not_flag_retention_when_no_retention_is_configured(): void
    {
        config(['backup.v2.retention' => null]);
        $this->writeValidPair('a', now('UTC')->toIso8601String());
        $this->writeValidPair('b', now('UTC')->subHour()->toIso8601String());
        $this->writeValidPair('c', now('UTC')->subHours(2)->toIso8601String());

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertTrue($report['ok']);
        $this->assertSame([], $report['retention_exceeded_modes']);
        $this->assertNull($report['retention_configured']);
    }

    public function test_does_not_flag_retention_when_valid_pairs_are_within_the_configured_limit(): void
    {
        config(['backup.v2.retention' => 2]);
        $this->writeValidPair('a', now('UTC')->toIso8601String());
        $this->writeValidPair('b', now('UTC')->subHour()->toIso8601String());

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertTrue($report['ok']);
        $this->assertSame([], $report['retention_exceeded_modes']);
        $this->assertSame(2, $report['pair_counts_by_mode']['periodic']);
    }

    /**
     * Cantiere 72: MariaDbBackupService::applyRetention() tratta un
     * fallimento di pulizia come un warning "mai bloccante" — senza
     * questo segnale, backup accumulati oltre il limite configurato
     * (es. per un fallimento di permessi ripetuto) resterebbero
     * invisibili a chi non legge i log di ogni esecuzione.
     */
    public function test_flags_retention_exceeded_when_more_valid_pairs_exist_than_the_configured_limit(): void
    {
        config(['backup.v2.retention' => 2]);
        $this->writeValidPair('a', now('UTC')->toIso8601String());
        $this->writeValidPair('b', now('UTC')->subHour()->toIso8601String());
        $this->writeValidPair('c', now('UTC')->subHours(2)->toIso8601String());

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertFalse($report['ok']);
        $this->assertSame(['periodic'], $report['retention_exceeded_modes']);
        $this->assertSame(3, $report['pair_counts_by_mode']['periodic']);
    }

    /**
     * MariaDbBackupService::applyRetention() applica il limite PER MODE
     * (glob scoped su "-{mode}-"): un 'pre-migration' oltre il limite non
     * deve mai essere nascosto da un 'periodic' sotto il limite, né
     * viceversa — sommarli farebbe perdere esattamente il segnale che
     * questa verifica esiste per dare.
     */
    public function test_tracks_retention_per_mode_independently(): void
    {
        config(['backup.v2.retention' => 1]);
        $this->writeValidPair('a', now('UTC')->toIso8601String(), 'periodic');
        $this->writeValidPair('b', now('UTC')->toIso8601String(), 'pre-migration');
        $this->writeValidPair('c', now('UTC')->subHour()->toIso8601String(), 'pre-migration');

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertFalse($report['ok']);
        $this->assertSame(['pre-migration'], $report['retention_exceeded_modes']);
        $this->assertSame(1, $report['pair_counts_by_mode']['periodic']);
        $this->assertSame(2, $report['pair_counts_by_mode']['pre-migration']);
    }

    /**
     * Stesso principio già verificato per max_age_hours (Codex, PR #567):
     * un valore malformato deve restare un segnale osservabile, mai
     * essere silenziosamente trattato come "non configurato".
     */
    public function test_flags_an_invalid_retention_configuration_instead_of_silently_ignoring_it(): void
    {
        config(['backup.v2.retention' => '-3']);
        $this->writeValidPair('a', now('UTC')->toIso8601String());

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertFalse($report['ok']);
        $this->assertTrue($report['retention_invalid']);
        $this->assertNull($report['retention_configured']);
    }

    /**
     * Un metadata scritto prima che il campo 'mode' esistesse (o
     * corrotto solo su quel campo) non deve sparire dal conteggio:
     * finisce nel bucket 'unknown', ancora contato verso il rischio di
     * accumulo, mai scartato in silenzio.
     */
    /**
     * Il mode è derivato dal FILENAME (stesso glob di
     * MariaDbBackupService::applyRetention()), mai dal campo 'mode' dei
     * metadata — un metadata privo del campo, o divergente, non deve mai
     * far scomparire una coppia il cui filename corrisponde comunque a un
     * mode noto (Codex, PR #613, P2): qui il filename dice 'periodic', il
     * metadata non ha affatto il campo, e deve comunque contare come
     * 'periodic', non 'unknown'.
     */
    public function test_derives_the_mode_from_the_filename_even_when_metadata_omits_it(): void
    {
        config(['backup.v2.retention' => 5]);
        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0700, true);
        }
        $artifact = $this->directory.'/mariadb-'.$this->identityHash().'-20260101T000000Z-periodic-nomode.sql';
        file_put_contents($artifact, "-- MariaDB dump\nCREATE TABLE example (id INT);\n");
        file_put_contents($artifact.'.json', json_encode([
            'created_at_utc' => now('UTC')->toIso8601String(),
            'sha256' => hash_file('sha256', $artifact),
            'size_bytes' => filesize($artifact),
        ], JSON_THROW_ON_ERROR));

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertSame(1, $report['pair_counts_by_mode']['periodic']);
        $this->assertArrayNotHasKey('unknown', $report['pair_counts_by_mode']);
    }

    /**
     * Una coppia valida il cui filename non corrisponde a NESSUNO dei
     * mode noti non verrebbe mai selezionata da alcuna chiamata di
     * applyRetention() (sempre scoped su un mode specifico): si
     * accumulerebbe senza limite. Deve restare un segnale osservabile nel
     * bucket 'unknown', non sparire dal conteggio.
     */
    public function test_counts_a_pair_whose_filename_mode_is_unknown_into_the_unknown_bucket(): void
    {
        config(['backup.v2.retention' => 5]);
        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0700, true);
        }
        $artifact = $this->directory.'/mariadb-'.$this->identityHash().'-20260101T000000Z-legacy-orphan.sql';
        file_put_contents($artifact, "-- MariaDB dump\nCREATE TABLE example (id INT);\n");
        file_put_contents($artifact.'.json', json_encode([
            'created_at_utc' => now('UTC')->toIso8601String(),
            'sha256' => hash_file('sha256', $artifact),
            'size_bytes' => filesize($artifact),
        ], JSON_THROW_ON_ERROR));

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertSame(1, $report['pair_counts_by_mode']['unknown']);
    }

    /**
     * Codex (PR #613, P1): senza retention configurata (il default) la
     * scansione completa della cronologia non deve mai avvenire — stesso
     * costo che l'ottimizzazione one-candidate-alla-volta di
     * latestValidBackup() evita già per lo staleness check.
     */
    public function test_does_not_scan_pair_counts_when_no_retention_is_configured(): void
    {
        config(['backup.v2.retention' => null]);
        $this->writeValidPair('a', now('UTC')->toIso8601String());

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertSame([], $report['pair_counts_by_mode']);
    }

    // ── Cantiere 74: verifica off-host ────────────────────────────

    public function test_does_not_check_offhost_when_no_disk_is_configured(): void
    {
        config(['backup.v2.offhost.disk' => null]);
        $this->writeValidPair('a', now('UTC')->toIso8601String());

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertTrue($report['ok']);
        $this->assertFalse($report['offhost_checked']);
        $this->assertFalse($report['offhost_missing']);
        $this->assertFalse($report['offhost_error']);
        $this->assertNull($report['offhost_disk']);
    }

    public function test_reports_ok_when_the_latest_backup_is_mirrored_offhost(): void
    {
        Storage::fake('offhost');
        config(['backup.v2.offhost.disk' => 'offhost']);
        $artifact = $this->writeValidPair('a', now('UTC')->toIso8601String());
        Storage::disk('offhost')->put('mariadb/'.basename($artifact), file_get_contents($artifact));
        Storage::disk('offhost')->put('mariadb/'.basename($artifact).'.json', file_get_contents($artifact.'.json'));

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertTrue($report['ok']);
        $this->assertTrue($report['offhost_checked']);
        $this->assertFalse($report['offhost_missing']);
        $this->assertFalse($report['offhost_error']);
        $this->assertSame('offhost', $report['offhost_disk']);
    }

    /**
     * Cantiere 74: MariaDbBackupService::copyToOffHostDiskIfConfigured()
     * tratta un fallimento di caricamento come un warning "mai
     * bloccante" per il backup locale — senza questa verifica, un
     * fallimento ripetuto (credenziali scadute, quota esaurita)
     * lascerebbe l'unica copia off-host assente in silenzio, proprio
     * nello scenario in cui servirebbe di più.
     */
    public function test_flags_offhost_missing_when_the_artifact_was_never_uploaded(): void
    {
        Storage::fake('offhost');
        config(['backup.v2.offhost.disk' => 'offhost']);
        $this->writeValidPair('a', now('UTC')->toIso8601String());

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertFalse($report['ok']);
        $this->assertTrue($report['offhost_checked']);
        $this->assertTrue($report['offhost_missing']);
        $this->assertFalse($report['offhost_error']);
    }

    public function test_flags_offhost_missing_when_only_the_metadata_is_absent(): void
    {
        Storage::fake('offhost');
        config(['backup.v2.offhost.disk' => 'offhost']);
        $artifact = $this->writeValidPair('a', now('UTC')->toIso8601String());
        // Solo l'artefatto è presente off-host: la metadata manca —
        // una coppia parziale conta comunque come mancante, mai come ok.
        Storage::disk('offhost')->put('mariadb/'.basename($artifact), file_get_contents($artifact));

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertFalse($report['ok']);
        $this->assertTrue($report['offhost_missing']);
    }

    public function test_a_custom_offhost_prefix_is_honored(): void
    {
        Storage::fake('offhost');
        config(['backup.v2.offhost.disk' => 'offhost']);
        config(['backup.v2.offhost.prefix' => 'kairus-db-copies']);
        $artifact = $this->writeValidPair('a', now('UTC')->toIso8601String());
        Storage::disk('offhost')->put('kairus-db-copies/'.basename($artifact), file_get_contents($artifact));
        Storage::disk('offhost')->put('kairus-db-copies/'.basename($artifact).'.json', file_get_contents($artifact.'.json'));

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertTrue($report['ok']);
        $this->assertFalse($report['offhost_missing']);
    }

    /**
     * Un disco configurato ma non registrato in config/filesystems.php
     * riproduce realisticamente un errore di configurazione senza
     * credenziali reali: la verifica deve restare di sola lettura e mai
     * lanciare, riportando 'offhost_error' invece di far fallire l'intero
     * comando con un'eccezione non gestita.
     */
    public function test_flags_offhost_error_when_the_configured_disk_does_not_exist(): void
    {
        config(['backup.v2.offhost.disk' => 'not-a-configured-disk']);
        $this->writeValidPair('a', now('UTC')->toIso8601String());

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertFalse($report['ok']);
        $this->assertTrue($report['offhost_checked']);
        $this->assertTrue($report['offhost_error']);
        $this->assertFalse($report['offhost_missing']);
    }

    public function test_does_not_check_offhost_when_no_local_backup_exists(): void
    {
        Storage::fake('offhost');
        config(['backup.v2.offhost.disk' => 'offhost']);

        $report = app(MariaDbBackupHealthAudit::class)->report();

        $this->assertFalse($report['offhost_checked']);
    }

    private function identityHash(): string
    {
        return substr(hash('sha256', 'mariadb|127.0.0.1|3306|kairus_test'), 0, 16);
    }

    private function writeValidPair(string $suffix, string $createdAtUtc, string $mode = 'periodic'): string
    {
        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0700, true);
        }
        $artifact = $this->directory.'/mariadb-'.$this->identityHash()."-20260101T000000Z-{$mode}-{$suffix}.sql";
        file_put_contents($artifact, "-- MariaDB dump\nCREATE TABLE example (id INT);\n");
        file_put_contents($artifact.'.json', json_encode([
            'created_at_utc' => $createdAtUtc,
            'sha256' => hash_file('sha256', $artifact),
            'size_bytes' => filesize($artifact),
            'mode' => $mode,
        ], JSON_THROW_ON_ERROR));

        return $artifact;
    }
}
