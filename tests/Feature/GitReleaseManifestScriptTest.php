<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Prompt 017 (150-prompt deploy-hardening program):
 * scripts/git-release-manifest.sh produces a deterministic A/M/D manifest
 * (in the exact TSV format scripts/selective-deploy-backup.sh consumes)
 * between two commits, with no implicit rename collapsing.
 */
class GitReleaseManifestScriptTest extends TestCase
{
    private static ?bool $bashAvailable = null;

    private string $repo;

    private string $script;

    /** @var list<string> */
    private array $extraPaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureBashAvailable();

        $this->repo = storage_path('framework/testing/git-release-manifest-'.bin2hex(random_bytes(6)));
        $this->script = base_path('scripts/git-release-manifest.sh');

        File::ensureDirectoryExists($this->repo);
        $this->git(['init', '--quiet', '--initial-branch=main']);
        $this->git(['config', 'user.email', 'test@example.test']);
        $this->git(['config', 'user.name', 'Test']);
    }

    protected function tearDown(): void
    {
        if (isset($this->repo)) {
            File::deleteDirectory($this->repo);
        }

        foreach ($this->extraPaths as $path) {
            if (is_dir($path)) {
                File::deleteDirectory($path);
            } elseif (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    private function ensureBashAvailable(): void
    {
        if (self::$bashAvailable === null) {
            self::$bashAvailable = $this->probeBashCapability();
        }

        if (! self::$bashAvailable) {
            $this->markTestSkipped('No functional Bash shell is available in this environment.');
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

    private function git(array $args): string
    {
        $process = new Process(['git', '-C', $this->repo, ...$args]);
        $process->mustRun();

        return trim($process->getOutput());
    }

    private function commit(string $message): string
    {
        $this->git(['add', '-A']);
        $this->git(['commit', '--quiet', '-m', $message]);

        return $this->git(['rev-parse', 'HEAD']);
    }

    private function runManifest(string $from, string $to): Process
    {
        $process = new Process([
            'bash', $this->script,
            '--from', $from,
            '--to', $to,
            '--repo', $this->repo,
        ]);
        $process->run();

        return $process;
    }

    public function test_generates_a_deterministic_manifest_for_added_modified_and_deleted_paths(): void
    {
        File::ensureDirectoryExists($this->repo.'/public/css');
        File::ensureDirectoryExists($this->repo.'/app');
        File::put($this->repo.'/app/Config.php', "old-config\n");
        File::put($this->repo.'/public/css/site.css', "old-css\n");
        $from = $this->commit('base');

        // Modified.
        File::put($this->repo.'/app/Config.php', "new-config\n");
        // Deleted.
        File::delete($this->repo.'/public/css/site.css');
        // Added.
        File::put($this->repo.'/public/css/new-feature.css', "new-feature\n");
        $to = $this->commit('release');

        $process = $this->runManifest($from, $to);

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $lines = array_values(array_filter(explode("\n", trim($process->getOutput()))));
        sort($lines);

        $this->assertSame([
            "app\tapp/Config.php",
            "public\tcss/new-feature.css",
            "public\tcss/site.css",
        ], $lines);
    }

    public function test_a_pure_rename_is_never_collapsed_and_surfaces_both_the_old_and_the_new_path(): void
    {
        File::ensureDirectoryExists($this->repo.'/app');
        File::put($this->repo.'/app/OldName.php', str_repeat("identical content line\n", 20));
        $from = $this->commit('base');

        $this->git(['mv', 'app/OldName.php', 'app/NewName.php']);
        $to = $this->commit('rename');

        $process = $this->runManifest($from, $to);

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $lines = array_values(array_filter(explode("\n", trim($process->getOutput()))));
        sort($lines);

        $this->assertSame([
            "app\tapp/NewName.php",
            "app\tapp/OldName.php",
        ], $lines, 'A rename must never be collapsed into a single line that silently drops the old path.');
    }

    public function test_a_type_change_is_refused_rather_than_guessed_at(): void
    {
        $this->ensureSymlinkSupported();

        File::ensureDirectoryExists($this->repo.'/app');
        File::put($this->repo.'/app/target.txt', "content\n");
        File::put($this->repo.'/app/link-or-file.php', "was a regular file\n");
        $from = $this->commit('base');

        File::delete($this->repo.'/app/link-or-file.php');
        symlink('target.txt', $this->repo.'/app/link-or-file.php');
        $to = $this->commit('type-change');

        $process = $this->runManifest($from, $to);

        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString('non-deterministic change class', $process->getErrorOutput());
    }

    public function test_identical_shas_are_rejected(): void
    {
        File::ensureDirectoryExists($this->repo.'/app');
        File::put($this->repo.'/app/one.php', "x\n");
        $sha = $this->commit('base');

        $process = $this->runManifest($sha, $sha);

        $this->assertFalse($process->isSuccessful());
    }

    public function test_unknown_shas_are_rejected(): void
    {
        File::ensureDirectoryExists($this->repo.'/app');
        File::put($this->repo.'/app/one.php', "x\n");
        $this->commit('base');

        $process = $this->runManifest(str_repeat('a', 40), str_repeat('b', 40));

        $this->assertFalse($process->isSuccessful());
    }

    public function test_no_changes_between_the_two_shas_produces_an_empty_manifest(): void
    {
        File::ensureDirectoryExists($this->repo.'/app');
        File::put($this->repo.'/app/one.php', "x\n");
        $from = $this->commit('base');

        // Same tree as $from, distinct commit (different metadata only) —
        // the manifest must reflect the tree diff, not merely "different SHA".
        $this->git(['commit', '--quiet', '--allow-empty', '-m', 'no-op, identical tree']);
        $to = $this->git(['rev-parse', 'HEAD']);

        $process = $this->runManifest($from, $to);

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertSame('', trim($process->getOutput()));
    }

    /**
     * Prompt 018 (150-prompt deploy-hardening program): the manifest this
     * script generates must actually compose with
     * scripts/selective-deploy-backup.sh's backup/rollback, not merely
     * look right in isolation. Simulates a real two-commit release: the
     * manifest is generated from the two SHAs, fed straight into a
     * backup, the "deploy" is applied (app root advances to the target
     * commit, the separately-served public root is synced by hand — the
     * same undocumented external step production relies on today), and a
     * rollback must restore both roots to the pre-release state using
     * only the generated manifest.
     */
    public function test_a_manifest_generated_between_two_commits_composes_with_backup_and_rollback(): void
    {
        $publicRoot = $this->repo.'-public-root';
        $backupRoot = $this->repo.'-backup-root';
        $manifestPath = $this->repo.'-manifest.tsv';
        $this->extraPaths = [$publicRoot, $backupRoot, $manifestPath];
        File::ensureDirectoryExists($publicRoot.'/css');
        File::ensureDirectoryExists($backupRoot);

        File::ensureDirectoryExists($this->repo.'/app');
        File::ensureDirectoryExists($this->repo.'/public/css');
        File::put($this->repo.'/app/Config.php', "old-config\n");
        File::put($this->repo.'/public/css/site.css', "old-css\n");
        File::put($publicRoot.'/css/site.css', "old-css\n");
        $from = $this->commit('base release');

        File::put($this->repo.'/app/Config.php', "new-config\n");
        File::put($this->repo.'/public/css/site.css', "new-css\n");
        File::put($this->repo.'/public/css/new-feature.css', "new-feature\n");
        $to = $this->commit('next release');

        $manifestProcess = $this->runManifest($from, $to);
        $this->assertTrue($manifestProcess->isSuccessful(), $manifestProcess->getErrorOutput());
        File::put($manifestPath, $manifestProcess->getOutput());

        // The backup must capture the pre-release state: put the working
        // tree back at $from (committing $to above already advanced it)
        // before snapshotting anything, exactly like a real deploy backs
        // up production BEFORE the new release lands.
        $this->git(['checkout', '--quiet', $from]);

        $backupScript = base_path('scripts/selective-deploy-backup.sh');
        $backupProcess = new Process([
            'bash', $backupScript, 'backup',
            '--manifest', $manifestPath,
            '--app-root', $this->repo,
            '--public-root', $publicRoot,
            '--backup-root', $backupRoot,
            '--previous-sha', $from,
            '--target-sha', $to,
        ]);
        $backupProcess->mustRun();
        $backupDir = trim($backupProcess->getOutput());

        // "Deploy": app root advances to the target commit (a real
        // checkout would do this); the separately-served public root is
        // synced by hand, exactly like the undocumented external step
        // production relies on today.
        $this->git(['checkout', '--quiet', $to]);
        File::put($publicRoot.'/css/site.css', "new-css\n");
        File::put($publicRoot.'/css/new-feature.css', "new-feature\n");

        $rollbackProcess = new Process([
            'bash', $backupScript, 'rollback',
            '--backup-dir', $backupDir,
            '--app-root', $this->repo,
            '--public-root', $publicRoot,
        ]);
        $rollbackProcess->mustRun();

        $this->assertSame("old-config\n", File::get($this->repo.'/app/Config.php'));
        $this->assertSame("old-css\n", File::get($publicRoot.'/css/site.css'));
        $this->assertFileDoesNotExist($publicRoot.'/css/new-feature.css', 'A file added by the release must be removed on rollback, not left behind.');
    }

    private function ensureSymlinkSupported(): void
    {
        $probeTarget = $this->repo.'/symlink-capability-probe-target';
        $probeLink = $this->repo.'/symlink-capability-probe-link';
        File::put($probeTarget, 'x');

        $supported = false;

        try {
            $supported = @symlink($probeTarget, $probeLink);
        } catch (\Throwable) {
            $supported = false;
        }

        if ($supported) {
            @unlink($probeLink);
        }
        @unlink($probeTarget);

        if (! $supported) {
            $this->markTestSkipped('symlink() is not permitted in this environment.');
        }
    }
}
