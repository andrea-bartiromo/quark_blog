<?php

namespace Tests\Unit\Deploy;

use Tests\TestCase;

/**
 * Missione 08 (secondo batch autonomo KAIRUS, Fase A — Test Reliability /
 * CI Foundation): audit del perimetro di default di
 * config('deploy.asset_drift_scan_paths').
 *
 * Verifica riportata: il perimetro di default gia' distingue correttamente
 * le quattro categorie richieste da questa missione —
 *
 *   - asset gestiti dalla release (css/, js/, assets/icons/, favicon.ico,
 *     apple-touch-icon.png, site.webmanifest, robots.txt): DENTRO;
 *   - upload utente / Libreria media (public/assets/img): FUORI — gia'
 *     sincronizzata a runtime da PublicMediaSyncService, un secondo
 *     meccanismo di confronto sarebbe ridondante e produrrebbe solo
 *     falsi positivi durante una finestra di sync legittima;
 *   - media generato/curato manualmente (public/images, decine di MB):
 *     FUORI — nessun cache-busting ?v= lo riguarda, e uno scan
 *     ricorsivo di un albero cosi' ampio sarebbe puro rumore;
 *   - `public/css` e `public/js` reali non contengono alcun artefatto di
 *     build/runtime generato (nessun .map, nessun file temporaneo — solo
 *     sorgenti autografati e tracciati da git), quindi lo scan di quelle
 *     due directory non produce falsi positivi da rumore interno.
 *
 * Nessun codice applicativo modificato da questa missione: questo test
 * blocca la decisione presa nella Missione 04 storica (PR #334) come
 * regressione — un futuro PR che aggiunga per errore 'assets/img' o
 * 'images' allo scan verrebbe qui bloccato immediatamente, prima di
 * arrivare a generare i falsi positivi che questa missione doveva
 * prevenire.
 */
class AssetDriftScanScopeTest extends TestCase
{
    public function test_the_default_scan_scope_is_exactly_the_curated_release_asset_list(): void
    {
        $this->assertSame([
            'css',
            'js',
            'assets/icons',
            'favicon.ico',
            'apple-touch-icon.png',
            'site.webmanifest',
            'robots.txt',
        ], config('deploy.asset_drift_scan_paths'));
    }

    public function test_the_default_scan_scope_never_includes_the_media_library_upload_root(): void
    {
        $this->assertNotContains('assets/img', config('deploy.asset_drift_scan_paths'));
    }

    public function test_the_default_scan_scope_never_includes_the_curated_editorial_image_tree(): void
    {
        $this->assertNotContains('images', config('deploy.asset_drift_scan_paths'));
    }

    public function test_public_css_and_js_contain_only_hand_authored_source_no_build_or_runtime_noise(): void
    {
        foreach (['css', 'js'] as $dir) {
            $absolute = public_path($dir);
            $this->assertDirectoryExists($absolute);

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $extension = strtolower($file->getExtension());
                $this->assertContains(
                    $extension,
                    ['css', 'js'],
                    "Unexpected non-source file in public/{$dir}: {$file->getPathname()} — would be scanned by the asset-drift gate as if it were release-managed."
                );
            }
        }
    }
}
