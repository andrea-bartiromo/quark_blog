<?php

namespace Tests\Feature;

use App\Services\ResponsiveImageVariantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTestImages;
use Tests\Concerns\UsesIsolatedMediaPublicRoot;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

/**
 * FASE 5/9 (missione S2 responsive images): copre ResponsiveImageVariantService
 * in isolamento — generazione, cancellazione (lifecycle) e risoluzione per
 * il markup, inclusa la sincronizzazione verso la radice pubblica
 * secondaria quando configurata (stesso principio gia' testato per
 * PublicMediaSyncService, qui applicato alle varianti).
 */
class ResponsiveImageVariantServiceTest extends TestCase
{
    use InteractsWithTestImages;
    use RefreshDatabase;
    use UsesIsolatedMediaPublicRoot;
    use UsesIsolatedPublicPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedPublicPath();
        config(['media.responsive_widths' => [480, 960]]);
    }

    protected function tearDown(): void
    {
        $this->tearDownIsolatedMediaPublicRoot();
        $this->tearDownIsolatedPublicPath();
        $this->tearDownTestImages();
        parent::tearDown();
    }

    private function service(): ResponsiveImageVariantService
    {
        return app(ResponsiveImageVariantService::class);
    }

    private function placeUploadedFileAt(string $diskName, int $width, int $height): string
    {
        $file = $this->makeSolidImageUpload(basename($diskName), $width, $height);
        $target = public_path('assets/img/'.$diskName);
        @mkdir(dirname($target), 0775, true);

        // Riproduce l'output reale dell'upload (WebP gia' scritto dalla
        // pipeline esistente): qui basta un file leggibile da GD nello
        // stesso formato del disk_name.
        rename($file->getPathname(), $target);

        return $target;
    }

    public function test_generate_for_upload_writes_variants_smaller_than_the_source(): void
    {
        $absolutePath = $this->placeUploadedFileAt('articles/covers/foto.webp', 2000, 1000);

        $generated = $this->service()->generateForUpload($absolutePath, 'articles/covers/foto.webp');

        $this->assertSame([480, 960], array_column($generated, 'width'));
        $this->assertFileExists(public_path('assets/img/articles/covers/foto-480w.webp'));
        $this->assertFileExists(public_path('assets/img/articles/covers/foto-960w.webp'));
    }

    public function test_generate_for_upload_syncs_variants_to_the_secondary_public_root(): void
    {
        $this->setUpIsolatedMediaPublicRoot();

        $absolutePath = $this->placeUploadedFileAt('articles/covers/synced.webp', 2000, 1000);

        $this->service()->generateForUpload($absolutePath, 'articles/covers/synced.webp');

        $this->assertFileExists($this->isolatedMediaPublicRoot.'/articles/covers/synced-480w.webp');
        $this->assertFileExists($this->isolatedMediaPublicRoot.'/articles/covers/synced-960w.webp');
    }

    public function test_generate_for_upload_respects_configured_widths(): void
    {
        config(['media.responsive_widths' => [300]]);

        $absolutePath = $this->placeUploadedFileAt('articles/covers/one-size.webp', 2000, 1000);

        $generated = $this->service()->generateForUpload($absolutePath, 'articles/covers/one-size.webp');

        $this->assertSame([300], array_column($generated, 'width'));
    }

    public function test_generate_for_upload_returns_empty_when_no_widths_configured(): void
    {
        config(['media.responsive_widths' => []]);

        $absolutePath = $this->placeUploadedFileAt('articles/covers/none.webp', 2000, 1000);

        $generated = $this->service()->generateForUpload($absolutePath, 'articles/covers/none.webp');

        $this->assertSame([], $generated);
    }

    public function test_delete_for_disk_name_removes_all_generated_variants(): void
    {
        $absolutePath = $this->placeUploadedFileAt('articles/covers/to-delete.webp', 2000, 1000);
        $this->service()->generateForUpload($absolutePath, 'articles/covers/to-delete.webp');

        $this->assertFileExists(public_path('assets/img/articles/covers/to-delete-480w.webp'));
        $this->assertFileExists(public_path('assets/img/articles/covers/to-delete-960w.webp'));

        $this->service()->deleteForDiskName('articles/covers/to-delete.webp');

        $this->assertFileDoesNotExist(public_path('assets/img/articles/covers/to-delete-480w.webp'));
        $this->assertFileDoesNotExist(public_path('assets/img/articles/covers/to-delete-960w.webp'));

        // L'originale non e' compito di questo servizio: resta intatto.
        $this->assertFileExists(public_path('assets/img/articles/covers/to-delete.webp'));
    }

    public function test_delete_for_disk_name_also_removes_variants_from_the_secondary_public_root(): void
    {
        $this->setUpIsolatedMediaPublicRoot();

        $absolutePath = $this->placeUploadedFileAt('articles/covers/sync-delete.webp', 2000, 1000);
        $this->service()->generateForUpload($absolutePath, 'articles/covers/sync-delete.webp');

        $this->assertFileExists($this->isolatedMediaPublicRoot.'/articles/covers/sync-delete-480w.webp');

        $this->service()->deleteForDiskName('articles/covers/sync-delete.webp');

        $this->assertFileDoesNotExist($this->isolatedMediaPublicRoot.'/articles/covers/sync-delete-480w.webp');
    }

    public function test_delete_for_disk_name_is_idempotent_when_no_variants_exist(): void
    {
        $this->placeUploadedFileAt('articles/covers/never-had-variants.webp', 200, 100);

        // Nessuna variante generata (200 < 480, mai upscale): la
        // cancellazione non deve fallire ne' lanciare eccezioni.
        $this->service()->deleteForDiskName('articles/covers/never-had-variants.webp');

        $this->assertFileExists(public_path('assets/img/articles/covers/never-had-variants.webp'));
    }

    public function test_delete_for_disk_name_never_touches_a_similarly_named_but_distinct_media(): void
    {
        // "foto-2.webp" contiene lo stesso prefisso di base di un ipotetico
        // "foto.webp" solo per costruzione del nome, ma NON deve mai essere
        // toccato dalla cancellazione delle varianti di "foto.webp": il
        // pattern usato da deleteForDiskName() ancora il nome base con "-"
        // seguito da cifre e "w." esatto, non un prefisso generico.
        $this->placeUploadedFileAt('articles/covers/foto.webp', 2000, 1000);
        $this->service()->generateForUpload(public_path('assets/img/articles/covers/foto.webp'), 'articles/covers/foto.webp');

        $unrelated = public_path('assets/img/articles/covers/foto-2.webp');
        file_put_contents($unrelated, 'contenuto-non-correlato');

        $this->service()->deleteForDiskName('articles/covers/foto.webp');

        $this->assertFileExists($unrelated, 'Un file dal nome simile ma distinto non deve mai essere cancellato.');
    }

    public function test_resolve_for_markup_returns_full_srcset_when_variants_exist(): void
    {
        $absolutePath = $this->placeUploadedFileAt('articles/covers/resolved.webp', 1600, 900);
        $this->service()->generateForUpload($absolutePath, 'articles/covers/resolved.webp');

        $resolved = $this->service()->resolveForMarkup('articles/covers/resolved.webp');

        $this->assertSame(1600, $resolved['width']);
        $this->assertSame(900, $resolved['height']);
        $this->assertStringContainsString('resolved-480w.webp 480w', $resolved['srcset']);
        $this->assertStringContainsString('resolved-960w.webp 960w', $resolved['srcset']);
        $this->assertStringContainsString('resolved.webp 1600w', $resolved['srcset']);
    }

    public function test_resolve_for_markup_falls_back_gracefully_when_no_variants_exist_yet(): void
    {
        // Requisito esplicito della missione: un'immagine gia' in
        // produzione senza varianti deve continuare a funzionare, senza
        // alcuna migrazione necessaria.
        $this->placeUploadedFileAt('articles/covers/legacy.webp', 1200, 800);

        $resolved = $this->service()->resolveForMarkup('articles/covers/legacy.webp');

        $this->assertSame(1200, $resolved['width']);
        $this->assertStringContainsString('legacy.webp 1200w', $resolved['srcset']);
        $this->assertStringNotContainsString('legacy-480w', $resolved['srcset']);
    }

    public function test_resolve_for_markup_falls_back_gracefully_when_original_file_is_missing(): void
    {
        $resolved = $this->service()->resolveForMarkup('articles/covers/does-not-exist.webp');

        $this->assertNull($resolved['width']);
        $this->assertNull($resolved['height']);
        $this->assertSame('', $resolved['srcset']);
        $this->assertStringContainsString('does-not-exist.webp', $resolved['src']);
    }

    public function test_a_missing_variant_falls_back_to_the_remaining_valid_candidates(): void
    {
        $absolutePath = $this->placeUploadedFileAt('articles/covers/partial.webp', 2000, 1000);
        $this->service()->generateForUpload($absolutePath, 'articles/covers/partial.webp');

        // Simula una variante corrotta/mancante rimuovendola manualmente.
        @unlink(public_path('assets/img/articles/covers/partial-480w.webp'));

        $resolved = $this->service()->resolveForMarkup('articles/covers/partial.webp');

        $this->assertStringNotContainsString('partial-480w', $resolved['srcset']);
        $this->assertStringContainsString('partial-960w.webp 960w', $resolved['srcset']);
        $this->assertStringContainsString('partial.webp 2000w', $resolved['srcset']);
    }

    public function test_corrupt_or_wrong_width_variant_is_not_advertised(): void
    {
        $absolutePath = $this->placeUploadedFileAt('articles/covers/validated.webp', 1600, 900);
        $this->service()->generateForUpload($absolutePath, 'articles/covers/validated.webp');

        file_put_contents(public_path('assets/img/articles/covers/validated-480w.webp'), 'not-an-image');

        $resolved = $this->service()->resolveForMarkup('articles/covers/validated.webp');

        $this->assertStringNotContainsString('validated-480w.webp', $resolved['srcset']);
        $this->assertStringContainsString('validated-960w.webp', $resolved['srcset']);
    }

    public function test_variant_missing_from_configured_served_root_is_not_advertised(): void
    {
        $this->setUpIsolatedMediaPublicRoot();
        $absolutePath = $this->placeUploadedFileAt('articles/covers/served.webp', 1600, 900);
        copy($absolutePath, $this->isolatedMediaPublicRoot.'/articles/covers/served.webp');
        $this->service()->generateForUpload($absolutePath, 'articles/covers/served.webp');

        @unlink($this->isolatedMediaPublicRoot.'/articles/covers/served-480w.webp');

        $resolved = $this->service()->resolveForMarkup('articles/covers/served.webp');

        $this->assertStringNotContainsString('served-480w.webp', $resolved['srcset']);
        $this->assertStringContainsString('served-960w.webp', $resolved['srcset']);
    }

}
