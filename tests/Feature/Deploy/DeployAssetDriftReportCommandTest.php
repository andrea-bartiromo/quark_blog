<?php

namespace Tests\Feature\Deploy;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Mission 05 — Deployment Release Gate for Public Assets.
 * `deploy:asset-drift` is the exit-code contract deploy.sh relies on: 0
 * when disabled or clean, non-zero the moment a real mismatch exists.
 */
class DeployAssetDriftReportCommandTest extends TestCase
{
    private const MARKER = 'kairus-test-cmd-served-root-';

    private ?string $servedRoot = null;

    protected function tearDown(): void
    {
        if ($this->servedRoot !== null && str_contains($this->servedRoot, self::MARKER)) {
            $this->deleteRecursively($this->servedRoot);
        }

        parent::tearDown();
    }

    private function makeServedRoot(): string
    {
        $this->servedRoot = sys_get_temp_dir().'/'.self::MARKER.uniqid('', true);
        mkdir($this->servedRoot, 0775, true);
        config(['deploy.served_public_root' => $this->servedRoot]);

        return $this->servedRoot;
    }

    private function deleteRecursively(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.'/'.$item;

            if (is_dir($path) && ! is_link($path)) {
                $this->deleteRecursively($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    public function test_the_command_exits_successfully_when_no_served_root_is_configured(): void
    {
        config(['deploy.served_public_root' => null]);

        $exitCode = Artisan::call('deploy:asset-drift');

        $this->assertSame(0, $exitCode);
    }

    public function test_the_command_exits_successfully_when_configured_and_clean(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probe = $this->probeFile();
        config(['deploy.asset_drift_scan_paths' => [$probe]]);

        file_put_contents(public_path($probe), 'body{color:red}');
        file_put_contents($servedRoot.'/'.$probe, 'body{color:red}');

        try {
            $exitCode = Artisan::call('deploy:asset-drift');
            $this->assertSame(0, $exitCode);
        } finally {
            @unlink(public_path($probe));
        }
    }

    public function test_the_command_fails_when_a_real_mismatch_exists(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probe = $this->probeFile();
        config(['deploy.asset_drift_scan_paths' => [$probe]]);

        file_put_contents(public_path($probe), 'body{color:red}');
        file_put_contents($servedRoot.'/'.$probe, 'body{color:blue}');

        try {
            $exitCode = Artisan::call('deploy:asset-drift');
            $this->assertNotSame(0, $exitCode);
        } finally {
            @unlink(public_path($probe));
        }
    }

    public function test_the_command_fails_when_a_release_file_never_reached_the_served_root(): void
    {
        $this->makeServedRoot();
        $probe = $this->probeFile();
        config(['deploy.asset_drift_scan_paths' => [$probe]]);

        file_put_contents(public_path($probe), 'body{color:red}');

        try {
            $exitCode = Artisan::call('deploy:asset-drift');
            $this->assertNotSame(0, $exitCode);
        } finally {
            @unlink(public_path($probe));
        }
    }

    /**
     * Vedi PublicAssetDriftDetectorTest::probeFile() — stesso motivo: un
     * nome univoco per test elimina l'unico rischio residuo di collisione
     * su questo file scritto per design nel vero public_path() applicativo.
     */
    private function probeFile(): string
    {
        return 'probe-'.uniqid('', true).'.css';
    }
}
