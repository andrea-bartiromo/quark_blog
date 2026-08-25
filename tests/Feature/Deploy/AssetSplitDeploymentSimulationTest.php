<?php

namespace Tests\Feature\Deploy;

use App\Services\Deploy\PublicAssetDriftDetector;
use App\Support\VersionedAsset;
use Tests\TestCase;

/**
 * Missione 07 (secondo batch autonomo KAIRUS, Fase B — Deployment
 * Reliability): ri-certifica il fix del deploy a due alberi
 * (VersionedAsset + PublicAssetDriftDetector, gia' testati singolarmente
 * e in scenari a singolo file/tipo) sotto una release REALISTICA che
 * cambia piu' tipi di asset statici insieme — CSS applicativo modificato,
 * nuovo CSS, JS invariato, immagine statica nuova — cosi' come accadrebbe
 * davvero in un singolo deploy, non un tipo di file alla volta.
 *
 * Non duplica VersionedAssetTest (versioning in isolamento) ne'
 * PublicAssetDriftDetectorTest (drift in isolamento, un file/stato alla
 * volta): prova che i due meccanismi compongono correttamente quando
 * QUELLA composizione e' esattamente cio' che descrive la docblock di
 * VersionedAsset — il versioning (churn del browser) e il rilevamento
 * drift (contenuto realmente sincronizzato) sono deliberatamente
 * indipendenti, e nessuno dei due deve mai mascherare l'altro.
 */
class AssetSplitDeploymentSimulationTest extends TestCase
{
    private const MARKER = 'kairus-test-m07-served-root-';

    private ?string $servedRoot = null;

    private ?string $revisionPath = null;

    /** @var string[] */
    private array $appFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->appFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        if ($this->servedRoot !== null && str_contains($this->servedRoot, self::MARKER)) {
            $this->deleteRecursively($this->servedRoot);
        }

        if ($this->revisionPath !== null && is_file($this->revisionPath)) {
            unlink($this->revisionPath);
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

    private function writeRevisionFile(string $contents): void
    {
        $this->revisionPath = base_path('REVISION');
        file_put_contents($this->revisionPath, $contents);
    }

    private function writeAppFile(string $relative, string $contents): void
    {
        $path = public_path($relative);
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, $contents);
        $this->appFiles[] = $path;
    }

    private function writeServedFile(string $relative, string $contents): void
    {
        $path = $this->servedRoot.'/'.$relative;
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, $contents);
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

    public function test_a_mixed_release_with_css_js_and_image_changes_is_classified_correctly_in_one_scan(): void
    {
        $servedRoot = $this->makeServedRoot();
        $release = 'release-sim-'.uniqid('', true);

        // CSS applicativo esistente, MODIFICATO da questa release ma non
        // ancora arrivato sulla radice servita: il caso reale dell'incidente
        // public-premium.css.
        $this->writeAppFile("$release/css/app-style.css", 'body{color:blue} /* v2 */');
        $this->writeServedFile("$release/css/app-style.css", 'body{color:blue} /* v1 */');

        // Nuovo CSS introdotto da questa release: non esiste ancora da
        // nessuna parte tranne l'albero applicativo appena aggiornato.
        $this->writeAppFile("$release/css/new-feature.css", '.new-badge{display:block}');

        // JS invariato, gia' sincronizzato correttamente su entrambe le
        // radici: deve restare OK e non generare rumore nel report.
        $this->writeAppFile("$release/js/app.js", 'console.log("kairus");');
        $this->writeServedFile("$release/js/app.js", 'console.log("kairus");');

        // Nuova immagine statica (non Libreria media, es. un'icona di
        // sistema versionata nel repository): stesso trattamento generico
        // del CSS/JS da parte del detector, nessuna logica speciale per
        // tipo di file.
        $this->writeAppFile("$release/img/new-icon.png", "\x89PNG\r\n\x1a\nfake-binary-payload");

        config(['deploy.asset_drift_scan_paths' => [$release]]);

        $report = app(PublicAssetDriftDetector::class)->report();

        $this->assertTrue($report['enabled']);
        $this->assertSame([
            'scanned' => 4,
            PublicAssetDriftDetector::STATUS_OK => 1,
            PublicAssetDriftDetector::STATUS_MISMATCH => 1,
            PublicAssetDriftDetector::STATUS_MISSING_ON_WEBROOT => 2,
            PublicAssetDriftDetector::STATUS_MISSING_ON_APP => 0,
        ], $report['totals']);

        $byPath = collect($report['entries'])->keyBy('path');
        $this->assertSame(PublicAssetDriftDetector::STATUS_MISMATCH, $byPath["$release/css/app-style.css"]['status']);
        $this->assertSame(PublicAssetDriftDetector::STATUS_MISSING_ON_WEBROOT, $byPath["$release/css/new-feature.css"]['status']);
        $this->assertSame(PublicAssetDriftDetector::STATUS_OK, $byPath["$release/js/app.js"]['status']);
        $this->assertSame(PublicAssetDriftDetector::STATUS_MISSING_ON_WEBROOT, $byPath["$release/img/new-icon.png"]['status']);

        // Una release cosi' deve fallire il release gate: c'e' contenuto
        // realmente non sincronizzato, indipendentemente dal fatto che sia
        // CSS, JS o un'immagine.
        $this->assertFalse(app(PublicAssetDriftDetector::class)->isClean());
    }

    /**
     * Il punto architetturale chiave (docblock di VersionedAsset): il
     * versioning per il cache-busting del browser e il rilevamento del
     * drift di contenuto sono deliberatamente indipendenti. Con una
     * REVISION valida, TUTTI gli asset della release condividono la stessa
     * querystring di versione — CSS modificato, CSS nuovo, JS invariato,
     * immagine nuova — anche quando il drift detector, nello stesso
     * identico scenario sopra, li classifica in modo molto diverso
     * (ok/mismatch/missing). Il primo meccanismo non deve mai nascondere
     * ne' essere condizionato dal secondo.
     */
    public function test_versioned_asset_uses_the_same_release_token_for_every_changed_asset_type(): void
    {
        $this->writeRevisionFile('cafebabecafebabecafebabecafebabecafebabe');

        $this->writeAppFile('mission07/css/app-style.css', 'body{color:blue}');
        $this->writeAppFile('mission07/css/new-feature.css', '.new-badge{display:block}');
        $this->writeAppFile('mission07/js/app.js', 'console.log("kairus");');
        $this->writeAppFile('mission07/img/new-icon.png', "\x89PNG\r\n\x1a\nfake-binary-payload");

        foreach ([
            'mission07/css/app-style.css',
            'mission07/css/new-feature.css',
            'mission07/js/app.js',
            'mission07/img/new-icon.png',
        ] as $relative) {
            $this->assertStringEndsWith(
                '?v=cafebabecafebabecafebabecafebabecafebabe',
                VersionedAsset::url($relative),
                "Expected the release token as version for {$relative}"
            );
        }
    }
}
