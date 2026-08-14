<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SelectiveDeployBackupScriptTest extends TestCase
{
    private string $root;
    private string $appRoot;
    private string $publicRoot;
    private string $backupRoot;
    private string $script;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/selective-deploy-backup-'.bin2hex(random_bytes(6)));
        $this->appRoot = $this->root.'/app';
        $this->publicRoot = $this->root.'/public';
        $this->backupRoot = $this->root.'/backups';
        $this->script = base_path('scripts/selective-deploy-backup.sh');

        File::ensureDirectoryExists($this->appRoot);
        File::ensureDirectoryExists($this->publicRoot);
        File::ensureDirectoryExists($this->backupRoot);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
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

        $this->assertFileExists($backup.'/files/app/a/one.php');
        $this->assertFileExists($backup.'/files/public/css/site.css');
        $this->assertStringContainsString("app\tb/new.php", File::get($backup.'/new-files.tsv'));
        $this->assertStringContainsString("app\ta/one.php", File::get($backup.'/backed-up-files.tsv'));
        $this->assertSame('640', substr(sprintf('%o', fileperms($backup.'/files/app/a/one.php')), -3));

        File::put($this->appRoot.'/a/one.php', "new-a\n");
        File::ensureDirectoryExists($this->appRoot.'/b');
        File::put($this->appRoot.'/b/new.php', "new-file\n");
        File::put($this->publicRoot.'/css/site.css', "new-css\n");

        $process = new Process(['bash', $this->script, 'rollback', '--backup-dir', $backup, '--app-root', $this->appRoot, '--public-root', $this->publicRoot]);
        $process->mustRun();

        $this->assertSame("old-a\n", File::get($this->appRoot.'/a/one.php'));
        $this->assertSame("old-css\n", File::get($this->publicRoot.'/css/site.css'));
        $this->assertFileDoesNotExist($this->appRoot.'/b/new.php');
        $this->assertSame('640', substr(sprintf('%o', fileperms($this->appRoot.'/a/one.php')), -3));
    }

    public function test_invalid_or_forbidden_manifest_fails_closed(): void
    {
        foreach (["app\t../escape\n", "app\tvendor/autoload.php\n", "app\ta.php\textra\n"] as $content) {
            $manifest = $this->root.'/bad-'.bin2hex(random_bytes(3)).'.tsv';
            File::put($manifest, $content);
            $process = $this->backupProcess($manifest);
            $process->run();
            $this->assertFalse($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());
        }
    }

    public function test_pr194_like_manifest_does_not_capture_unlisted_vendor_storage_or_public_media(): void
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
            File::ensureDirectoryExists(dirname($root.'/'.$relative));
            File::put($root.'/'.$relative, "x\n");
            $manifestLines[] = $scope."\t".$relative;
        }

        File::ensureDirectoryExists($this->appRoot.'/vendor/large');
        File::ensureDirectoryExists($this->appRoot.'/storage/runtime');
        File::ensureDirectoryExists($this->publicRoot.'/assets/img/media');
        File::put($this->appRoot.'/vendor/large/blob', str_repeat('v', 256 * 1024));
        File::put($this->publicRoot.'/assets/img/media/blob', str_repeat('m', 256 * 1024));

        $manifest = $this->root.'/pr194.tsv';
        File::put($manifest, implode("\n", $manifestLines)."\n");
        $backup = $this->runBackup($manifest);

        $backedUp = array_values(array_filter(explode("\n", trim(File::get($backup.'/backed-up-files.tsv')))));
        $this->assertCount(15, $backedUp);
        $this->assertDirectoryDoesNotExist($backup.'/files/app/vendor');
        $this->assertDirectoryDoesNotExist($backup.'/files/app/storage');
        $this->assertDirectoryDoesNotExist($backup.'/files/public/assets');
        $this->assertLessThan(128 * 1024, $this->directorySize($backup));
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

    private function directorySize(string $directory): int
    {
        $size = 0;
        foreach (File::allFiles($directory) as $file) {
            $size += $file->getSize();
        }

        return $size;
    }
}
