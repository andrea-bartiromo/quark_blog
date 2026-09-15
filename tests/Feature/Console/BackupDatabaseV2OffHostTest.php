<?php

namespace Tests\Feature\Console;

use App\Contracts\DatabaseDumpRunner;
use App\Services\Backup\MariaDbBackupService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Cantiere 71 (programma "100 cantieri Kairus", indipendente): seconda
 * copia opzionale dell'artefatto+metadata di backup MariaDB già
 * validati e pubblicati in locale, su un secondo filesystem Laravel —
 * config-only, disabilitata per default (docs/BACKUP_V2_OPERATIONS.md
 * elencava OFF_HOST_STORAGE come "UNKNOWN / TO CONFIRM"). Un
 * fallimento della copia off-host non deve mai far apparire fallito un
 * backup locale in realtà riuscito — stesso principio "mai bloccante"
 * già applicato alla pulizia di retention.
 */
class BackupDatabaseV2OffHostTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/backup-v2-offhost-'.bin2hex(random_bytes(4)));
        config()->set('backup.v2.directory', $this->directory);
        config()->set('backup.v2.binary', PHP_BINARY);
        config()->set('backup.v2.retention', null);
        config()->set('backup.v2.lock_store', 'file');
        config()->set('backup.v2.lock_seconds', 900);
        config()->set('backup.v2.revision_file', $this->directory.'/REVISION');
        config()->set('backup.v2.offhost.disk', null);
        config()->set('backup.v2.offhost.prefix', 'mariadb');
        config()->set('database.default', 'mariadb');
        config()->set('database.connections.mariadb.host', '127.0.0.1');
        config()->set('database.connections.mariadb.port', '3306');
        config()->set('database.connections.mariadb.database', 'kairus_test');
        config()->set('database.connections.mariadb.username', 'kairus');
        config()->set('database.connections.mariadb.password', 'super-secret-test-password');
        config()->set('database.connections.mariadb.unix_socket', '');
        $this->app->instance(DatabaseDumpRunner::class, new OffHostRecordingDumpRunner);
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

    /**
     * Guardia non banale: Storage::disk('') (stringa vuota, non null)
     * in Laravel ricade silenziosamente sul disco di default
     * dell'applicazione ('local', operatore ?: interno al Filesystem
     * Manager) invece di sollevare un errore — senza il controllo
     * esplicito su stringa vuota, un backup ordinario scriverebbe una
     * copia indesiderata sul disco locale di default ad ogni
     * esecuzione, non solo quando l'off-host è stato attivato di
     * proposito. Verificato qui fakeando il disco di default stesso e
     * controllando che resti vuoto, non solo che manchi un warning.
     */
    public function test_no_offhost_copy_is_attempted_when_no_disk_is_configured(): void
    {
        Storage::fake('local');

        $this->artisan('backup:database-v2')
            ->doesntExpectOutputToContain('Off-host')
            ->assertSuccessful();

        Storage::disk('local')->assertDirectoryEmpty('mariadb');
    }

    public function test_the_published_artifact_and_metadata_are_copied_to_the_configured_offhost_disk(): void
    {
        Storage::fake('offhost');
        config()->set('backup.v2.offhost.disk', 'offhost');

        $this->artisan('backup:database-v2')->assertSuccessful();

        $artifact = (glob($this->directory.'/mariadb-*.sql') ?: [])[0];
        $basename = basename($artifact);

        Storage::disk('offhost')->assertExists('mariadb/'.$basename);
        Storage::disk('offhost')->assertExists('mariadb/'.$basename.'.json');
        $this->assertSame(
            file_get_contents($artifact),
            Storage::disk('offhost')->get('mariadb/'.$basename)
        );
        $this->assertSame(
            file_get_contents($artifact.'.json'),
            Storage::disk('offhost')->get('mariadb/'.$basename.'.json')
        );
    }

    public function test_a_custom_prefix_is_honored(): void
    {
        Storage::fake('offhost');
        config()->set('backup.v2.offhost.disk', 'offhost');
        config()->set('backup.v2.offhost.prefix', 'kairus-db-copies');

        $this->artisan('backup:database-v2')->assertSuccessful();

        $artifact = (glob($this->directory.'/mariadb-*.sql') ?: [])[0];
        Storage::disk('offhost')->assertExists('kairus-db-copies/'.basename($artifact));
    }

    /**
     * Un disco configurato ma inesistente (nessun driver registrato)
     * riproduce realisticamente un errore di configurazione/rete di
     * produzione, senza credenziali reali: prova che il fallimento
     * della copia off-host non fa mai fallire l'intero backup, che
     * resta pubblicato e valido in locale.
     */
    public function test_a_misconfigured_offhost_disk_produces_a_warning_but_the_backup_still_succeeds(): void
    {
        config()->set('backup.v2.offhost.disk', 'not-a-configured-disk');

        $this->artisan('backup:database-v2')
            ->expectsOutputToContain('Off-host backup copy failed; the local backup remains the source of truth.')
            ->assertSuccessful();

        $artifacts = glob($this->directory.'/mariadb-*.sql') ?: [];
        $this->assertCount(1, $artifacts);
        $this->assertFileExists($artifacts[0].'.json');
    }

    /**
     * Codex (PR #610, P2): se il caricamento dell'artefatto .sql riesce
     * ma quello del metadata .json fallisce subito dopo, l'oggetto già
     * caricato in QUESTO tentativo deve essere ripulito — altrimenti,
     * dato che ogni esecuzione usa un basename nuovo e non esiste
     * alcuna retention off-host, un fallimento ripetuto lascerebbe
     * accumulare dump non verificati e spaiati sul disco remoto.
     */
    public function test_a_partial_offhost_upload_failure_cleans_up_the_already_uploaded_object(): void
    {
        Storage::fake('offhost');
        config()->set('backup.v2.offhost.disk', 'offhost');

        $service = new SecondOffHostUploadFailsBackupService(new OffHostRecordingDumpRunner);
        $this->app->instance(MariaDbBackupService::class, $service);

        $this->artisan('backup:database-v2')
            ->expectsOutputToContain('Off-host backup copy failed; the local backup remains the source of truth.')
            ->assertSuccessful();

        $artifacts = glob($this->directory.'/mariadb-*.sql') ?: [];
        $this->assertCount(1, $artifacts, 'Il backup locale deve comunque restare pubblicato e valido.');

        // Nessun oggetto orfano: né l'artefatto (caricato con successo, poi
        // ripulito) né il metadata (mai caricato) devono restare sul disco
        // off-host dopo un fallimento parziale.
        $this->assertSame([], Storage::disk('offhost')->allFiles());
    }
}

class OffHostRecordingDumpRunner implements DatabaseDumpRunner
{
    public function dump(string $binary, string $optionFile, string $database, string $outputPath): void
    {
        file_put_contents($outputPath, "-- MariaDB dump 10.19 Distrib 10.11\nCREATE TABLE `example` (`id` bigint NOT NULL);\nINSERT INTO `example` VALUES (1);\n");
    }
}

class SecondOffHostUploadFailsBackupService extends MariaDbBackupService
{
    private int $calls = 0;

    protected function putToOffHostDisk(Filesystem $filesystem, string $remotePath, $stream): bool
    {
        $this->calls++;
        if ($this->calls === 2) {
            return false;
        }

        return parent::putToOffHostDisk($filesystem, $remotePath, $stream);
    }
}
