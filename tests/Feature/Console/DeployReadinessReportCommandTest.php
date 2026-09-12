<?php

namespace Tests\Feature\Console;

use Tests\TestCase;

/**
 * Cantiere 20 (programma 100-cantieri Kairus). deploy:readiness-report
 * aggrega tutte le verifiche `deploy:verify-*`/`deploy:asset-drift`
 * esistenti in un unico report di sola lettura, senza mai eseguire
 * deploy.sh né generare alcun effetto collaterale (nessun refresh cache,
 * nessuna scrittura di REVISION/DEPLOY_INFO).
 */
class DeployReadinessReportCommandTest extends TestCase
{
    public function test_reports_success_when_every_check_passes(): void
    {
        // deploy:verify-persistent-storage e deploy:verify-database-backup
        // sono gli unici, tra le sei verifiche aggregate, il cui esito di
        // default (senza .env di produzione) non è "ok" a prescindere:
        // qui li si configura esplicitamente allo stato sano, cosi' come
        // farebbe un .env di produzione reale.
        config(['backup.v2.directory' => '/var/kairus-shared/backups/mariadb']);
        config(['deploy.release_registry_path' => null]);
        config(['database.default' => 'sqlite']);

        $this->artisan('deploy:readiness-report')
            ->assertExitCode(0)
            ->expectsOutputToContain('Tutte le verifiche sono superate.');
    }

    public function test_reports_json_output_when_requested(): void
    {
        config(['backup.v2.directory' => '/var/kairus-shared/backups/mariadb']);
        config(['deploy.release_registry_path' => null]);
        config(['database.default' => 'sqlite']);

        $this->artisan('deploy:readiness-report', ['--json' => true])->assertExitCode(0);
    }

    /**
     * Il default "di fabbrica" (nessun .env di produzione impostato) per
     * DB_BACKUP_DIRECTORY risolve dentro la directory di release corrente
     * (vedi App\Services\Deploy\PersistentStoragePreflight): una verifica
     * SOLO INFORMATIVA che fallisce non deve mai far fallire il report
     * nel suo complesso, cosi' come non fa fallire deploy.sh stesso.
     */
    public function test_an_informational_only_failure_does_not_fail_the_overall_report(): void
    {
        // Nessuna configurazione esplicita: DB_BACKUP_DIRECTORY resta al
        // default dentro la release corrente, quindi
        // deploy:verify-persistent-storage fallisce (informativo).
        config(['database.default' => 'sqlite']);

        $this->artisan('deploy:readiness-report')
            ->assertExitCode(0)
            ->expectsOutputToContain('Percorsi persistenti')
            ->expectsOutputToContain('rischio da rivedere');
    }

    /**
     * Stesso pattern di DeployAssetDriftReportCommandTest: un file sonda
     * dal nome univoco, mai un file esistente del repository, cosi' da
     * forzare un mismatch reale (bloccante) senza mai toccare alcun file
     * reale della release.
     */
    public function test_a_blocking_failure_fails_the_overall_report(): void
    {
        config(['backup.v2.directory' => '/var/kairus-shared/backups/mariadb']);
        config(['deploy.release_registry_path' => null]);
        config(['database.default' => 'sqlite']);

        $servedRoot = sys_get_temp_dir().'/kairus-test-readiness-report-'.uniqid('', true);
        mkdir($servedRoot, 0775, true);
        config(['deploy.served_public_root' => $servedRoot]);
        $probe = 'probe-'.uniqid('', true).'.css';
        config(['deploy.asset_drift_scan_paths' => [$probe]]);

        file_put_contents(public_path($probe), 'body{color:red}');
        file_put_contents($servedRoot.'/'.$probe, 'body{color:blue}');

        try {
            $this->artisan('deploy:readiness-report')
                ->assertExitCode(1)
                ->expectsOutputToContain('rifiuterebbe questo rilascio');
        } finally {
            @unlink(public_path($probe));
            @unlink($servedRoot.'/'.$probe);
            @rmdir($servedRoot);
        }
    }
}
