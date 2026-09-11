<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Prompt 10 (programma 100-prompt Kairus, Fase P0 — affidabilità del
 * rilascio): prova empirica del meccanismo esatto che Prompt 8 difende
 * — due directory di release REALMENTE diverse (lo schema a directory
 * separate + switch di symlink già in uso in produzione garantisce che
 * ogni deploy ne abbia una diversa dal precedente), con un
 * bootstrap/cache/config.php scritto per la PRIMA copiato per errore
 * nella SECONDA (una copia/rsync che include per errore bootstrap/cache,
 * o un operatore che salta il refresh cache). Nessun test in-process può
 * per costruzione dimostrare questo: storage_path() dipende dal vero
 * percorso assoluto del processo PHP in esecuzione, non simulabile
 * dentro lo stesso processo PHPUnit già bootstrappato da un'unica
 * directory. Vedi App\Services\Deploy\CachedConfigPathAudit e
 * App\Console\Commands\DeployVerifyCachePaths.
 */
class DeployVerifyCachePathsRealSubprocessTest extends TestCase
{
    public function test_gate_fails_closed_when_a_stale_config_cache_from_a_different_release_directory_is_copied_in_and_passes_again_after_a_real_recache(): void
    {
        $this->ensureBashAndGitAvailable();

        $worktreeA = base_path('storage/framework/testing/deploy-cache-path-worktree-a-'.bin2hex(random_bytes(6)));
        $worktreeB = base_path('storage/framework/testing/deploy-cache-path-worktree-b-'.bin2hex(random_bytes(6)));

        try {
            (new Process(['git', 'worktree', 'add', '--quiet', '--detach', $worktreeA, 'HEAD'], base_path()))->mustRun();
            (new Process(['git', 'worktree', 'add', '--quiet', '--detach', $worktreeB, 'HEAD'], base_path()))->mustRun();

            // Vendor collegato, mai copiato: stessa strategia già in uso
            // dagli altri test a sottoprocesso reale in questo file
            // gemello (vedi DeploymentSafetyTest).
            symlink(base_path('vendor'), $worktreeA.'/vendor');
            symlink(base_path('vendor'), $worktreeB.'/vendor');

            $this->provisionWorktreeEnv($worktreeA);
            $this->provisionWorktreeEnv($worktreeB);

            (new Process(['php', 'artisan', 'migrate', '--force', '--no-ansi'], $worktreeA))->mustRun();
            (new Process(['php', 'artisan', 'migrate', '--force', '--no-ansi'], $worktreeB))->mustRun();

            // La release A costruisce la propria config cache reale — i
            // valori storage_path()-based congelati qui riflettono il
            // percorso assoluto di $worktreeA.
            (new Process(['php', 'artisan', 'config:cache'], $worktreeA))->mustRun();

            $healthyInA = new Process(['php', 'artisan', 'deploy:verify-cache-paths', '--no-ansi'], $worktreeA);
            $healthyInA->run();
            $this->assertTrue($healthyInA->isSuccessful(), 'Expected the gate to pass in the release that actually cached its own config: '.$healthyInA->getOutput().$healthyInA->getErrorOutput());

            // Release B costruisce prima la propria cache corretta (cosi'
            // bootstrap/cache esiste), poi la stessa incidente reale
            // viene riprodotta: il file di A sovrascrive quello di B.
            (new Process(['php', 'artisan', 'config:cache'], $worktreeB))->mustRun();
            copy($worktreeA.'/bootstrap/cache/config.php', $worktreeB.'/bootstrap/cache/config.php');

            $brokenInB = new Process(['php', 'artisan', 'deploy:verify-cache-paths', '--no-ansi'], $worktreeB);
            $brokenInB->run();

            $this->assertFalse(
                $brokenInB->isSuccessful(),
                'A config cache copied in from a different release directory must fail the gate as a real, separate subprocess.'
            );
            $combinedOutput = $brokenInB->getOutput().$brokenInB->getErrorOutput();
            $this->assertStringContainsString('directory di release diversa', $combinedOutput);
            $this->assertStringContainsString($worktreeA, $combinedOutput, 'The reported stale value should still show the origin (worktree A) path.');

            // Il comportamento corretto di deploy.sh: optimize:clear +
            // config:cache rigenera SEMPRE la cache da zero prima di
            // questo gate — la stessa release B, ricachata davvero,
            // deve tornare a passare.
            (new Process(['php', 'artisan', 'optimize:clear'], $worktreeB))->mustRun();
            (new Process(['php', 'artisan', 'config:cache'], $worktreeB))->mustRun();

            $fixedInB = new Process(['php', 'artisan', 'deploy:verify-cache-paths', '--no-ansi'], $worktreeB);
            $fixedInB->run();
            $this->assertTrue(
                $fixedInB->isSuccessful(),
                'A genuine recache in this exact worktree must pass the gate again: '.$fixedInB->getOutput().$fixedInB->getErrorOutput()
            );
        } finally {
            (new Process(['git', 'worktree', 'remove', '--force', $worktreeA], base_path()))->run();
            (new Process(['git', 'worktree', 'remove', '--force', $worktreeB], base_path()))->run();
            File::deleteDirectory($worktreeA);
            File::deleteDirectory($worktreeB);
        }
    }

    private function provisionWorktreeEnv(string $worktree): void
    {
        $dbPath = $worktree.'/database/deploy_cache_path_test.sqlite';

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
}
