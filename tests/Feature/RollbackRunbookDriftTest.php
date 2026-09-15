<?php

namespace Tests\Feature;

use App\Services\PublicMediaSyncService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Cantiere 75 (programma "100 cantieri Kairus", documentazione pura).
 * docs/ROLLBACK_RUNBOOK.md non introduce alcun comando o script nuovo:
 * si limita a mettere in sequenza meccanismi già esistenti (migrate:
 * rollback, scripts/selective-deploy-backup.sh, i comandi di verifica
 * deploy, la sequenza di refresh cache). Questo test verifica che ogni
 * comando/script/documento citato a supporto di quel runbook esista
 * ancora davvero — stesso principio già in uso in
 * BackupRestoreCiEvidenceDriftTest e TrustEditorialProtocolDriftTest —
 * e che nessun comando artisan che orchestri un rollback end-to-end sia
 * comparso nel frattempo senza una decisione esplicita a monte (stesso
 * principio del tripwire "no artisan backup:restore*" di Cantiere 73).
 */
class RollbackRunbookDriftTest extends TestCase
{
    public function test_the_runbook_cites_only_artisan_commands_that_are_actually_registered(): void
    {
        $doc = $this->doc();
        $registered = array_keys(Artisan::all());

        foreach ([
            'optimize:clear',
            'config:cache',
            'route:cache',
            'view:cache',
            'migrate:status',
            'migrate:rollback',
            'backup:database-v2',
            'deploy:verify-front-controller',
            'deploy:asset-drift',
            'release:registry',
        ] as $command) {
            $this->assertStringContainsString(
                $command,
                $doc,
                "Il runbook dovrebbe citare il comando '{$command}'."
            );
            $this->assertContains(
                $command,
                $registered,
                "Il runbook cita '{$command}', ma non risulta più registrato in Artisan::all()."
            );
        }
    }

    public function test_the_runbook_cites_scripts_that_still_exist_with_the_documented_verbs(): void
    {
        $doc = $this->doc();

        $this->assertFileExists(base_path('scripts/selective-deploy-backup.sh'));
        $this->assertFileExists(base_path('scripts/git-release-manifest.sh'));

        $script = file_get_contents(base_path('scripts/selective-deploy-backup.sh'));
        $this->assertIsString($script);

        foreach (['backup)', 'rollback)', '--backup-dir', '--app-root', '--public-root', '--manifest', '--backup-root', '--previous-sha', '--target-sha'] as $flag) {
            $this->assertStringContainsString(
                $flag,
                $script,
                "scripts/selective-deploy-backup.sh non contiene più '{$flag}': l'interfaccia citata dal runbook potrebbe essere cambiata."
            );
        }

        foreach (['scripts/selective-deploy-backup.sh', 'scripts/git-release-manifest.sh'] as $reference) {
            $this->assertStringContainsString($reference, $doc);
        }
    }

    public function test_the_runbook_cites_docs_that_still_exist(): void
    {
        $doc = $this->doc();

        foreach ([
            'docs/DEPLOYMENT.md',
            'docs/CPANEL_FRONT_CONTROLLER_RUNBOOK.md',
            'docs/NEWSLETTER_PENDING_RECOVERY.md',
            'docs/BACKUP_V2_OPERATIONS.md',
        ] as $referencedDoc) {
            $this->assertStringContainsString($referencedDoc, $doc);
            $this->assertFileExists(base_path($referencedDoc));
        }
    }

    public function test_the_media_sync_service_cited_by_the_runbook_still_exists(): void
    {
        $doc = $this->doc();

        $this->assertStringContainsString('PublicMediaSyncService', $doc);
        $this->assertTrue(
            class_exists(PublicMediaSyncService::class),
            'App\Services\PublicMediaSyncService citato dal runbook non esiste più.'
        );
    }

    public function test_deployment_doc_still_links_to_the_rollback_runbook(): void
    {
        $deploymentDoc = file_get_contents(base_path('docs/DEPLOYMENT.md'));
        $this->assertIsString($deploymentDoc);

        $this->assertStringContainsString('docs/ROLLBACK_RUNBOOK.md', $deploymentDoc);
    }

    /**
     * Tripwire: il runbook dichiara esplicitamente che nessun comando
     * orchestra un rollback end-to-end (nessun 'artisan rollback:release'
     * o simile) — se un simile comando comparisse senza che questa
     * affermazione venga aggiornata di conseguenza, l'affermazione
     * diventerebbe falsa in silenzio, esattamente come il tripwire
     * "no artisan backup:restore*" di Cantiere 73.
     */
    public function test_no_orchestrating_rollback_command_exists_without_updating_the_runbook(): void
    {
        $doc = $this->doc();

        $this->assertStringContainsString(
            'un ipotetico `artisan rollback:release`',
            $doc,
            'Il runbook dovrebbe dichiarare esplicitamente che nessun comando orchestratore esiste oggi.'
        );

        foreach (array_keys(Artisan::all()) as $commandName) {
            $this->assertStringNotContainsStringIgnoringCase(
                'rollback:release',
                $commandName,
                "Il comando Artisan registrato '{$commandName}' sembra orchestrare un rollback end-to-end — se lo fa davvero, docs/ROLLBACK_RUNBOOK.md va aggiornato di conseguenza."
            );
        }
    }

    private function doc(): string
    {
        $content = file_get_contents(base_path('docs/ROLLBACK_RUNBOOK.md'));
        $this->assertIsString($content);

        return $content;
    }
}
