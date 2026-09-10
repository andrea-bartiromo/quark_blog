<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DeploymentSafetyTest extends TestCase
{
    public function test_production_deploy_requires_and_records_expected_revision(): void
    {
        $script = $this->deployScript();

        $this->assertStringContainsString('EXPECTED_SHA="${1:-}"', $script);
        $this->assertStringContainsString('[0-9a-fA-F]{40}', $script);
        $this->assertStringContainsString('REVISION', $script);
        $this->assertStringContainsString('DEPLOY_INFO', $script);
        $this->assertStringContainsString('date -u', $script);
    }

    public function test_production_deploy_requires_tracked_release_files_to_match_expected_revision(): void
    {
        $script = $this->deployScript();

        $this->assertStringContainsString('git -c core.fileMode=false diff --quiet --ignore-submodules --', $script);
        $this->assertStringContainsString('git -c core.fileMode=false diff --cached --quiet --ignore-submodules --', $script);
        $this->assertStringContainsString('Tracked release files differ from the expected Git revision', $script);
    }

    /**
     * A real bug, found only by actually running deploy.sh twice against
     * the same checkout (see .github/workflows/deploy-safety.yml's own
     * "second deploy.sh run" test): the script's own `chmod -R 755 storage
     * bootstrap/cache` flips tracked files inside bootstrap/cache from the
     * repo's 644 to 755 with zero content change, which then made a SECOND
     * run's dirty-release-artifact check spuriously fail on mode bits
     * alone. `core.fileMode=false` still fails closed on any real content
     * drift — the actual safety intent — it only stops mode-only noise
     * from the script's own prior side effect.
     */
    public function test_production_deploy_tracked_files_check_ignores_its_own_chmod_side_effect(): void
    {
        $script = $this->deployScript();

        $this->assertStringContainsString('chmod -R 755 storage bootstrap/cache', $script);

        preg_match_all('/git -c core\.fileMode=false diff( --cached)? --quiet --ignore-submodules --/', $script, $matches);
        $this->assertCount(2, $matches[0], 'Expected exactly the two tracked-files dirty checks to use core.fileMode=false.');
    }

    public function test_production_deploy_does_not_assume_sqlite_or_run_the_sqlite_only_backup_command(): void
    {
        $script = $this->deployScript();

        $this->assertStringNotContainsString('database/database.sqlite', $script);
        $this->assertStringNotContainsString('php artisan backup:database', $script);
        $this->assertStringContainsString('mysql', $script);
        $this->assertStringContainsString('mariadb', $script);
    }

    public function test_production_deploy_fails_closed_when_migrations_are_pending_without_backup_v2(): void
    {
        $script = $this->deployScript();

        $this->assertStringContainsString('php artisan migrate:status --no-ansi', $script);
        $this->assertStringContainsString('Pending', $script);
        $this->assertStringNotContainsString('php artisan migrate --force', $script);
    }

    /**
     * Prompt 015 (150-prompt deploy-hardening program): questo script
     * presuppone di essere invocato da una directory di release GIA'
     * estratta/clonata (verifica solo `git rev-parse HEAD`) — non ha mai
     * estratto archivi ne' cambiato directory da solo. Blocca ogni
     * regressione futura che introducesse un simile passo implicito.
     */
    public function test_production_deploy_never_extracts_an_archive_or_changes_directory_itself(): void
    {
        $script = $this->deployScript();

        foreach (['tar -x', 'tar x', 'unzip ', 'cd ..', 'cd ../', 'cd /'] as $needle) {
            $this->assertStringNotContainsString($needle, $script, "deploy.sh must never itself extract an archive or leave its working directory (found: {$needle}).");
        }
    }

    /**
     * Prompt 016: rifiuta una directory di release incompleta (manca
     * artisan/composer.json/.git) PRIMA di qualunque altro controllo —
     * cosi' l'operatore vede subito il motivo reale invece di un errore
     * PHP o git confuso più avanti nello script.
     */
    public function test_production_deploy_rejects_an_incomplete_release_directory_before_anything_else(): void
    {
        $script = $this->deployScript();

        $this->assertStringContainsString('[ -f artisan ] ||', $script);
        $this->assertStringContainsString('[ -f composer.json ] ||', $script);
        $this->assertStringContainsString('[ -d .git ] ||', $script);

        $artisanCheckPosition = strpos($script, '[ -f artisan ] ||');
        $composerCheckPosition = strpos($script, '[ -f composer.json ] ||');
        $gitCheckPosition = strpos($script, '[ -d .git ] ||');
        $envCheckPosition = strpos($script, '[ -f .env ] ||');
        $revisionComparePosition = strpos($script, 'ACTUAL_SHA="$(git rev-parse HEAD)"');

        $this->assertNotFalse($artisanCheckPosition);
        $this->assertNotFalse($composerCheckPosition);
        $this->assertNotFalse($gitCheckPosition);
        $this->assertNotFalse($envCheckPosition);
        $this->assertNotFalse($revisionComparePosition);

        foreach ([$artisanCheckPosition, $composerCheckPosition, $gitCheckPosition] as $position) {
            $this->assertLessThan($envCheckPosition, $position, 'Release-completeness guards must run before the .env check.');
            $this->assertLessThan($revisionComparePosition, $position, 'Release-completeness guards must run before the revision is even read.');
        }
    }

    /**
     * Revisione Codex su PR #535: in un checkout creato con
     * `git worktree add` (o un submodule), `.git` e' un file di metadati
     * regolare — non una directory — anche se `git rev-parse HEAD` e ogni
     * verifica successiva funzionano normalmente. La guardia precedente
     * (`[ -d .git ]`) rifiutava un simile checkout, valido e completo,
     * prima ancora di iniziare la verifica. Prova con un worktree Git
     * reale (non un file fittizio) che la guardia lo accetta: l'esecuzione
     * deve proseguire oltre il controllo `.git` e fallire sul controllo
     * successivo (`.env` mancante), mai sul messaggio della guardia `.git`.
     */
    public function test_production_deploy_accepts_a_real_git_worktree_checkout_where_dot_git_is_a_file(): void
    {
        $this->ensureBashAndGitAvailable();

        $sourceRepo = base_path('storage/framework/testing/deploy-worktree-source-'.bin2hex(random_bytes(6)));
        $worktree = base_path('storage/framework/testing/deploy-worktree-'.bin2hex(random_bytes(6)));

        try {
            (new Process(['git', 'init', '--quiet', '--initial-branch=main', $sourceRepo]))->mustRun();
            (new Process(['git', '-C', $sourceRepo, 'config', 'user.email', 'test@example.test']))->mustRun();
            (new Process(['git', '-C', $sourceRepo, 'config', 'user.name', 'Test']))->mustRun();
            file_put_contents($sourceRepo.'/artisan', "#!/usr/bin/env php\n");
            file_put_contents($sourceRepo.'/composer.json', "{}\n");
            (new Process(['git', '-C', $sourceRepo, 'add', '-A']))->mustRun();
            (new Process(['git', '-C', $sourceRepo, 'commit', '--quiet', '-m', 'base']))->mustRun();

            (new Process(['git', '-C', $sourceRepo, 'worktree', 'add', '--quiet', '--detach', $worktree, 'main']))->mustRun();

            $this->assertFileExists($worktree.'/.git', 'A real git worktree checkout must have created .git.');
            $this->assertFalse(is_dir($worktree.'/.git'), 'This test only proves the fix when .git is a FILE, not a directory — otherwise it would pass even without the fix.');

            $actualSha = trim((new Process(['git', '-C', $worktree, 'rev-parse', 'HEAD']))->mustRun()->getOutput());

            $process = new Process(['bash', base_path('deploy.sh'), $actualSha], $worktree);
            $process->run();

            $this->assertFalse($process->isSuccessful(), 'This worktree deliberately has no .env — deploy.sh must still fail, just not on the .git guard.');
            $this->assertStringNotContainsString('.git not found', $process->getErrorOutput(), 'A real worktree checkout (.git as a file) must not be rejected by the release-completeness guard.');
            $this->assertStringContainsString('.env is missing', $process->getErrorOutput(), 'Execution must reach past the .git guard to the next check.');
        } finally {
            (new Process(['git', '-C', $sourceRepo, 'worktree', 'remove', '--force', $worktree]))->run();
            File::deleteDirectory($sourceRepo);
            File::deleteDirectory($worktree);
        }
    }

    private function ensureBashAndGitAvailable(): void
    {
        try {
            $bash = new Process(['bash', '-lc', 'printf ok']);
            $bash->setTimeout(5);
            $bash->run();

            $git = new Process(['git', '--version']);
            $git->setTimeout(5);
            $git->run();

            if (! ($bash->isSuccessful() && trim($bash->getOutput()) === 'ok' && $git->isSuccessful())) {
                $this->markTestSkipped('No functional Bash/Git shell is available in this environment.');
            }
        } catch (\Throwable) {
            $this->markTestSkipped('No functional Bash/Git shell is available in this environment.');
        }
    }

    public function test_production_deploy_never_generates_a_new_app_key(): void
    {
        $script = $this->deployScript();

        $this->assertStringNotContainsString('php artisan key:generate --force', $script);
        $this->assertStringContainsString('APP_KEY', $script);
    }

    public function test_production_environment_example_targets_mysql_mariadb_configuration(): void
    {
        $env = file_get_contents(base_path('.env.production.example'));

        $this->assertIsString($env);
        $this->assertStringContainsString('DB_CONNECTION=mysql', $env);
        $this->assertStringContainsString('DB_HOST=', $env);
        $this->assertStringContainsString('DB_PORT=', $env);
        $this->assertStringContainsString('DB_DATABASE=', $env);
        $this->assertStringContainsString('DB_USERNAME=', $env);
        $this->assertStringContainsString('DB_PASSWORD=', $env);
        $this->assertStringNotContainsString('DB_CONNECTION=sqlite', $env);
    }

    /**
     * Kairus Prompt 281 (programma 251-400): DEPLOY_SERVED_PUBLIC_ROOT
     * attiva l'intero gate di drift degli asset statici (vedi
     * PublicAssetDriftDetector, deploy.sh e docs/DEPLOYMENT.md) ma non
     * compariva affatto in .env.production.example, a differenza del suo
     * omologo per la Libreria media (MEDIA_PUBLIC_ROOT, gia' documentato
     * li' con un esempio commentato) — un operatore che segue solo questo
     * file come checklist di configurazione non avrebbe mai saputo che la
     * variabile esiste, lasciando il gate silenziosamente disattivato per
     * omissione, non per scelta deliberata.
     */
    public function test_production_environment_example_documents_the_asset_drift_served_root_variable(): void
    {
        $env = file_get_contents(base_path('.env.production.example'));

        $this->assertIsString($env);
        $this->assertStringContainsString('DEPLOY_SERVED_PUBLIC_ROOT', $env);
    }

    public function test_sqlite_remains_the_deterministic_test_database(): void
    {
        $phpunit = file_get_contents(base_path('phpunit.xml'));
        $workflow = file_get_contents(base_path('.github/workflows/tests.yml'));

        $this->assertIsString($phpunit);
        $this->assertIsString($workflow);
        $this->assertStringContainsString('DB_CONNECTION" value="sqlite"', $phpunit);
        $this->assertStringContainsString('DB_CONNECTION=sqlite', $workflow);
    }

    public function test_scheduled_sqlite_backup_is_guarded_outside_sqlite_environments(): void
    {
        $schedule = file_get_contents(base_path('routes/console.php'));

        $this->assertIsString($schedule);
        $this->assertStringContainsString("config('database.default') === 'sqlite'", $schedule);
        $this->assertStringContainsString("Schedule::command('backup:database')", $schedule);
    }

    /**
     * Difetto di rilascio riscontrato: `newsletter:reconfirmation-cleanup`
     * era schedulato in routes/console.php ma, in un tentativo di
     * rilascio controllato, Artisan lo segnalava come "Command is not
     * defined" — cioè `Schedule::command('newsletter:reconfirmation-cleanup')`
     * puntava a un nome mai realmente registrato. Nessun test esistente
     * collegava le due cose: guardava routes/console.php per stringhe
     * isolate (vedi il test sopra), oppure verificava il comando in
     * isolamento, mai "questo nome schedulato corrisponde a un comando
     * che Artisan conosce davvero". Legge ogni riga Schedule::command(...)
     * per nome e la confronta con l'elenco comandi realmente registrato
     * in questo stesso processo: una futura riga schedulata che punta a
     * un comando rinominato, rimosso o mai registrato fa fallire la
     * suite, non solo un rilascio in produzione.
     */
    public function test_every_scheduled_command_in_routes_console_is_actually_registered(): void
    {
        $schedule = file_get_contents(base_path('routes/console.php'));
        $this->assertIsString($schedule);

        preg_match_all("/Schedule::command\(\s*['\"]([^'\"]+)['\"]/", $schedule, $matches);
        $this->assertNotEmpty($matches[1], 'Expected at least one Schedule::command(...) line in routes/console.php.');

        $registered = array_keys(Artisan::all());

        foreach ($matches[1] as $rawCommand) {
            // Una riga può includere argomenti/opzioni inline (es.
            // "project:sync-editorial-calendar --execute"): il nome del
            // comando registrato in Artisan è solo il primo token.
            $commandName = strtok($rawCommand, ' ');

            $this->assertContains(
                $commandName,
                $registered,
                "routes/console.php schedules '{$commandName}' but no such command is registered in Artisan — this is exactly the release defect that made newsletter:reconfirmation-cleanup fail with \"Command is not defined\" in production.",
            );
        }
    }

    /**
     * Missione 19 (secondo batch autonomo KAIRUS, Fase B — Deployment
     * Reliability — "Release metadata integrity"): REVISION and DEPLOY_INFO
     * are two separate files written from the same shell variable
     * ($ACTUAL_SHA). Nothing before this test asserted they stay derived
     * from that single variable, so a future edit could desync them (e.g.
     * DEPLOY_INFO's revision= line quietly reintroducing $EXPECTED_SHA, the
     * pre-verification input, instead of the verified $ACTUAL_SHA) with no
     * regression to catch it. This locks both writes to the same source of
     * truth and the write order — always last, only after every check
     * above (migration gate, asset drift gate) has already passed.
     *
     * Missione 13 (già in questo batch, PR precedente) ha già coperto la
     * cattura dell'hash per-file nei manifest di selective-deploy-backup;
     * questa missione copre un gap diverso e non ancora testato — la
     * coerenza reciproca tra REVISION e DEPLOY_INFO dentro deploy.sh
     * stesso, non il meccanismo di backup/rollback.
     */
    public function test_production_deploy_records_revision_and_deploy_info_from_the_same_verified_sha(): void
    {
        $script = $this->deployScript();

        $this->assertStringContainsString("printf '%s\\n' \"\$ACTUAL_SHA\" > REVISION", $script);
        $this->assertStringContainsString("printf 'revision=%s\\n' \"\$ACTUAL_SHA\"", $script);
        $this->assertStringNotContainsString('$EXPECTED_SHA" > REVISION', $script);
        $this->assertStringNotContainsString("revision=%s\\n' \"\$EXPECTED_SHA\"", $script);

        $revisionWritePosition = strpos($script, '> REVISION');
        $assetDriftCheckPosition = strpos($script, 'deploy:asset-drift');
        $migrationGatePosition = strpos($script, 'Pending migrations detected');

        $this->assertNotFalse($revisionWritePosition);
        $this->assertNotFalse($assetDriftCheckPosition);
        $this->assertNotFalse($migrationGatePosition);
        $this->assertGreaterThan($migrationGatePosition, $revisionWritePosition, 'REVISION/DEPLOY_INFO must be recorded after the migration safety gate, never before it.');
        $this->assertGreaterThan($assetDriftCheckPosition, $revisionWritePosition, 'REVISION/DEPLOY_INFO must be recorded after the asset drift gate, never before it.');
    }

    /**
     * DEPLOY_INFO è un artefatto write-only per l'operatore: nessun codice
     * applicativo lo legge (verificato con grep su app/ durante l'indagine
     * di questa missione). Il suo unico requisito di integrità è
     * strutturale — i tre campi su cui un operatore fa affidamento
     * ispezionando una release devono essere sempre presenti e in questa
     * forma.
     */
    public function test_deploy_info_records_the_three_fields_an_operator_relies_on(): void
    {
        $script = $this->deployScript();

        $this->assertStringContainsString("printf 'revision=%s\\n' \"\$ACTUAL_SHA\"", $script);
        $this->assertStringContainsString("printf 'deployed_at_utc=%s\\n' \"\$(date -u '+%Y-%m-%dT%H:%M:%SZ')\"", $script);
        $this->assertStringContainsString("printf 'database_driver=%s\\n' \"\$DB_CONNECTION_VALUE\"", $script);
    }

    /**
     * Prompt 004 (150-prompt deploy-hardening program): #534 rimosse le
     * cache Laravel generate che erano finite tracciate in
     * bootstrap/cache/*.php; bootstrap/cache/.gitignore le esclude ora,
     * ma nessun test provava che non possano ritornare tracciate in
     * futuro (es. un `git add -f` distratto). Usa il vero repository di
     * questo checkout — se `.git` non esiste (es. un archivio sorgente
     * senza storia Git) il test si salta, non fallisce.
     */
    public function test_bootstrap_cache_php_files_are_never_tracked_by_git(): void
    {
        if (! is_dir(base_path('.git'))) {
            $this->markTestSkipped('No .git directory in this checkout — nothing to inspect.');
        }

        $process = new Process(
            ['git', 'ls-files', 'bootstrap/cache'],
            base_path()
        );
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

        $tracked = array_values(array_filter(explode("\n", trim($process->getOutput()))));
        $trackedPhp = array_filter($tracked, fn (string $path) => str_ends_with($path, '.php'));

        $this->assertSame(
            [],
            array_values($trackedPhp),
            'bootstrap/cache must never contain tracked generated PHP caches — see PR #534.'
        );
    }

    public function test_production_deploy_verifies_scheduled_commands_via_a_dedicated_gate_command(): void
    {
        $script = $this->deployScript();

        $this->assertStringContainsString('php artisan deploy:verify-scheduled-commands', $script);

        $cachePosition = strpos($script, 'php artisan view:cache');
        $gatePosition = strpos($script, 'php artisan deploy:verify-scheduled-commands');
        $chmodPosition = strpos($script, 'chmod -R 755 storage bootstrap/cache');

        $this->assertNotFalse($cachePosition);
        $this->assertNotFalse($gatePosition);
        $this->assertNotFalse($chmodPosition);
        $this->assertGreaterThan($cachePosition, $gatePosition, 'The scheduled-command gate must run after the same cache refresh a real release goes through.');
        $this->assertLessThan($chmodPosition, $gatePosition, 'The scheduled-command gate must run before the release is otherwise finalized.');
    }

    /**
     * Difetto di rilascio riscontrato di nuovo su main@0907b4e (dopo PR
     * #540): `newsletter:reconfirmation-cleanup` era correttamente
     * registrato secondo bootstrap/app.php e secondo
     * test_every_scheduled_command_in_routes_console_is_actually_registered
     * (sopra) — un test PHPUnit che chiama Artisan::all() nello STESSO
     * processo già bootstrappato da PHPUnit — eppure un vero
     * sottoprocesso `php artisan newsletter:reconfirmation-cleanup
     * --dry-run` nella release reale falliva con "Command is not
     * defined". Nessun test in-process può per costruzione rilevare
     * questa classe di difetto: deve esistere un processo Artisan
     * REALMENTE separato, in un vero git worktree con vendor collegato,
     * .env di tipo produzione e lo stesso ciclo di cache di deploy.sh.
     *
     * Questo test costruisce esattamente quel worktree, prova prima il
     * caso positivo (il gate passa quando nulla è rotto), poi rompe la
     * registrazione esattamente come l'incidente reale — rimuovendo il
     * file del comando schedulato — e prova che
     * `php artisan deploy:verify-scheduled-commands`, eseguito come vero
     * sottoprocesso separato (mai Artisan::all() in questo processo),
     * fallisce chiuso con l'esatto nome del comando mancante.
     */
    public function test_production_deploy_verify_scheduled_commands_gate_fails_closed_on_a_real_broken_release_worktree(): void
    {
        $this->ensureBashAndGitAvailable();

        $worktree = base_path('storage/framework/testing/deploy-verify-worktree-'.bin2hex(random_bytes(6)));

        try {
            (new Process(['git', 'worktree', 'add', '--quiet', '--detach', $worktree, 'HEAD'], base_path()))->mustRun();

            // Vendor collegato: stessa strategia di una release reale —
            // link, mai una copia — cosi' l'autoloader e' esattamente
            // quello gia' in uso in questo checkout.
            symlink(base_path('vendor'), $worktree.'/vendor');

            $dbPath = $worktree.'/database/deploy_verify_test.sqlite';
            if (! is_dir(dirname($dbPath))) {
                mkdir(dirname($dbPath), 0775, true);
            }
            touch($dbPath);

            $appKey = 'base64:'.base64_encode(random_bytes(32));
            file_put_contents($worktree.'/.env', <<<ENV
            APP_NAME="Kairus"
            APP_ENV=production
            APP_KEY={$appKey}
            APP_DEBUG=false
            APP_URL=https://kairus.it
            APP_LOCALE=it
            APP_TIMEZONE=Europe/Rome
            DB_CONNECTION=sqlite
            DB_DATABASE={$dbPath}
            CACHE_STORE=file
            SESSION_DRIVER=file
            LOG_CHANNEL=daily
            LOG_LEVEL=error
            MAIL_MAILER=array

            ENV);

            (new Process(['php', 'artisan', 'migrate', '--force', '--no-ansi'], $worktree))->mustRun();
            (new Process(['php', 'artisan', 'optimize:clear'], $worktree))->mustRun();
            (new Process(['php', 'artisan', 'config:cache'], $worktree))->mustRun();
            (new Process(['php', 'artisan', 'route:cache'], $worktree))->mustRun();
            (new Process(['php', 'artisan', 'view:cache'], $worktree))->mustRun();

            $healthy = new Process(['php', 'artisan', 'deploy:verify-scheduled-commands', '--no-ansi'], $worktree);
            $healthy->run();
            $this->assertTrue(
                $healthy->isSuccessful(),
                'Expected the gate to pass as a real subprocess on an unmodified release worktree: '.$healthy->getOutput().$healthy->getErrorOutput()
            );
            $this->assertStringContainsString('scheduled commands are registered', $healthy->getOutput());

            $commandFile = $worktree.'/app/Console/Commands/CleanupExpiredNewsletterPending.php';
            $this->assertFileExists($commandFile, 'Fixture assumption broken: this file must exist in HEAD for the negative case to be meaningful.');
            rename($commandFile, $commandFile.'.disabled');

            (new Process(['php', 'artisan', 'optimize:clear'], $worktree))->mustRun();

            $broken = new Process(['php', 'artisan', 'deploy:verify-scheduled-commands', '--no-ansi'], $worktree);
            $broken->run();

            $this->assertFalse(
                $broken->isSuccessful(),
                'A real, separate php artisan subprocess must fail closed on the exact newsletter:reconfirmation-cleanup incident class.'
            );
            $combinedOutput = $broken->getOutput().$broken->getErrorOutput();
            $this->assertStringContainsString('newsletter:reconfirmation-cleanup', $combinedOutput);
            $this->assertStringContainsString('incident class', $combinedOutput);
        } finally {
            (new Process(['git', 'worktree', 'remove', '--force', $worktree], base_path()))->run();
            File::deleteDirectory($worktree);
        }
    }

    private function deployScript(): string
    {
        $script = file_get_contents(base_path('deploy.sh'));

        $this->assertIsString($script);

        return $script;
    }
}
