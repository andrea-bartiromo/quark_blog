<?php

namespace Tests\Feature\Assets;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Kairus Prompt 267: ogni asset CSS/JS locale referenziato via
 * VersionedAsset::url() da una vista Blade deve esistere davvero sotto
 * public/ nel repository — VersionedAsset::url() stessa non fallisce mai
 * su un file mancante (ricade su "?v=1", vedi VersionedAssetTest), quindi
 * un riferimento morto non produce un errore applicativo: produce un 404
 * silenzioso sul sito reale, rilevabile solo confrontando ogni
 * riferimento con il filesystem del repository, come fa questo test.
 *
 * Scansiona TUTTE le viste Blade (non solo i layout principali) per non
 * dipendere da un elenco mantenuto a mano che si disallineerebbe nel
 * tempo.
 */
class PublicLayoutAssetsExistTest extends TestCase
{
    public function test_every_versioned_asset_referenced_by_a_blade_view_exists_under_public(): void
    {
        $references = [];

        $finder = (new Finder)
            ->files()
            ->in(resource_path('views'))
            ->name('*.blade.php');

        foreach ($finder as $file) {
            $contents = $file->getContents();

            if (preg_match_all("/VersionedAsset::url\('([^']+)'\)/", $contents, $matches)) {
                foreach ($matches[1] as $relativePath) {
                    $references[$relativePath][] = $file->getRelativePathname();
                }
            }
        }

        $this->assertNotEmpty($references, 'Nessun riferimento VersionedAsset::url() trovato: il test non starebbe verificando nulla.');

        $missing = [];

        foreach ($references as $relativePath => $viewsUsingIt) {
            if (! is_file(public_path($relativePath))) {
                $missing[] = "$relativePath (referenziato da: ".implode(', ', array_unique($viewsUsingIt)).')';
            }
        }

        $this->assertSame(
            [],
            $missing,
            "Asset referenziati via VersionedAsset::url() ma assenti da public/:\n".implode("\n", $missing)
        );
    }
}
