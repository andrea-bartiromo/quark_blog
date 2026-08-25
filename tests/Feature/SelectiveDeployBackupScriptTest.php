<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SelectiveDeployBackupScriptTest extends TestCase
{
    /**
     * Memoized across the whole run: a "bash.exe exists on PATH" check is
     * not the same thing as "a real Bash shell can execute a command" —
     * on Windows without an installed WSL distribution, bash.exe is
     * present (the WSL launcher stub) but every invocation fails with
     * "Windows Subsystem for Linux has no installed distributions."
     * scripts/selective-deploy-backup.sh is Linux/production-shell
     * tooling; these tests must therefore be skipped, not failed, when
     * no Bash capable of actually running a command exists.
     */
    private static ?bool $bashAvailable = null;

    private string $root;

    private string $appRoot;

    private string $publicRoot;

    private string $backupRoot;

    private string $script;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureBashAvailable();

        $this->root = storage_path('framework/testing/selective-deploy-backup-'.bin2hex(random_bytes(6)));
        $this->appRoot = $this->root.'/app';
        $this->publicRoot = $this->root.'/public';
        $this->backupRoot = $this->root.'/backups';
        $this->script = base_path('scripts/selective-deploy-backup.sh');

        File::ensureDirectoryExists($this->appRoot);
        File::ensureDirectoryExists($this->publicRoot);
        File::ensureDirectoryExists($this->backupRoot);
    }

    private function ensureBashAvailable(): void
    {
        if (self::$bashAvailable === null) {
            self::$bashAvailable = $this->probeBashCapability();
        }

        if (! self::$bashAvailable) {
            $this->markTestSkipped(
                'No functional Bash shell is available in this environment '.
                '(bash executable missing, or present but unable to run a '.
                'command — e.g. the Windows WSL launcher stub with no '.
                'installed distribution). scripts/selective-deploy-backup.sh '.
                'is Linux deploy tooling exercised via a real bash process; '.
                'skipping rather than failing.'
            );
        }
    }

    private function probeBashCapability(): bool
    {
        try {
            $process = new Process(['bash', '-lc', 'printf ok']);
            $process->setTimeout(5);
            $process->run();

            return $process->isSuccessful() && trim($process->getOutput()) === 'ok';
        } catch (\Throwable) {
            return false;
        }
    }

    private function ensureSymlinkSupported(): void
    {
        $probeTarget = $this->root.'/symlink-capability-probe-target';
        $probeLink = $this->root.'/symlink-capability-probe-link';
        File::ensureDirectoryExists($probeTarget);

        $supported = false;

        try {
            $supported = @symlink($probeTarget, $probeLink);
        } catch (\Throwable) {
            $supported = false;
        }

        if ($supported) {
            @unlink($probeLink);
        }

        if (! $supported) {
            $this->markTestSkipped(
                'symlink() is not permitted in this environment (e.g. '.
                'Windows without Developer Mode / SeCreateSymbolicLinkPrivilege) — '.
                'skipping the symlinked-ancestor escape regression.'
            );
        }
    }

    protected function tearDown(): void
    {
        // markTestSkipped() in ensureBashAvailable() (called from setUp(),
        // before $this->root is assigned) throws immediately — PHPUnit
        // still runs tearDown() afterwards, so $root can reach here
        // uninitialized. Only clean up what setUp() actually created.
        if (isset($this->root)) {
            File::deleteDirectory($this->root);
        }

        parent::tearDown();
    }

    public function test_selective_backup_and_rollback_cover_existing_new_and_multiple_roots(): void
    {
        File::ensureDirectoryExists($this->appRoot.'/a');
        File::ensureDirectoryExists($this->publicRoot.'/css');
        File::put($this->appRoot.'/a/one.php', "old-a\n");
        File::put($this->publicRoot.'/css/site.css', "old-css\n");
        chmod($this->appRoot.'/a/one.php', 0640);

        $manifest = $this->root.'/manifest.tsv';
        File::put($manifest, "app\ta/one.php\napp\tb/new.php\npublic\tcss/site.css\n");

        $backup = $this->runBackup($manifest);

        $this->assertFileExists($backup.'/.complete');
        $this->assertFileExists($backup.'/files/app/a/one.php');
        $this->assertFileExists($backup.'/files/public/css/site.css');
        $this->assertStringContainsString("app\tb/new.php", File::get($backup.'/new-files.tsv'));
        $this->assertStringContainsString("app\ta/one.php", File::get($backup.'/backed-up-files.tsv'));
        $this->assertSame('640', substr(sprintf('%o', fileperms($backup.'/files/app/a/one.php')), -3));

        File::put($this->appRoot.'/a/one.php', "new-a\n");
        File::ensureDirectoryExists($this->appRoot.'/b');
        File::put($this->appRoot.'/b/new.php', "new-file\n");
        File::put($this->publicRoot.'/css/site.css', "new-css\n");

        $this->rollbackProcess($backup)->mustRun();

        $this->assertSame("old-a\n", File::get($this->appRoot.'/a/one.php'));
        $this->assertSame("old-css\n", File::get($this->publicRoot.'/css/site.css'));
        $this->assertFileDoesNotExist($this->appRoot.'/b/new.php');
        $this->assertSame('640', substr(sprintf('%o', fileperms($this->appRoot.'/a/one.php')), -3));
        $this->assertFileExists($backup.'/.rollback-complete');
    }

    public function test_incomplete_backup_is_rejected_for_rollback(): void
    {
        File::put($this->appRoot.'/existing.php', "old\n");
        $manifest = $this->root.'/manifest.tsv';
        File::put($manifest, "app\texisting.php\n");
        $backup = $this->runBackup($manifest);
        File::delete($backup.'/.complete');

        $process = $this->rollbackProcess($backup);
        $process->run();

        $this->assertFalse($process->isSuccessful());
        $this->assertSame("old\n", File::get($this->appRoot.'/existing.php'));
    }

    public function test_completed_rollback_cannot_be_replayed(): void
    {
        $manifest = $this->root.'/manifest.tsv';
        File::put($manifest, "app\tnew.php\n");
        $backup = $this->runBackup($manifest);
        File::put($this->appRoot.'/new.php', "deployed\n");

        $this->rollbackProcess($backup)->mustRun();
        File::put($this->appRoot.'/new.php', "created-after-rollback\n");

        $second = $this->rollbackProcess($backup);
        $second->run();

        $this->assertFalse($second->isSuccessful());
        $this->assertSame("created-after-rollback\n", File::get($this->appRoot.'/new.php'));
    }

    public function test_invalid_absolute_duplicate_empty_or_forbidden_manifest_fails_closed(): void
    {
        File::put($this->appRoot.'/trailing.php', "old\n");

        foreach ([
            "app\t../escape\n",
            "app\t/etc/passwd\n",
            "app\ttrailing.php/\n",
            "app\tvendor/autoload.php\n",
            "app\t.env.production\n",
            "app\ta.php\textra\n",
            "app\ta.php\napp\ta.php\n",
            "# comment only\n",
        ] as $content) {
            $manifest = $this->root.'/bad-'.bin2hex(random_bytes(3)).'.tsv';
            File::put($manifest, $content);
            $process = $this->backupProcess($manifest);
            $process->run();
            $this->assertFalse($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());
        }
    }

    public function test_env_example_templates_are_accepted_and_backed_up(): void
    {
        File::put($this->appRoot.'/.env.example', "APP_ENV=local\n");
        File::put($this->appRoot.'/.env.production.example', "APP_ENV=production\n");

        $manifest = $this->root.'/env-templates.tsv';
        File::put($manifest, "app\t.env.example\napp\t.env.production.example\n");

        $backup = $this->runBackup($manifest);

        $backedUp = File::get($backup.'/backed-up-files.tsv');
        $this->assertStringContainsString("app\t.env.example", $backedUp);
        $this->assertStringContainsString("app\t.env.production.example", $backedUp);
        $this->assertSame("APP_ENV=local\n", File::get($backup.'/files/app/.env.example'));
        $this->assertSame("APP_ENV=production\n", File::get($backup.'/files/app/.env.production.example'));
    }

    public function test_real_env_files_remain_forbidden(): void
    {
        $i = 0;
        foreach (['.env', '.env.production'] as $forbidden) {
            $manifest = $this->root.'/env-real-'.bin2hex(random_bytes(3)).'.tsv';
            File::put($manifest, "app\t.env.example\napp\t{$forbidden}\n");

            // Distinct target SHA per iteration (not the shared fixed pair
            // backupProcess() uses) so each attempt gets its own backup
            // directory name — the real backup_dir path is created before
            // the manifest is validated, so two calls sharing both SHAs and
            // the same wall-clock second would otherwise collide on
            // "backup path already exists" and mask the rejection this test
            // is actually checking.
            $i++;
            $process = new Process([
                'bash', $this->script, 'backup',
                '--manifest', $manifest,
                '--app-root', $this->appRoot,
                '--public-root', $this->publicRoot,
                '--backup-root', $this->backupRoot,
                '--previous-sha', str_repeat('0', 40),
                '--target-sha', str_repeat((string) $i, 40),
            ]);
            $process->run();

            $this->assertFalse($process->isSuccessful(), "expected rejection for manifest entry: {$forbidden}");
            $this->assertStringContainsString(
                'forbidden environment path: '.$forbidden,
                $process->getErrorOutput()
            );
        }
    }

    public function test_directory_manifest_entry_is_rejected(): void
    {
        File::ensureDirectoryExists($this->appRoot.'/config-dir');
        $manifest = $this->root.'/directory.tsv';
        File::put($manifest, "app\tconfig-dir\n");

        $process = $this->backupProcess($manifest);
        $process->run();

        $this->assertFalse($process->isSuccessful());
    }

    public function test_symlinked_ancestor_cannot_escape_destination_root(): void
    {
        $this->ensureSymlinkSupported();

        $outside = $this->root.'/outside';
        File::ensureDirectoryExists($outside);
        File::put($outside.'/secret.php', "secret\n");
        symlink($outside, $this->appRoot.'/linked');

        $manifest = $this->root.'/symlink-escape.tsv';
        File::put($manifest, "app\tlinked/secret.php\n");
        $process = $this->backupProcess($manifest);
        $process->run();

        $this->assertFalse($process->isSuccessful());
    }

    public function test_rollback_rejects_wrong_destination_roots(): void
    {
        File::put($this->appRoot.'/existing.php', "old\n");
        $manifest = $this->root.'/manifest.tsv';
        File::put($manifest, "app\texisting.php\n");
        $backup = $this->runBackup($manifest);
        $wrongApp = $this->root.'/wrong-app';
        File::ensureDirectoryExists($wrongApp);

        $process = new Process([
            'bash', $this->script, 'rollback',
            '--backup-dir', $backup,
            '--app-root', $wrongApp,
            '--public-root', $this->publicRoot,
        ]);
        $process->run();

        $this->assertFalse($process->isSuccessful());
        $this->assertFileDoesNotExist($wrongApp.'/existing.php');
    }

    public function test_rollback_rejects_corrupted_file_lists_outside_stored_manifest(): void
    {
        File::put($this->appRoot.'/existing.php', "old\n");
        File::put($this->appRoot.'/unrelated.php', "keep\n");
        $manifest = $this->root.'/manifest.tsv';
        File::put($manifest, "app\texisting.php\n");
        $backup = $this->runBackup($manifest);
        File::append($backup.'/new-files.tsv', "app\tunrelated.php\n");

        $process = $this->rollbackProcess($backup);
        $process->run();

        $this->assertFalse($process->isSuccessful());
        $this->assertSame("keep\n", File::get($this->appRoot.'/unrelated.php'));
    }

    public function test_rollback_refuses_file_path_that_became_directory_before_changing_anything(): void
    {
        File::put($this->appRoot.'/first.php', "old-first\n");
        File::put($this->appRoot.'/second.php', "old-second\n");
        $manifest = $this->root.'/manifest.tsv';
        File::put($manifest, "app\tfirst.php\napp\tsecond.php\n");
        $backup = $this->runBackup($manifest);

        File::put($this->appRoot.'/first.php', "new-first\n");
        File::delete($this->appRoot.'/second.php');
        File::ensureDirectoryExists($this->appRoot.'/second.php');

        $process = $this->rollbackProcess($backup);
        $process->run();

        $this->assertFalse($process->isSuccessful());
        $this->assertSame("new-first\n", File::get($this->appRoot.'/first.php'));
        $this->assertDirectoryExists($this->appRoot.'/second.php');
    }

    public function test_pr194_like_manifest_captures_only_preexisting_runtime_files(): void
    {
        $entries = [
            ['app', 'app/Http/Controllers/Admin/ContentClusterController.php'],
            ['app', 'app/Http/Controllers/HomeController.php'],
            ['app', 'app/Services/Concerns/AppliesMediaReferenceUpdates.php'],
            ['app', 'app/Services/MediaReferenceService.php'],
            ['app', 'app/Services/MediaUsageService.php'],
            ['app', 'resources/views/admin/content-clusters/form.blade.php'],
            ['app', 'resources/views/articles/partials/path-continuation.blade.php'],
            ['app', 'resources/views/content-clusters/index.blade.php'],
            ['app', 'resources/views/content-clusters/show.blade.php'],
            ['app', 'resources/views/home.blade.php'],
            ['app', 'resources/views/home/partials/paths-discovery.blade.php'],
            ['app', 'resources/views/home/partials/turing-teaser.blade.php'],
            ['app', 'routes/content-clusters-admin.php'],
            ['public', 'css/content-clusters.css'],
            ['public', 'css/frontend-hardening.css'],
        ];

        $manifestLines = [];
        foreach ($entries as [$scope, $relative]) {
            $root = $scope === 'app' ? $this->appRoot : $this->publicRoot;
            if ($relative !== 'resources/views/home/partials/paths-discovery.blade.php') {
                File::ensureDirectoryExists(dirname($root.'/'.$relative));
                File::put($root.'/'.$relative, "x\n");
            }
            $manifestLines[] = $scope."\t".$relative;
        }

        File::ensureDirectoryExists($this->appRoot.'/vendor/large');
        File::ensureDirectoryExists($this->appRoot.'/storage/runtime');
        File::ensureDirectoryExists($this->appRoot.'/tests/Feature');
        File::ensureDirectoryExists($this->appRoot.'/docs');
        File::ensureDirectoryExists($this->publicRoot.'/assets/img/media');
        File::put($this->appRoot.'/vendor/large/blob', str_repeat('v', 256 * 1024));
        File::put($this->appRoot.'/storage/runtime/blob', str_repeat('s', 128 * 1024));
        File::put($this->appRoot.'/tests/Feature/NoiseTest.php', str_repeat('t', 64 * 1024));
        File::put($this->appRoot.'/docs/noise.md', str_repeat('d', 64 * 1024));
        File::put($this->publicRoot.'/assets/img/media/blob', str_repeat('m', 256 * 1024));

        $manifest = $this->root.'/pr194.tsv';
        File::put($manifest, implode("\n", $manifestLines)."\n");
        $backup = $this->runBackup($manifest);

        $backedUp = $this->nonEmptyLines($backup.'/backed-up-files.tsv');
        $newFiles = $this->nonEmptyLines($backup.'/new-files.tsv');
        $this->assertCount(14, $backedUp);
        $this->assertSame(["app\tresources/views/home/partials/paths-discovery.blade.php"], $newFiles);
        $this->assertDirectoryDoesNotExist($backup.'/files/app/vendor');
        $this->assertDirectoryDoesNotExist($backup.'/files/app/storage');
        $this->assertDirectoryDoesNotExist($backup.'/files/app/tests');
        $this->assertDirectoryDoesNotExist($backup.'/files/app/docs');
        $this->assertDirectoryDoesNotExist($backup.'/files/public/assets');
        $this->assertLessThan(128 * 1024, $this->directorySize($backup));
    }

    /**
     * Missione 11 (secondo batch autonomo KAIRUS, Fase B — Deployment
     * Reliability): "Cover the exact public-premium.css incident as
     * regression" — riproduce l'incidente reale (24/08) e prova che un
     * rollback lo ripara COMPLETAMENTE, non solo sul contenuto del file.
     *
     * L'incidente non era "un file sbagliato": era un file corretto sulla
     * radice servita ma con una querystring di versione (?v=) che
     * continuava a puntare alla vecchia copia, perché REVISION non faceva
     * parte di nessun processo di rollback verificato. Un test che
     * ripristinasse solo public-premium.css sulle due radici, senza
     * toccare REVISION, proverebbe un rollback INCOMPLETO — esattamente il
     * tipo di falso senso di sicurezza che questa missione chiede di
     * escludere esplicitamente.
     *
     * REVISION vive a base_path(), fuori da app-root/public-root simulati
     * da questa classe: qui si prova che il MECCANISMO di backup/rollback
     * (comune a qualunque file "app"-scoped, incluso REVISION quando
     * l'operatore lo include nel proprio manifest) ripristina il suo
     * contenuto byte-per-byte. Che quel contenuto, una volta ripristinato,
     * produca il token `?v=` corretto è già provato esaustivamente da
     * VersionedAssetTest — non riprovato qui per non duplicare copertura.
     */
    public function test_rollback_restores_public_premium_css_on_both_roots_and_the_revision_token_together(): void
    {
        File::ensureDirectoryExists($this->appRoot.'/css');
        File::ensureDirectoryExists($this->publicRoot.'/css');

        $oldRevision = str_repeat('a', 40);
        $oldCss = "body{color:blue} /* release {$oldRevision} */\n";

        // Stato pre-incidente: le due radici e il token di versione
        // coincidono, esattamente come subito dopo un deploy riuscito.
        File::put($this->appRoot.'/REVISION', $oldRevision."\n");
        File::put($this->appRoot.'/css/public-premium.css', $oldCss);
        File::put($this->publicRoot.'/css/public-premium.css', $oldCss);

        $manifest = $this->root.'/public-premium-incident.tsv';
        File::put($manifest, "app\tREVISION\napp\tcss/public-premium.css\npublic\tcss/public-premium.css\n");

        $backup = $this->runBackup($manifest);

        // Il "deploy fallito": REVISION avanza e public-premium.css cambia
        // SOLO sulla radice applicativa — la stessa identica divergenza
        // dell'incidente reale (public_html restava quella corretta e
        // aggiornata, kairus_app/public quella stale; qui invertiamo i
        // ruoli concreti ma il punto è lo stesso: le due copie e il token
        // di versione finiscono disallineati tra loro).
        $newRevision = str_repeat('b', 40);
        $newCss = "body{color:red} /* release {$newRevision} */\n";
        File::put($this->appRoot.'/REVISION', $newRevision."\n");
        File::put($this->appRoot.'/css/public-premium.css', $newCss);

        $this->rollbackProcess($backup)->mustRun();

        $this->assertSame($oldRevision."\n", File::get($this->appRoot.'/REVISION'), 'REVISION must roll back together with the asset it versions, not be left pointing at the failed release.');
        $this->assertSame($oldCss, File::get($this->appRoot.'/css/public-premium.css'));
        $this->assertSame($oldCss, File::get($this->publicRoot.'/css/public-premium.css'));
        $this->assertSame(
            File::get($this->appRoot.'/css/public-premium.css'),
            File::get($this->publicRoot.'/css/public-premium.css'),
            'Post-rollback, both document roots must serve byte-identical content — the exact property whose absence caused the real incident.'
        );
    }

    /**
     * Missione 13 (secondo batch autonomo KAIRUS, Fase B — Deployment
     * Reliability): "Ensure selective deploy manifests capture: source
     * SHA, target SHA, file root, file hash, timestamp." Le prime quattro
     * erano già catturate (metadata.env, roots.tsv); mancava l'hash
     * per-file, aggiunto in questa missione come file-hashes.tsv.
     */
    public function test_backup_records_a_sha256_hash_per_backed_up_file(): void
    {
        File::put($this->appRoot.'/one.php', "content-a\n");
        $manifest = $this->root.'/manifest.tsv';
        File::put($manifest, "app\tone.php\n");

        $backup = $this->runBackup($manifest);

        $expectedHash = hash('sha256', "content-a\n");
        $this->assertSame("app\tone.php\t{$expectedHash}\n", File::get($backup.'/file-hashes.tsv'));
    }

    /**
     * La verifica di integrità (Missione 13) deve fermare il rollback
     * PRIMA di toccare qualunque destinazione, non a metà: qui il file
     * nel backup viene alterato dopo la creazione del backup stesso (come
     * farebbe una corruzione o una manomissione mentre riposa su
     * backup-root), e il rollback deve fallire chiuso senza scrivere nulla.
     */
    public function test_rollback_fails_closed_when_a_backed_up_file_no_longer_matches_its_recorded_hash(): void
    {
        File::put($this->appRoot.'/one.php', "original\n");
        $manifest = $this->root.'/manifest.tsv';
        File::put($manifest, "app\tone.php\n");
        $backup = $this->runBackup($manifest);

        // Simula corruzione/manomissione del backup dopo la sua creazione.
        File::put($backup.'/files/app/one.php', "tampered\n");
        File::put($this->appRoot.'/one.php', "deployed\n");

        $process = $this->rollbackProcess($backup);
        $process->run();

        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString('backup integrity check failed', $process->getErrorOutput());
        $this->assertSame("deployed\n", File::get($this->appRoot.'/one.php'), 'A failed integrity check must not touch the destination at all.');
        $this->assertFileDoesNotExist($backup.'/.rollback-complete');
    }

    /**
     * Retrocompatibilità esplicita: un backup creato da una versione di
     * questo script precedente alla Missione 13 non ha file-hashes.tsv —
     * il rollback deve continuare a funzionare esattamente come prima,
     * saltando semplicemente la verifica che non può fare.
     */
    public function test_rollback_still_succeeds_on_a_pre_mission13_backup_without_file_hashes(): void
    {
        File::put($this->appRoot.'/one.php', "original\n");
        $manifest = $this->root.'/manifest.tsv';
        File::put($manifest, "app\tone.php\n");
        $backup = $this->runBackup($manifest);

        File::delete($backup.'/file-hashes.tsv');
        File::put($this->appRoot.'/one.php', "deployed\n");

        $this->rollbackProcess($backup)->mustRun();

        $this->assertSame("original\n", File::get($this->appRoot.'/one.php'));
    }

    private function runBackup(string $manifest): string
    {
        $process = $this->backupProcess($manifest);
        $process->mustRun();

        return trim($process->getOutput());
    }

    private function backupProcess(string $manifest): Process
    {
        return new Process([
            'bash', $this->script, 'backup',
            '--manifest', $manifest,
            '--app-root', $this->appRoot,
            '--public-root', $this->publicRoot,
            '--backup-root', $this->backupRoot,
            '--previous-sha', str_repeat('1', 40),
            '--target-sha', str_repeat('2', 40),
        ]);
    }

    private function rollbackProcess(string $backup): Process
    {
        return new Process([
            'bash', $this->script, 'rollback',
            '--backup-dir', $backup,
            '--app-root', $this->appRoot,
            '--public-root', $this->publicRoot,
        ]);
    }

    /** @return list<string> */
    private function nonEmptyLines(string $path): array
    {
        return array_values(array_filter(explode("\n", trim(File::get($path)))));
    }

    private function directorySize(string $directory): int
    {
        $size = 0;
        foreach (File::allFiles($directory) as $file) {
            $size += $file->getSize();
        }

        return $size;
    }
}
