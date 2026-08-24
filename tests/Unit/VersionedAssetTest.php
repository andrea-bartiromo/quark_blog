<?php

namespace Tests\Unit;

use App\Support\VersionedAsset;
use Tests\TestCase;

/**
 * Produzione Kairus serve i file statici da due document root fisicamente
 * separate (~/kairus_app/public, da cui public_path() legge, e
 * ~/public_html, effettivamente servita da Apache) senza alcuna
 * sincronizzazione automatica per CSS/JS — a differenza della Libreria
 * media (PublicMediaSyncService). Un incidente reale
 * (public-premium.css, notte del 24/08) ha dimostrato che una versione
 * calcolata da filemtime(public_path()) può riflettere una copia stale
 * mai davvero servita al browser. Il file REVISION (scritto da deploy.sh
 * solo dopo un deploy riuscito, contenente lo SHA Git della release) è
 * ora la fonte di verità preferita quando presente — identica
 * indipendentemente da quale albero la legge, e sempre diversa ad ogni
 * nuovo deploy.
 */
class VersionedAssetTest extends TestCase
{
    private ?string $revisionPath = null;

    protected function tearDown(): void
    {
        if ($this->revisionPath !== null && is_file($this->revisionPath)) {
            unlink($this->revisionPath);
        }

        parent::tearDown();
    }

    private function writeRevisionFile(string $contents): void
    {
        $this->revisionPath = base_path('REVISION');
        file_put_contents($this->revisionPath, $contents);
    }

    public function test_an_existing_file_is_versioned_with_its_real_mtime(): void
    {
        $url = VersionedAsset::url('css/style.css');

        $expectedVersion = filemtime(public_path('css/style.css'));

        $this->assertStringEndsWith('?v='.$expectedVersion, $url);
        $this->assertStringContainsString('/css/style.css', $url);
    }

    public function test_two_calls_for_the_same_untouched_file_produce_the_same_version(): void
    {
        $first = VersionedAsset::url('css/style.css');
        $second = VersionedAsset::url('css/style.css');

        $this->assertSame($first, $second);
    }

    public function test_a_missing_file_falls_back_to_a_fixed_version_instead_of_erroring(): void
    {
        $url = VersionedAsset::url('css/this-file-does-not-exist-anywhere.css');

        $this->assertStringEndsWith('?v=1', $url);
    }

    public function test_a_revision_file_takes_priority_over_the_files_own_mtime(): void
    {
        $this->writeRevisionFile("a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2\n");

        $url = VersionedAsset::url('css/style.css');

        $this->assertStringEndsWith('?v=a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2', $url);
    }

    /**
     * La proprietà chiave del fix: quando esiste una REVISION, due asset
     * DIVERSI (mtime diversi sul filesystem) condividono la stessa
     * versione — la versione dipende dalla release, non dal singolo file
     * o dall'albero che lo legge.
     */
    public function test_a_revision_file_produces_the_same_version_for_every_asset_regardless_of_its_own_mtime(): void
    {
        $this->writeRevisionFile('deadbeefdeadbeefdeadbeefdeadbeefdeadbeef');

        $first = VersionedAsset::url('css/style.css');
        $second = VersionedAsset::url('css/admin.css');
        $missing = VersionedAsset::url('css/this-file-does-not-exist-anywhere.css');

        $this->assertStringEndsWith('?v=deadbeefdeadbeefdeadbeefdeadbeefdeadbeef', $first);
        $this->assertStringEndsWith('?v=deadbeefdeadbeefdeadbeefdeadbeefdeadbeef', $second);
        $this->assertStringEndsWith('?v=deadbeefdeadbeefdeadbeefdeadbeefdeadbeef', $missing);
    }

    public function test_an_empty_revision_file_falls_back_to_mtime_instead_of_producing_a_blank_version(): void
    {
        $this->writeRevisionFile('');

        $url = VersionedAsset::url('css/style.css');

        $expectedVersion = filemtime(public_path('css/style.css'));
        $this->assertStringEndsWith('?v='.$expectedVersion, $url);
    }

    public function test_a_whitespace_only_revision_file_falls_back_to_mtime(): void
    {
        $this->writeRevisionFile("   \n\t \n");

        $url = VersionedAsset::url('css/style.css');

        $expectedVersion = filemtime(public_path('css/style.css'));
        $this->assertStringEndsWith('?v='.$expectedVersion, $url);
    }

    /**
     * Un contenuto corrotto contenente un newline interno non deve mai
     * produrre una querystring rotta (spazi/newline letterali nell'URL
     * generato) — meglio ricadere sul mtime che propagare un valore
     * malformato.
     */
    public function test_a_multiline_revision_file_falls_back_to_mtime_instead_of_producing_a_broken_url(): void
    {
        $this->writeRevisionFile("abc123\nsecond line\n");

        $url = VersionedAsset::url('css/style.css');

        $expectedVersion = filemtime(public_path('css/style.css'));
        $this->assertStringEndsWith('?v='.$expectedVersion, $url);
    }
}
