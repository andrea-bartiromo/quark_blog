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

    public function test_a_file_with_matching_content_but_an_unsafe_mode_on_the_served_root_is_flagged(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probe = $this->probeFile();
        config(['deploy.asset_drift_scan_paths' => [$probe]]);

        file_put_contents(public_path($probe), 'body{color:red}');
        file_put_contents($servedRoot.'/'.$probe, 'body{color:red}');
        chmod($servedRoot.'/'.$probe, 0600);

        try {
            $report = $this->detector()->report();

            $entry = collect($report['entries'])->firstWhere('path', $probe);
            $this->assertSame(PublicAssetDriftDetector::STATUS_UNSAFE_MODE, $entry['status']);
            $this->assertSame($entry['app_hash'], $entry['served_hash'], 'Content still matches — only the mode is unsafe.');
            $this->assertSame(1, $report['totals'][PublicAssetDriftDetector::STATUS_UNSAFE_MODE]);
            $this->assertSame(0, $report['totals'][PublicAssetDriftDetector::STATUS_OK]);
            $this->assertFalse($this->detector()->isClean());
        } finally {
            @unlink(public_path($probe));
        }
    }

    public function test_a_file_with_matching_content_but_an_unsafe_mode_on_the_app_root_is_flagged(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probe = $this->probeFile();
        config(['deploy.asset_drift_scan_paths' => [$probe]]);

        file_put_contents(public_path($probe), 'body{color:red}');
        chmod(public_path($probe), 0640);
        file_put_contents($servedRoot.'/'.$probe, 'body{color:red}');

        try {
            $report = $this->detector()->report();

            $entry = collect($report['entries'])->firstWhere('path', $probe);
            $this->assertSame(PublicAssetDriftDetector::STATUS_UNSAFE_MODE, $entry['status']);
            $this->assertFalse($this->detector()->isClean());
        } finally {
            chmod(public_path($probe), 0644);
            @unlink(public_path($probe));
        }
    }

    public function test_a_file_with_a_safe_but_more_permissive_mode_stays_ok(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probe = $this->probeFile();
        config(['deploy.asset_drift_scan_paths' => [$probe]]);

        file_put_contents(public_path($probe), 'body{color:red}');
        file_put_contents($servedRoot.'/'.$probe, 'body{color:red}');
        chmod($servedRoot.'/'.$probe, 0664);

        try {
            $report = $this->detector()->report();

            $entry = collect($report['entries'])->firstWhere('path', $probe);
            $this->assertSame(PublicAssetDriftDetector::STATUS_OK, $entry['status']);
            $this->assertTrue($this->detector()->isClean());
        } finally {
            @unlink(public_path($probe));
        }
    }

    public function test_a_mismatch_is_reported_as_mismatch_even_when_its_mode_is_also_unsafe(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probe = $this->probeFile();
        config(['deploy.asset_drift_scan_paths' => [$probe]]);

        file_put_contents(public_path($probe), 'body{color:red}');
        file_put_contents($servedRoot.'/'.$probe, 'body{color:blue}');
        chmod($servedRoot.'/'.$probe, 0600);

        try {
            $report = $this->detector()->report();

            $entry = collect($report['entries'])->firstWhere('path', $probe);
            $this->assertSame(
                PublicAssetDriftDetector::STATUS_MISMATCH,
                $entry['status'],
                'A real content mismatch must never be masked by an also-unsafe mode.'
            );
        } finally {
            @unlink(public_path($probe));
        }
    }

    public function test_a_scanned_directory_with_an_unsafe_mode_is_flagged_as_a_synthetic_entry(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probeDir = $this->probeDir();
        config(['deploy.asset_drift_scan_paths' => [$probeDir]]);

        mkdir(public_path($probeDir), 0775, true);
        file_put_contents(public_path($probeDir.'/one.js'), 'console.log(1)');
        mkdir($servedRoot.'/'.$probeDir, 0700, true);
        file_put_contents($servedRoot.'/'.$probeDir.'/one.js', 'console.log(1)');

        try {
            $report = $this->detector()->report();
            $byPath = collect($report['entries'])->keyBy('path');

            $this->assertSame(PublicAssetDriftDetector::STATUS_UNSAFE_MODE, $byPath[$probeDir]['status']);
            $this->assertNull($byPath[$probeDir]['app_hash']);
            $this->assertSame(PublicAssetDriftDetector::STATUS_OK, $byPath[$probeDir.'/one.js']['status'], 'The file itself is still readable and content-identical — only the directory entry is flagged.');
            $this->assertFalse($this->detector()->isClean());
        } finally {
            chmod($servedRoot.'/'.$probeDir, 0775);
            $this->deleteRecursively(public_path($probeDir));
        }
    }

    /**
     * Revisione Codex su PR #535: la directory nominata direttamente in
     * asset_drift_scan_paths puo' avere un permesso sicuro mentre una sua
     * SOTTOdirectory (mai nominata esplicitamente in config) non lo ha. Il
     * processo che esegue la scansione (proprietario dei file, come un
     * deploy reale) puo' comunque attraversarla e trovare/confrontare
     * "one.js" al suo interno con hash identico su entrambe le radici —
     * senza questo test, quel file risulterebbe "ok" e il gate passerebbe
     * anche se Apache (altro utente) non puo' raggiungere la
     * sottodirectory. Il gate deve rilevarla come sottodirectory,
     * non solo come directory nominata direttamente.
     */
    public function test_a_nested_subdirectory_with_an_unsafe_mode_is_flagged_even_when_the_named_scan_directory_itself_is_safe(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probeDir = $this->probeDir();
        $nested = $probeDir.'/theme';
        config(['deploy.asset_drift_scan_paths' => [$probeDir]]);

        mkdir(public_path($nested), 0775, true);
        file_put_contents(public_path($nested.'/one.js'), 'console.log(1)');
        mkdir($servedRoot.'/'.$nested, 0700, true);
        file_put_contents($servedRoot.'/'.$nested.'/one.js', 'console.log(1)');

        try {
            $report = $this->detector()->report();
            $byPath = collect($report['entries'])->keyBy('path');

            $this->assertSame(
                PublicAssetDriftDetector::STATUS_UNSAFE_MODE,
                $byPath[$nested]['status'],
                'A nested directory with an unsafe mode must be flagged even though it is never named directly in asset_drift_scan_paths.'
            );
            $this->assertSame(
                PublicAssetDriftDetector::STATUS_OK,
                $byPath[$nested.'/one.js']['status'],
                'The file itself is still readable and content-identical to the scanning process — only the directory entry is flagged.'
            );
            $this->assertFalse($this->detector()->isClean());
        } finally {
            chmod($servedRoot.'/'.$nested, 0775);
            $this->deleteRecursively(public_path($probeDir));
        }
    }

    public function test_a_scanned_directory_with_a_safe_mode_is_not_reported_at_all(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probeDir = $this->probeDir();
        config(['deploy.asset_drift_scan_paths' => [$probeDir]]);

        mkdir(public_path($probeDir), 0775, true);
        file_put_contents(public_path($probeDir.'/one.js'), 'console.log(1)');
        mkdir($servedRoot.'/'.$probeDir, 0775, true);
        file_put_contents($servedRoot.'/'.$probeDir.'/one.js', 'console.log(1)');

        try {
            $report = $this->detector()->report();
            $byPath = collect($report['entries'])->keyBy('path');

            $this->assertArrayNotHasKey($probeDir, $byPath, 'A safely-permissioned directory must not itself appear as an entry.');
            $this->assertSame(0, $report['totals'][PublicAssetDriftDetector::STATUS_UNSAFE_MODE]);
            $this->assertTrue($this->detector()->isClean());
        } finally {
            $this->deleteRecursively(public_path($probeDir));
        }
    }

    public function test_a_file_empty_on_both_roots_is_flagged_as_empty_rather_than_ok(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probe = $this->probeFile();
        config(['deploy.asset_drift_scan_paths' => [$probe]]);

        file_put_contents(public_path($probe), '');
        file_put_contents($servedRoot.'/'.$probe, '');

        try {
            $report = $this->detector()->report();

            $entry = collect($report['entries'])->firstWhere('path', $probe);
            $this->assertSame(PublicAssetDriftDetector::STATUS_EMPTY_FILE, $entry['status']);
            $this->assertSame(1, $report['totals'][PublicAssetDriftDetector::STATUS_EMPTY_FILE]);
            $this->assertSame(0, $report['totals'][PublicAssetDriftDetector::STATUS_OK]);
            $this->assertFalse($this->detector()->isClean());
        } finally {
            @unlink(public_path($probe));
        }
    }

    public function test_a_file_empty_on_only_one_root_is_reported_as_a_mismatch_not_empty(): void
    {
        $servedRoot = $this->makeServedRoot();
        $probe = $this->probeFile();
        config(['deploy.asset_drift_scan_paths' => [$probe]]);

        file_put_contents(public_path($probe), 'body{color:red}');
        file_put_contents($servedRoot.'/'.$probe, '');

        try {
            $report = $this->detector()->report();

            $entry = collect($report['entries'])->firstWhere('path', $probe);
            $this->assertSame(
                PublicAssetDriftDetector::STATUS_MISMATCH,
                $entry['status'],
                'Different content (one side truncated to empty, the other not) is a mismatch, not the "empty on both sides" case.'
            );
        } finally {
            @unlink(public_path($probe));
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
