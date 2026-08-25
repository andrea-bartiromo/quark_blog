<?php

namespace Tests\Unit\Deploy;

use App\Services\Deploy\PublicAssetDriftDetector;
use Tests\TestCase;

/**
 * Mission 03/04 — Selective Deploy Public Asset Synchronization / Drift
 * Detector.
 *
 * Simula le due document root reali (l'albero applicativo, sempre
 * public_path() di questo checkout di test, e una radice "servita" isolata
 * in una directory temporanea) per provare che il detector rileva
 * esattamente le quattro classi di divergenza richieste dalla missione:
 * ok, mismatch, missing_on_webroot, missing_on_app — mai mutando alcun
 * file su nessuna delle due radici.
 */
class PublicAssetDriftDetectorTest extends TestCase
{
    private const MARKER = 'kairus-test-served-root-';

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

    private function detector(): PublicAssetDriftDetector
    {
        return app(PublicAssetDriftDetector::class);
    }

    public function test_report_is_disabled_when_no_served_root_is_configured(): void
    {
        config(['deploy.served_public_root' => null]);

        $report = $this->detector()->report();

        $this->assertFalse($report['enabled']);
        $this->assertTrue($this->detector()->isClean());
    }

    public function test_report_is_disabled_when_served_root_resolves_to_the_same_physical_directory(): void
    {
        config(['deploy.served_public_root' => public_path()]);

        $report = $this->detector()->report();

        $this->assertFalse($report['enabled']);
    }

    public function test_a_file_present_and_identical_on_both_roots_is_reported_ok(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probe = $this->probeFile();
        config(['deploy.asset_drift_scan_paths' => [$probe]]);

        file_put_contents(public_path($probe), 'body{color:red}');
        file_put_contents($servedRoot.'/'.$probe, 'body{color:red}');

        try {
            $report = $this->detector()->report();

            $this->assertTrue($report['enabled']);
            $entry = collect($report['entries'])->firstWhere('path', $probe);
            $this->assertSame(PublicAssetDriftDetector::STATUS_OK, $entry['status']);
            $this->assertSame($entry['app_hash'], $entry['served_hash']);
            $this->assertTrue($this->detector()->isClean());
        } finally {
            @unlink(public_path($probe));
        }
    }

    public function test_different_content_on_the_two_roots_is_reported_as_a_mismatch(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probe = $this->probeFile();
        config(['deploy.asset_drift_scan_paths' => [$probe]]);

        file_put_contents(public_path($probe), 'body{color:red}');
        file_put_contents($servedRoot.'/'.$probe, 'body{color:blue}');

        try {
            $report = $this->detector()->report();

            $entry = collect($report['entries'])->firstWhere('path', $probe);
            $this->assertSame(PublicAssetDriftDetector::STATUS_MISMATCH, $entry['status']);
            $this->assertNotSame($entry['app_hash'], $entry['served_hash']);
            $this->assertSame(1, $report['totals'][PublicAssetDriftDetector::STATUS_MISMATCH]);
            $this->assertFalse($this->detector()->isClean());
        } finally {
            @unlink(public_path($probe));
        }
    }

    public function test_a_file_present_on_the_app_root_but_missing_on_the_served_root_is_flagged(): void
    {
        $this->makeServedRoot();
        $probe = $this->probeFile();
        config(['deploy.asset_drift_scan_paths' => [$probe]]);

        file_put_contents(public_path($probe), 'body{color:red}');

        try {
            $report = $this->detector()->report();

            $entry = collect($report['entries'])->firstWhere('path', $probe);
            $this->assertSame(PublicAssetDriftDetector::STATUS_MISSING_ON_WEBROOT, $entry['status']);
            $this->assertNotNull($entry['app_hash']);
            $this->assertNull($entry['served_hash']);
            $this->assertFalse($this->detector()->isClean());
        } finally {
            @unlink(public_path($probe));
        }
    }

    public function test_a_file_present_on_the_served_root_but_missing_on_the_app_root_is_flagged(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probe = $this->probeFile();
        config(['deploy.asset_drift_scan_paths' => [$probe]]);

        file_put_contents($servedRoot.'/'.$probe, 'body{color:red}');

        $report = $this->detector()->report();

        $entry = collect($report['entries'])->firstWhere('path', $probe);
        $this->assertSame(PublicAssetDriftDetector::STATUS_MISSING_ON_APP, $entry['status']);
        $this->assertNull($entry['app_hash']);
        $this->assertNotNull($entry['served_hash']);
        $this->assertFalse($this->detector()->isClean());
    }

    public function test_a_directory_target_is_scanned_recursively_on_both_roots(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probeDir = $this->probeDir();
        config(['deploy.asset_drift_scan_paths' => [$probeDir]]);

        mkdir(public_path($probeDir.'/nested'), 0775, true);
        file_put_contents(public_path($probeDir.'/one.js'), 'console.log(1)');
        file_put_contents(public_path($probeDir.'/nested/two.js'), 'console.log(2)');
        mkdir($servedRoot.'/'.$probeDir, 0775, true);
        file_put_contents($servedRoot.'/'.$probeDir.'/one.js', 'console.log(1)');
        // nested/two.js deliberately absent on the served root.

        try {
            $report = $this->detector()->report();
            $byPath = collect($report['entries'])->keyBy('path');

            $this->assertSame(PublicAssetDriftDetector::STATUS_OK, $byPath[$probeDir.'/one.js']['status']);
            $this->assertSame(PublicAssetDriftDetector::STATUS_MISSING_ON_WEBROOT, $byPath[$probeDir.'/nested/two.js']['status']);
        } finally {
            $this->deleteRecursively(public_path($probeDir));
        }
    }

    public function test_the_report_never_writes_to_either_root(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probe = $this->probeFile();
        config(['deploy.asset_drift_scan_paths' => [$probe]]);

        file_put_contents(public_path($probe), 'original');

        try {
            $beforeAppHash = hash_file('sha256', public_path($probe));
            $this->detector()->report();
            $afterAppHash = hash_file('sha256', public_path($probe));

            $this->assertSame($beforeAppHash, $afterAppHash);
            $this->assertFalse(is_file($servedRoot.'/'.$probe), 'report() must never create a file on the served root it does not already find there.');
        } finally {
            @unlink(public_path($probe));
        }
    }

    /**
     * Nome file univoco per test: prima di questa missione tutti i metodi
     * di questa classe scrivevano lo stesso "probe.css" letterale nel vero
     * public_path() applicativo (necessario per design — vedi la docblock
     * di classe — non e' isolabile in una directory temporanea). Un nome
     * univoco elimina l'unico rischio residuo reale: un errore fatale che
     * interrompe un test a meta' non puo' piu' lasciare un file il cui nome
     * un test COMPLETAMENTE DIVERSO in questo stesso file si aspetta assente.
     */
    private function probeFile(): string
    {
        return 'probe-'.uniqid('', true).'.css';
    }

    private function probeDir(): string
    {
        return 'probe-dir-'.uniqid('', true);
    }
}
