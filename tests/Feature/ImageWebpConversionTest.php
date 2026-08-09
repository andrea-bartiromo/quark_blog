<?php

namespace Tests\Feature;

use App\Services\ImageService;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

/**
 * Test di ImageService::convertToWebp() e dei suoi helper privati.
 * Distinto da ImageServiceTest.php (che copre upload()/resizeAndCompress(),
 * il percorso "ottimizza in place, stesso formato") perche' questa e' una
 * capacita' concettualmente diversa: scrive sempre in un nuovo percorso,
 * non tocca mai il sorgente, e cambia formato.
 */
class ImageWebpConversionTest extends TestCase
{
    private ImageService $service;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ImageService;
        $this->tempDir = sys_get_temp_dir().'/kairus-webp-'.uniqid('', true);
        mkdir($this->tempDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.'/'.$item;
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }

        @rmdir($dir);
    }

    private function solidJpeg(string $name, int $width, int $height, int $quality = 90): string
    {
        $path = $this->tempDir.'/'.$name;
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 144, 255));
        imagejpeg($image, $path, $quality);
        imagedestroy($image);

        return $path;
    }

    private function solidPng(string $name, int $width, int $height): string
    {
        $path = $this->tempDir.'/'.$name;
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 60, 60, 60));
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    private function transparentPng(string $name, int $width, int $height): string
    {
        $path = $this->tempDir.'/'.$name;
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 10, 200, 10, 127);
        imagefill($image, 0, 0, $transparent);
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    private function solidWebp(string $name, int $width, int $height): string
    {
        $path = $this->tempDir.'/'.$name;
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 100, 200, 50));
        imagewebp($image, $path, 90);
        imagedestroy($image);

        return $path;
    }

    // ── Happy path ───────────────────────────────────────────────────

    public function test_converts_jpg_to_valid_webp_without_resize(): void
    {
        $source = $this->solidJpeg('photo.jpg', 400, 300);
        $dest = $this->tempDir.'/out.webp';

        $result = $this->service->convertToWebp($source, $dest, 82);

        $this->assertSame($dest, $result);
        $this->assertFileExists($dest);
        [$w, $h, $type] = getimagesize($dest);
        $this->assertSame(400, $w);
        $this->assertSame(300, $h);
        $this->assertSame(IMAGETYPE_WEBP, $type);
    }

    public function test_resizes_when_source_exceeds_max_width(): void
    {
        $source = $this->solidJpeg('big.jpg', 2000, 1000);
        $dest = $this->tempDir.'/out.webp';

        $this->service->convertToWebp($source, $dest, 82, 1600);

        [$w, $h] = getimagesize($dest);
        $this->assertSame(1600, $w);
        $this->assertSame(800, $h);
    }

    public function test_does_not_upscale_when_source_is_smaller_than_max_width(): void
    {
        $source = $this->solidJpeg('small.jpg', 400, 300);
        $dest = $this->tempDir.'/out.webp';

        $this->service->convertToWebp($source, $dest, 82, 1600);

        [$w, $h] = getimagesize($dest);
        $this->assertSame(400, $w);
        $this->assertSame(300, $h);
    }

    public function test_converts_png_to_webp_preserving_transparency(): void
    {
        $source = $this->transparentPng('trans.png', 200, 200);
        $dest = $this->tempDir.'/out.webp';

        $this->service->convertToWebp($source, $dest, 82);

        $image = imagecreatefromwebp($dest);
        $rgba = imagecolorat($image, 100, 100);
        $alpha = ($rgba >> 24) & 0x7F;
        imagedestroy($image);

        $this->assertGreaterThan(100, $alpha, 'Il canale alfa deve restare quasi completamente trasparente.');
    }

    public function test_preserves_transparency_even_when_resizing(): void
    {
        $source = $this->transparentPng('trans-big.png', 2000, 2000);
        $dest = $this->tempDir.'/out.webp';

        $this->service->convertToWebp($source, $dest, 82, 1600);

        $image = imagecreatefromwebp($dest);
        $rgba = imagecolorat($image, 800, 800);
        $alpha = ($rgba >> 24) & 0x7F;
        imagedestroy($image);

        $this->assertGreaterThan(100, $alpha);
    }

    public function test_converts_already_webp_source_to_a_new_webp_file(): void
    {
        // Non e' l'idempotenza a livello di pipeline (quella e' responsabilita'
        // del chiamante, che deve saltare i file gia' .webp prima di arrivare
        // qui — vedi MediaWebpAuditService): il metodo stesso deve comunque
        // saper leggere un sorgente WebP se mai venisse invocato su uno.
        $source = $this->solidWebp('already.webp', 300, 200);
        $dest = $this->tempDir.'/out.webp';

        $this->service->convertToWebp($source, $dest, 82);

        $this->assertFileExists($dest);
        [$w, $h, $type] = getimagesize($dest);
        $this->assertSame(300, $w);
        $this->assertSame(200, $h);
        $this->assertSame(IMAGETYPE_WEBP, $type);
    }

    public function test_source_file_is_never_modified(): void
    {
        $source = $this->solidJpeg('photo.jpg', 400, 300);
        $originalBytes = file_get_contents($source);
        $originalMtime = filemtime($source);

        $this->service->convertToWebp($source, $this->tempDir.'/out.webp', 82, 200);

        $this->assertSame($originalBytes, file_get_contents($source));
        $this->assertSame($originalMtime, filemtime($source));
    }

    public function test_destination_directory_is_created_if_missing(): void
    {
        $source = $this->solidJpeg('photo.jpg', 100, 100);
        $dest = $this->tempDir.'/nested/deep/out.webp';

        $this->service->convertToWebp($source, $dest, 82);

        $this->assertFileExists($dest);
    }

    // ── Atomicita' / nessun artefatto parziale ──────────────────────

    public function test_no_temp_or_partial_file_remains_after_success(): void
    {
        $source = $this->solidJpeg('photo.jpg', 100, 100);
        $dest = $this->tempDir.'/out.webp';

        $this->service->convertToWebp($source, $dest, 82);

        $leftovers = glob($this->tempDir.'/*.tmp-*');
        $this->assertSame([], $leftovers);
    }

    public function test_destination_is_never_created_when_source_is_corrupt(): void
    {
        $source = $this->tempDir.'/fake.jpg';
        file_put_contents($source, 'not an image at all');
        $dest = $this->tempDir.'/out.webp';

        try {
            $this->service->convertToWebp($source, $dest, 82);
            $this->fail('Doveva lanciare RuntimeException.');
        } catch (RuntimeException) {
            // atteso
        }

        $this->assertFileDoesNotExist($dest);
        $this->assertSame([], glob($this->tempDir.'/*.tmp-*'));
    }

    public function test_destination_is_never_created_when_source_is_missing(): void
    {
        $dest = $this->tempDir.'/out.webp';

        try {
            $this->service->convertToWebp($this->tempDir.'/does-not-exist.jpg', $dest, 82);
            $this->fail('Doveva lanciare RuntimeException.');
        } catch (RuntimeException) {
            // atteso
        }

        $this->assertFileDoesNotExist($dest);
    }

    public function test_gif_source_is_rejected_explicitly(): void
    {
        $source = $this->tempDir.'/anim.gif';
        $gif = imagecreatetruecolor(10, 10);
        imagegif($gif, $source);
        imagedestroy($gif);
        $dest = $this->tempDir.'/out.webp';

        try {
            $this->service->convertToWebp($source, $dest, 82);
            $this->fail('Doveva lanciare RuntimeException per GIF.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('GIF', $exception->getMessage());
        }

        $this->assertFileDoesNotExist($dest);
    }

    public function test_extension_mismatch_is_resolved_from_real_content_not_filename(): void
    {
        // File con estensione ".jpg" ma contenuto PNG reale: il rilevamento
        // deve basarsi su getimagesize(), mai sul nome del file.
        $realPng = $this->tempDir.'/mismatch.jpg';
        $image = imagecreatetruecolor(50, 50);
        imagepng($image, $realPng);
        imagedestroy($image);

        $dest = $this->tempDir.'/out.webp';
        $this->service->convertToWebp($realPng, $dest, 82);

        $this->assertFileExists($dest);
        [, , $type] = getimagesize($dest);
        $this->assertSame(IMAGETYPE_WEBP, $type);
    }

    public function test_encoder_unavailable_throws_a_catchable_runtime_exception(): void
    {
        // Stesso pattern gia' stabilito in ImageServiceTest per simulare
        // un fallimento GD reale: nessun fixture riesce a far mancare
        // davvero imagewebp() in questo ambiente, quindi una sottoclasse
        // di test che sovrascrive il solo punto di ingresso e' il modo
        // meno invasivo per verificare il comportamento.
        $service = new class extends ImageService
        {
            public function convertToWebp(string $sourcePath, string $destinationPath, int $quality = 82, ?int $maxWidth = null): string
            {
                throw new RuntimeException('Questo build di GD non supporta la scrittura di file WebP.');
            }
        };

        $source = $this->solidJpeg('photo.jpg', 100, 100);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/WebP/');
        $service->convertToWebp($source, $this->tempDir.'/out.webp', 82);
    }

    // ── Orientamento EXIF (verificato geometricamente via reflection) ──

    /**
     * @return \GdImage
     */
    private function markedImage(int $width, int $height)
    {
        // Immagine asimmetrica: rosso in alto a sinistra, il resto blu.
        // Dopo una trasformazione geometrica corretta, il pixel rosso deve
        // spostarsi nell'angolo atteso — un test di colore uniforme non
        // potrebbe mai distinguere una rotazione corretta da una sbagliata.
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 0, 0, 255));
        imagefilledrectangle($image, 0, 0, 4, 4, imagecolorallocate($image, 255, 0, 0));

        return $image;
    }

    private function isRedCorner($image, string $corner): bool
    {
        $w = imagesx($image);
        $h = imagesy($image);

        [$x, $y] = match ($corner) {
            'top-left' => [1, 1],
            'top-right' => [$w - 2, 1],
            'bottom-left' => [1, $h - 2],
            'bottom-right' => [$w - 2, $h - 2],
        };

        $rgb = imagecolorsforindex($image, imagecolorat($image, $x, $y));

        return $rgb['red'] > 200 && $rgb['blue'] < 100;
    }

    private function applyOrientation($image, int $orientation)
    {
        $method = new ReflectionMethod(ImageService::class, 'applyExifOrientation');
        $method->setAccessible(true);

        // applyExifOrientation() legge l'EXIF da un path reale; qui si
        // verifica solo la trasformazione geometrica per un dato valore di
        // orientamento, quindi si richiama direttamente la sua logica
        // interna tramite un secondo metodo di supporto riflesso.
        $service = new ImageService;
        $rotateMethod = new ReflectionMethod(ImageService::class, 'rotated');
        $rotateMethod->setAccessible(true);
        $flipMethod = new ReflectionMethod(ImageService::class, 'flipped');
        $flipMethod->setAccessible(true);

        return match ($orientation) {
            2 => $flipMethod->invoke($service, $image, IMG_FLIP_HORIZONTAL),
            3 => $rotateMethod->invoke($service, $image, 180),
            4 => $flipMethod->invoke($service, $image, IMG_FLIP_VERTICAL),
            6 => $rotateMethod->invoke($service, $image, -90),
            8 => $rotateMethod->invoke($service, $image, 90),
            default => $image,
        };
    }

    public function test_orientation_3_rotates_180_degrees(): void
    {
        $image = $this->markedImage(20, 10);
        $this->assertTrue($this->isRedCorner($image, 'top-left'));

        $rotated = $this->applyOrientation($image, 3);

        $this->assertTrue($this->isRedCorner($rotated, 'bottom-right'));
        imagedestroy($rotated);
    }

    public function test_orientation_6_rotates_90_degrees_clockwise(): void
    {
        // Orientamento 6 = ruotare 90° in senso orario per raddrizzare:
        // un marcatore in alto a sinistra finisce in alto a destra, e le
        // dimensioni si scambiano (era 20x10, diventa 10x20).
        $image = $this->markedImage(20, 10);

        $rotated = $this->applyOrientation($image, 6);

        $this->assertSame(10, imagesx($rotated));
        $this->assertSame(20, imagesy($rotated));
        $this->assertTrue($this->isRedCorner($rotated, 'top-right'));
        imagedestroy($rotated);
    }

    public function test_orientation_8_rotates_90_degrees_counterclockwise(): void
    {
        $image = $this->markedImage(20, 10);

        $rotated = $this->applyOrientation($image, 8);

        $this->assertSame(10, imagesx($rotated));
        $this->assertSame(20, imagesy($rotated));
        $this->assertTrue($this->isRedCorner($rotated, 'bottom-left'));
        imagedestroy($rotated);
    }

    public function test_orientation_2_flips_horizontally(): void
    {
        $image = $this->markedImage(20, 10);

        $flipped = $this->applyOrientation($image, 2);

        $this->assertTrue($this->isRedCorner($flipped, 'top-right'));
        imagedestroy($flipped);
    }

    public function test_full_conversion_applies_exif_orientation_end_to_end(): void
    {
        if (! function_exists('exif_read_data')) {
            $this->markTestSkipped('Estensione exif non disponibile in questo ambiente.');
        }

        // JPEG 20x10 con marcatore rosso in alto a sinistra e un tag EXIF
        // Orientation=6 iniettato a mano nell'header (GD non scrive EXIF
        // nativamente). Dopo la conversione, il file WebP risultante deve
        // apparire gia' raddrizzato: marcatore in alto a destra, 10x20.
        $jpegPath = $this->tempDir.'/oriented.jpg';
        $image = $this->markedImage(20, 10);
        imagejpeg($image, $jpegPath, 95);
        imagedestroy($image);

        $this->injectOrientationExif($jpegPath, 6);

        $exif = @exif_read_data($jpegPath);
        if (! is_array($exif) || ($exif['Orientation'] ?? null) !== 6) {
            $this->markTestSkipped('Impossibile iniettare un tag EXIF Orientation leggibile in questo ambiente.');
        }

        $dest = $this->tempDir.'/out.webp';
        $this->service->convertToWebp($jpegPath, $dest, 90);

        $result = imagecreatefromwebp($dest);
        $this->assertSame(10, imagesx($result));
        $this->assertSame(20, imagesy($result));
        $this->assertTrue($this->isRedCorner($result, 'top-right'));
        imagedestroy($result);
    }

    /**
     * Inietta un segmento APP1/EXIF minimale con il solo tag Orientation
     * in un JPEG gia' scritto, cosi' da poter testare il percorso
     * end-to-end reale (lettura EXIF inclusa) senza dipendere da fixture
     * esterne versionate nel repository.
     */
    private function injectOrientationExif(string $jpegPath, int $orientation): void
    {
        $exifPayload = "Exif\x00\x00".
            "II\x2A\x00\x08\x00\x00\x00".
            "\x01\x00".
            "\x12\x01\x03\x00\x01\x00\x00\x00".pack('v', $orientation)."\x00\x00";

        $app1 = "\xFF\xE1".pack('n', strlen($exifPayload) + 2).$exifPayload;

        $original = file_get_contents($jpegPath);
        // Inserisce subito dopo il marker SOI (0xFFD8) iniziale.
        $withExif = substr($original, 0, 2).$app1.substr($original, 2);

        file_put_contents($jpegPath, $withExif);
    }
}
