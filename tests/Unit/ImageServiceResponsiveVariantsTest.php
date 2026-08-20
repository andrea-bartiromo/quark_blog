<?php

namespace Tests\Unit;

use App\Services\ImageService;
use Tests\Concerns\InteractsWithTestImages;
use Tests\TestCase;

/**
 * FASE 5/9 (missione S2 responsive images): test diretti di
 * ImageService::generateResponsiveVariants()/responsiveVariantPath(), la
 * nuova capacita' di produrre copie WebP piu' strette accanto a
 * un'immagine gia' presente sul filesystem — senza toccare
 * resizeAndCompress()/convertToWebp() ne' alcun comportamento preesistente.
 */
class ImageServiceResponsiveVariantsTest extends TestCase
{
    use InteractsWithTestImages;

    private ImageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ImageService;
    }

    protected function tearDown(): void
    {
        $this->tearDownTestImages();

        parent::tearDown();
    }

    public function test_responsive_variant_path_appends_width_before_extension_preserving_directory(): void
    {
        $this->assertSame(
            'articles/covers/foto-480w.webp',
            $this->service->responsiveVariantPath('articles/covers/foto.webp', 480)
        );

        $this->assertSame(
            '/var/www/public/assets/img/foto-960w.jpg',
            $this->service->responsiveVariantPath('/var/www/public/assets/img/foto.jpg', 960)
        );

        $this->assertSame(
            'foto-480w.webp',
            $this->service->responsiveVariantPath('foto.webp', 480)
        );
    }

    public function test_generates_a_variant_for_each_target_width_smaller_than_the_source(): void
    {
        $file = $this->makeSolidImageUpload('big-photo.jpg', 2000, 1000);
        $source = $file->getPathname();

        // 2000 e' escluso: una larghezza target UGUALE alla larghezza reale
        // del sorgente non e' un upscale in senso stretto, ma non serve a
        // nulla generarla (il sorgente stesso e' gia' quella variante) —
        // stesso confine "< sourceWidth", mai "<=".
        $written = $this->service->generateResponsiveVariants($source, [480, 960, 2000], 82);

        $this->assertCount(2, $written);
        $this->assertSame([480, 960], array_column($written, 'width'));
    }

    public function test_never_upscales_a_target_width_greater_or_equal_to_the_source(): void
    {
        $file = $this->makeSolidImageUpload('small-photo.jpg', 400, 300);
        $source = $file->getPathname();

        $written = $this->service->generateResponsiveVariants($source, [480, 960], 82);

        $this->assertSame([], $written, 'Un sorgente di 400px non deve produrre varianti a 480/960: sarebbero un upscale.');
    }

    public function test_variant_dimensions_are_proportional_to_the_source_aspect_ratio(): void
    {
        $file = $this->makeSolidImageUpload('wide-photo.jpg', 1600, 900);
        $source = $file->getPathname();

        $written = $this->service->generateResponsiveVariants($source, [480], 82);

        $this->assertCount(1, $written);
        $info = getimagesize($written[0]['path']);
        $this->assertSame(480, $info[0]);
        $this->assertSame(270, $info[1], 'Aspect ratio 16:9 preservato: 480 * 900/1600 = 270.');
        $this->assertSame(IMAGETYPE_WEBP, $info[2]);
    }

    public function test_is_idempotent_a_second_call_does_not_error_and_keeps_a_valid_file(): void
    {
        $file = $this->makeSolidImageUpload('idem-photo.jpg', 1200, 800);
        $source = $file->getPathname();

        $first = $this->service->generateResponsiveVariants($source, [480], 82);
        $mtimeFirst = filemtime($first[0]['path']);

        // Piccola attesa per rendere un eventuale mtime diverso rilevabile.
        usleep(1_100_000);

        $second = $this->service->generateResponsiveVariants($source, [480], 82);

        $this->assertCount(1, $second);
        $this->assertSame($first[0]['path'], $second[0]['path']);
        $this->assertSame(
            $mtimeFirst,
            filemtime($second[0]['path']),
            'Un file gia valido non deve essere riscritto: stesso mtime, nessuna rigenerazione superflua.'
        );
    }

    public function test_gif_sources_are_never_processed_to_preserve_animation(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'kairus-gif-').'.gif';
        $this->tempImageFilesForCleanup($path);
        $im = imagecreatetruecolor(800, 600);
        imagegif($im, $path);
        imagedestroy($im);

        $written = $this->service->generateResponsiveVariants($path, [480], 82);

        $this->assertSame([], $written);
    }

    public function test_missing_source_file_returns_empty_array_without_throwing(): void
    {
        $written = $this->service->generateResponsiveVariants('/nonexistent/path/to/photo.jpg', [480], 82);

        $this->assertSame([], $written);
    }

    public function test_corrupted_source_returns_empty_array_without_throwing(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'kairus-corrupt-').'.jpg';
        $this->tempImageFilesForCleanup($path);
        file_put_contents($path, 'this is not a real jpeg');

        $written = $this->service->generateResponsiveVariants($path, [480], 82);

        $this->assertSame([], $written);
    }

    public function test_empty_target_widths_returns_empty_array(): void
    {
        $file = $this->makeSolidImageUpload('photo.jpg', 2000, 1000);

        $written = $this->service->generateResponsiveVariants($file->getPathname(), [], 82);

        $this->assertSame([], $written);
    }

    public function test_preserves_transparency_for_png_sources(): void
    {
        $file = $this->makeTransparentPngUpload('transparent.png', 1000, 1000);

        $written = $this->service->generateResponsiveVariants($file->getPathname(), [400], 82);

        $this->assertCount(1, $written);

        // Un pixel al centro deve restare trasparente (alpha != 0) dopo il
        // resize verso WebP, non appiattito su uno sfondo opaco.
        $im = imagecreatefromwebp($written[0]['path']);
        $rgba = imagecolorat($im, 200, 200);
        $alpha = ($rgba >> 24) & 0x7F;
        imagedestroy($im);

        $this->assertGreaterThan(0, $alpha, 'Il pixel deve conservare un canale alfa non nullo (trasparenza preservata).');
    }

    /**
     * Piccolo helper locale: il trait InteractsWithTestImages traccia solo
     * i file creati tramite i suoi stessi metodi; qui vogliamo lo stesso
     * comportamento di cleanup automatico anche per un file GIF/corrotto
     * costruito a mano in questo test.
     */
    private function tempImageFilesForCleanup(string $path): void
    {
        $ref = new \ReflectionProperty($this, 'tempImageFiles');
        $ref->setAccessible(true);
        $current = $ref->getValue($this);
        $current[] = $path;
        $ref->setValue($this, $current);
    }
}
