<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Cantiere 73 (programma "100 cantieri Kairus", documentazione pura).
 * docs/BACKUP_V2_OPERATIONS.md afferma che "Restore isolato con
 * fixture/dump non produttivi" è già delivered dal job CI "MariaDB
 * 11.4 real dump restore" (.github/workflows/backup-restore.yml) — non
 * da codice scritto per questo cantiere. Questo test verifica che ogni
 * file/job/classe citati a supporto di quell'affermazione esistano
 * ancora davvero, e che nessun comando artisan `backup:restore*` sia
 * comparso nel frattempo senza che la documentazione venga aggiornata
 * di conseguenza — stesso principio già in uso in
 * TrustEditorialProtocolDriftTest e ReleaseChecklistDriftTest.
 */
class BackupRestoreCiEvidenceDriftTest extends TestCase
{
    public function test_the_ci_restore_workflow_file_and_job_name_still_exist(): void
    {
        $doc = $this->doc();
        $workflowPath = base_path('.github/workflows/backup-restore.yml');

        $this->assertStringContainsString(
            'MariaDB 11.4 real dump restore',
            $doc,
            'Il documento dovrebbe citare il job CI per nome.'
        );
        $this->assertFileExists($workflowPath, 'Il workflow CI citato dal documento non esiste più.');

        $workflow = file_get_contents($workflowPath);
        $this->assertIsString($workflow);
        $this->assertStringContainsString(
            'MariaDB 11.4 real dump restore',
            $workflow,
            "Il job 'MariaDB 11.4 real dump restore' citato dal documento non è più il nome reale del job CI."
        );
    }

    public function test_the_ci_workflow_still_performs_every_step_the_evidence_contract_lists(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/backup-restore.yml'));
        $this->assertIsString($workflow);

        foreach ([
            'BackupRestoreTestSeeder',
            'artisan backup:database-v2',
            'CREATE DATABASE kairus_restore',
            'BackupRestoreVerificationTest',
            'DROP DATABASE IF EXISTS kairus_restore',
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $workflow,
                "Il workflow CI non contiene più '{$expected}', citato (direttamente o come passo del contratto) da docs/BACKUP_V2_OPERATIONS.md."
            );
        }
    }

    public function test_the_fixture_seeder_and_verification_test_files_still_exist(): void
    {
        $this->assertFileExists(base_path('database/seeders/BackupRestoreTestSeeder.php'));
        $this->assertFileExists(base_path('tests/Feature/Console/BackupRestoreVerificationTest.php'));
    }

    /**
     * Tripwire: se un comando di restore locale/on-demand comparisse
     * senza che questo documento (che oggi dichiara esplicitamente
     * "no artisan backup:restore* command exists today") venga
     * aggiornato di conseguenza, l'affermazione diventerebbe falsa in
     * silenzio.
     */
    public function test_the_doc_still_says_no_local_restore_command_exists_and_none_does(): void
    {
        $doc = $this->doc();

        $this->assertStringContainsString('no `artisan backup:restore*` command exists today', $doc);

        foreach (Route::getRoutes() as $route) {
            $this->assertStringNotContainsString('backup/restore', $route->uri());
        }

        $commandFiles = glob(base_path('app/Console/Commands/*.php')) ?: [];
        foreach ($commandFiles as $file) {
            $basename = basename($file);
            $this->assertStringNotContainsStringIgnoringCase(
                'restore',
                $basename,
                "'{$basename}' sembra un comando di restore — se è un comando reale di restore locale, docs/BACKUP_V2_OPERATIONS.md va aggiornato di conseguenza."
            );
        }
    }

    private function doc(): string
    {
        $content = file_get_contents(base_path('docs/BACKUP_V2_OPERATIONS.md'));
        $this->assertIsString($content);

        return $content;
    }
}
