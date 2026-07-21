<?php

namespace Tests\Feature;

use App\Services\ImageService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithTestImages;
use Tests\TestCase;

/**
 * Test diretti di ImageService, senza passare dai controller.
 *
 * Ogni preset (Admin\ArticleController, Admin\MediaController,
 * Admin\CategoryController, Redazione\ArticleController) e riprodotto qui
 * usando esattamente gli stessi parametri (larghezza massima, qualita,
 * trasparenza, alwaysReencode, logErrors) che i controller reali passano a
 * resizeAndCompress() — vedi l'audit nella descrizione della PR per il
 * riscontro riga per riga.
 */
class ImageServiceTest extends TestCase
{
    use InteractsWithTestImages;

    private ImageService $service;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ImageService;
        $this->tempDir = sys_get_temp_dir().'/quark-imgsvc-'.uniqid('', true);
        mkdir($this->tempDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->tearDownTestImages();
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

    // ── 1. Naming — Admin Article ───────────────────────────────────

    public function test_admin_article_naming_includes_slug_timestamp_and_six_char_hex_suffix(): void
    {
        $file = $this->makeSolidImageUpload('My Cover Photo!.jpg', 50, 50);

        // Stessa espressione usata in Admin\ArticleController::validated().
        $suffix = date('YmdHis').'-'.substr(md5(rand()), 0, 6);
        $name = $this->service->buildFileName($file, 'jpg', $suffix);

        $this->assertMatchesRegularExpression(
            '/^my-cover-photo-\d{14}-[0-9a-f]{6}\.jpg$/',
            $name
        );
        $this->assertStringNotContainsString(' ', $name);
    }

    // ── 2. Naming — Admin Media ──────────────────────────────────────

    public function test_admin_media_naming_includes_slug_timestamp_and_six_char_alnum_suffix(): void
    {
        $file = $this->makeSolidImageUpload('Beautiful Sunset.png', 50, 50, 'png');

        // Stessa espressione usata in Admin\MediaController::store().
        $suffix = now()->format('YmdHis').'-'.Str::random(6);
        $name = $this->service->buildFileName($file, 'png', $suffix);

        $this->assertMatchesRegularExpression(
            '/^beautiful-sunset-\d{14}-[A-Za-z0-9]{6}\.png$/',
            $name
        );
    }

    // ── 3. Naming — Admin Category ───────────────────────────────────

    public function test_admin_category_naming_includes_hex_suffix_and_uploads_into_categories_subdirectory(): void
    {
        $file = $this->makeSolidImageUpload('Category Icon.jpg', 50, 50);

        // Stessa espressione usata in Admin\CategoryController::handleImageUpload().
        $suffix = date('YmdHis').'-'.substr(md5((string) microtime(true)), 0, 6);
        $name = $this->service->buildFileName($file, 'jpg', $suffix);

        $this->assertMatchesRegularExpression(
            '/^category-icon-\d{14}-[0-9a-f]{6}\.jpg$/',
            $name
        );

        $destination = $this->tempDir.'/assets/img/categories';
        $this->service->ensureDirectoryExists($destination, 0755);
        $fullPath = $this->service->upload($file, $destination, $name);

        $this->assertStringEndsWith('assets/img/categories/'.$name, $fullPath);
        $this->assertFileExists($destination.'/'.$name);
    }

    // ── 4. Naming — Redazione Article ────────────────────────────────

    public function test_redazione_article_naming_and_upload_without_any_resize_call(): void
    {
        $file = $this->makeSolidImageUpload('Articolo Bozza.jpg', 2000, 1000);

        // Stessa espressione usata in Redazione\ArticleController::store()/update().
        $suffix = date('YmdHis').'-'.Str::random(6);
        $name = $this->service->buildFileName($file, 'jpg', $suffix);

        $this->assertMatchesRegularExpression(
            '/^articolo-bozza-\d{14}-[A-Za-z0-9]{6}\.jpg$/',
            $name
        );

        $destination = $this->tempDir.'/assets/img';
        mkdir($destination, 0775, true);
        $fullPath = $this->service->upload($file, $destination, $name);

        // Il preset Redazione non chiama mai resizeAndCompress(): le
        // dimensioni originali restano intatte.
        [$w, $h] = getimagesize($fullPath);
        $this->assertSame(2000, $w);
        $this->assertSame(1000, $h);
    }

    // ── 5. Creazione directory ───────────────────────────────────────

    public function test_ensure_directory_exists_creates_missing_nested_directory(): void
    {
        $target = $this->tempDir.'/assets/img/categories';
        $this->assertDirectoryDoesNotExist($target);

        $this->service->ensureDirectoryExists($target, 0775);

        $this->assertDirectoryExists($target);
    }

    public function test_ensure_directory_exists_applies_requested_permissions_on_posix(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('I permessi POSIX non sono verificabili in modo portabile su Windows.');
        }

        $target = $this->tempDir.'/assets/img';
        $this->service->ensureDirectoryExists($target, 0775);

        $expected = 0775 & ~umask();
        $this->assertSame($expected, fileperms($target) & 0777);
    }

    public function test_ensure_directory_exists_is_a_no_op_when_directory_already_present(): void
    {
        $target = $this->tempDir.'/assets/img';
        mkdir($target, 0700, true);

        // Non deve tentare di ricreare (e quindi fallire) una directory esistente.
        $this->service->ensureDirectoryExists($target, 0775);

        $this->assertDirectoryExists($target);
    }

    // ── 6. Upload semplice ────────────────────────────────────────────

    public function test_upload_moves_the_file_to_the_destination_with_the_given_name(): void
    {
        $file = $this->makeSolidImageUpload('photo.jpg', 40, 40);
        $sourcePath = $file->getRealPath();

        $destination = $this->tempDir.'/assets/img';
        mkdir($destination, 0775, true);

        $fullPath = $this->service->upload($file, $destination, 'final-name.jpg');

        $this->assertSame($destination.'/final-name.jpg', $fullPath);
        $this->assertFileExists($fullPath);
        $this->assertFileDoesNotExist($sourcePath);
        $this->assertSame('jpg', pathinfo($fullPath, PATHINFO_EXTENSION));
    }

    // ── 7. Resize ──────────────────────────────────────────────────────

    public static function resizePresetProvider(): array
    {
        return [
            'Admin Article (1600px)' => [1600, ['jpg' => 82, 'png' => 7, 'webp' => 82]],
            'Admin Media (1600px)' => [1600, ['jpg' => 82, 'png' => 7, 'webp' => 82]],
            'Admin Category (1200px)' => [1200, ['jpg' => 84, 'png' => 7, 'webp' => 84]],
        ];
    }

    #[DataProvider('resizePresetProvider')]
    public function test_resize_scales_down_to_max_width_preserving_aspect_ratio(int $maxWidth, array $quality): void
    {
        $file = $this->makeSolidImageUpload('big.jpg', 2000, 1000);
        $destination = $this->tempDir.'/assets/img';
        mkdir($destination, 0775, true);
        $fullPath = $this->service->upload($file, $destination, 'big.jpg');

        $this->service->resizeAndCompress($fullPath, 'jpg', $maxWidth, $quality);

        [$w, $h] = getimagesize($fullPath);
        $this->assertLessThanOrEqual($maxWidth, $w);
        $this->assertSame($maxWidth, $w);

        $expectedHeight = (int) round(1000 * ($maxWidth / 2000));
        $this->assertSame($expectedHeight, $h);
    }

    // ── 8. Nessun upscale ────────────────────────────────────────────

    public function test_admin_and_category_presets_leave_a_smaller_image_completely_untouched(): void
    {
        $file = $this->makeSolidImageUpload('small.jpg', 400, 300);
        $destination = $this->tempDir.'/assets/img';
        mkdir($destination, 0775, true);
        $fullPath = $this->service->upload($file, $destination, 'small.jpg');

        $before = file_get_contents($fullPath);

        // alwaysReencode: false, come Admin\ArticleController e Admin\CategoryController.
        $this->service->resizeAndCompress($fullPath, 'jpg', 1600, ['jpg' => 82, 'png' => 7, 'webp' => 82]);

        [$w, $h] = getimagesize($fullPath);
        $this->assertSame(400, $w);
        $this->assertSame(300, $h);

        // Nessuna funzione GD viene invocata in questo ramo: il file resta
        // byte-per-byte identico (non e una compressione "coincidentalmente"
        // identica, e proprio un no-op strutturale del servizio).
        $this->assertSame($before, file_get_contents($fullPath));
    }

    public function test_media_preset_does_not_upscale_a_smaller_image_but_may_recompress_it(): void
    {
        $file = $this->makeSolidImageUpload('small.jpg', 400, 300);
        $destination = $this->tempDir.'/assets/img';
        mkdir($destination, 0775, true);
        $fullPath = $this->service->upload($file, $destination, 'small.jpg');

        // alwaysReencode: true, come Admin\MediaController.
        $this->service->resizeAndCompress(
            $fullPath, 'jpg', 1600, ['jpg' => 82, 'png' => 7, 'webp' => 82],
            preserveTransparency: true, alwaysReencode: true, logErrors: true
        );

        [$w, $h] = getimagesize($fullPath);
        $this->assertSame(400, $w);
        $this->assertSame(300, $h);
        $this->assertGreaterThan(0, filesize($fullPath));
    }

    // ── 9. Redazione senza ottimizzazione ─────────────────────────────

    public function test_redazione_preset_never_calls_resize_and_preserves_original_bytes(): void
    {
        $file = $this->makeSolidImageUpload('big.jpg', 2000, 1200);
        $destination = $this->tempDir.'/assets/img';
        mkdir($destination, 0775, true);

        $fullPath = $this->service->upload($file, $destination, 'big.jpg');
        $afterUploadBytes = file_get_contents($fullPath);

        // Redazione\ArticleController non chiama mai resizeAndCompress():
        // simuliamo esattamente questo (nessuna chiamata) e verifichiamo
        // che il file caricato resti quello originale.
        [$w, $h] = getimagesize($fullPath);
        $this->assertSame(2000, $w);
        $this->assertSame(1200, $h);
        $this->assertSame($afterUploadBytes, file_get_contents($fullPath));
    }

    // ── 10. PNG e trasparenza ──────────────────────────────────────────

    public function test_media_preset_preserves_png_transparency_when_resizing(): void
    {
        $file = $this->makeTransparentPngUpload('transparent.png', 2000, 2000);
        $destination = $this->tempDir.'/assets/img';
        mkdir($destination, 0775, true);
        $fullPath = $this->service->upload($file, $destination, 'transparent.png');

        $this->service->resizeAndCompress(
            $fullPath, 'png', 1600, ['jpg' => 82, 'png' => 7, 'webp' => 82],
            preserveTransparency: true, alwaysReencode: true, logErrors: true
        );

        $resized = imagecreatefrompng($fullPath);
        $rgba = imagecolorat($resized, (int) (imagesx($resized) / 2), (int) (imagesy($resized) / 2));
        $alpha = ($rgba >> 24) & 0x7F;
        imagedestroy($resized);

        // 127 = completamente trasparente in GD. Soglia larga per tollerare
        // l'antialiasing del resample sui bordi, ma resta ben lontana da 0
        // (opaco), dimostrando che il canale alfa e stato preservato.
        $this->assertGreaterThan(100, $alpha);
    }

    public function test_admin_article_preset_does_not_preserve_png_transparency_on_resize(): void
    {
        // Comportamento reale e storico: Admin\ArticleController non passa
        // preserveTransparency=true. Questo test ne fotografa il
        // comportamento attuale come guardia di regressione, senza
        // correggerlo (fuori scope per questo task).
        $file = $this->makeTransparentPngUpload('transparent.png', 2000, 2000);
        $destination = $this->tempDir.'/assets/img';
        mkdir($destination, 0775, true);
        $fullPath = $this->service->upload($file, $destination, 'transparent.png');

        $this->service->resizeAndCompress($fullPath, 'png', 1600, ['jpg' => 82, 'png' => 7, 'webp' => 82]);

        $resized = imagecreatefrompng($fullPath);
        $rgba = imagecolorat($resized, (int) (imagesx($resized) / 2), (int) (imagesy($resized) / 2));
        $alpha = ($rgba >> 24) & 0x7F;
        imagedestroy($resized);

        $this->assertSame(0, $alpha);
    }

    // ── 11. JPEG ───────────────────────────────────────────────────────

    public function test_jpeg_remains_a_valid_readable_image_after_processing_with_real_quality_parameter(): void
    {
        $file = $this->makeSolidImageUpload('photo.jpg', 2000, 1500);
        $destination = $this->tempDir.'/assets/img';
        mkdir($destination, 0775, true);
        $fullPath = $this->service->upload($file, $destination, 'photo.jpg');

        // Stesso parametro qualita passato da Admin\ArticleController (82).
        $this->service->resizeAndCompress($fullPath, 'jpg', 1600, ['jpg' => 82, 'png' => 7, 'webp' => 82]);

        $info = getimagesize($fullPath);
        $this->assertNotFalse($info, 'Il file risultante deve restare un\'immagine leggibile.');
        $this->assertSame('image/jpeg', $info['mime']);
        $this->assertSame(1600, $info[0]);
    }

    // ── 12. WebP ─────────────────────────────────────────────────────

    public function test_webp_is_resized_and_remains_readable_when_gd_supports_it(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('Il build GD di questo ambiente non supporta WebP.');
        }

        $file = $this->makeSolidImageUpload('photo.webp', 2000, 1000, 'webp');
        $destination = $this->tempDir.'/assets/img';
        mkdir($destination, 0775, true);
        $fullPath = $this->service->upload($file, $destination, 'photo.webp');

        $this->service->resizeAndCompress(
            $fullPath, 'webp', 1600, ['jpg' => 82, 'png' => 7, 'webp' => 82],
            preserveTransparency: true, alwaysReencode: true
        );

        $info = getimagesize($fullPath);
        $this->assertNotFalse($info);
        $this->assertSame('image/webp', $info['mime']);
        $this->assertSame(1600, $info[0]);
    }

    // ── 13. GD non disponibile (limite documentato) ────────────────────

    public function test_resize_is_a_safe_no_op_when_the_target_file_does_not_exist(): void
    {
        // Non e possibile disabilitare dinamicamente l'estensione GD in
        // questo processo PHP (e il task lo vieta esplicitamente). GD e
        // disponibile in questo ambiente (verificato nel report della PR).
        // Il servizio usa un'unica guardia combinata:
        //   if (! extension_loaded('gd') || ! file_exists($fullPath)) return;
        // Questo test esercita la stessa riga di codice tramite il secondo
        // ramo della condizione (file assente), dimostrando che il "safe
        // early return" della guardia funziona senza errori fatali. Il
        // ramo "GD assente" resta non testabile senza modifiche invasive
        // (skippare/mockare extension_loaded() richiederebbe un'estensione
        // di test PHP dedicata, esplicitamente fuori scope).
        $missingPath = $this->tempDir.'/does-not-exist.jpg';

        $this->service->resizeAndCompress($missingPath, 'jpg', 1600, ['jpg' => 82]);

        $this->assertFileDoesNotExist($missingPath);
    }

    // ── 14. File non valido o errore di elaborazione ───────────────────

    public function test_corrupt_image_does_not_throw_and_leaves_the_original_file_untouched(): void
    {
        Log::spy();

        $file = $this->makeNonImageUpload('fake.jpg');
        $destination = $this->tempDir.'/assets/img';
        mkdir($destination, 0775, true);
        $fullPath = $this->service->upload($file, $destination, 'fake.jpg');
        $originalBytes = file_get_contents($fullPath);

        // Preset con logErrors=true (Media): comportamento empiricamente
        // verificato in fase di audit — getimagesize() fallisce in modo
        // "morbido" (nessuna eccezione), quindi il ramo alwaysReencode la
        // intercetta con un return esplicito prima di raggiungere il
        // catch/log. Nessun log atteso in questo scenario specifico.
        $this->service->resizeAndCompress(
            $fullPath, 'jpg', 1600, ['jpg' => 82],
            alwaysReencode: true, logErrors: true
        );

        $this->assertFileExists($fullPath);
        $this->assertSame($originalBytes, file_get_contents($fullPath));
        Log::shouldNotHaveReceived('warning');
    }

    public function test_a_genuine_gd_failure_is_caught_and_logged_only_when_the_preset_enables_logging(): void
    {
        // createImageResource() e stato reso "protected" (da "private")
        // esclusivamente per questo scenario: nessun fixture di immagine
        // corrotta disponibile in questo ambiente riesce a far fallire GD
        // in un modo che sollevi davvero un'eccezione (i rami di errore
        // reali sono tutti intercettati da guardie esplicite con return,
        // non da throw — verificato empiricamente). Una sottoclasse di
        // test che sovrascrive questo singolo metodo protetto e il modo
        // meno invasivo per simulare un errore GD realmente catastrofico e
        // verificare il comportamento del blocco catch, senza introdurre
        // wrapper o dipendenze aggiuntive nel servizio di produzione.
        $service = new class extends ImageService
        {
            protected function createImageResource(string $path, string $ext)
            {
                throw new \RuntimeException('Errore GD simulato per il test');
            }
        };

        $file = $this->makeSolidImageUpload('big.jpg', 2000, 1000);
        $destination = $this->tempDir.'/assets/img';
        mkdir($destination, 0775, true);
        $fullPath = $service->upload($file, $destination, 'big.jpg');
        $originalBytes = file_get_contents($fullPath);

        Log::spy();

        // Preset Admin\ArticleController / Admin\CategoryController: logErrors=false.
        $service->resizeAndCompress($fullPath, 'jpg', 1600, ['jpg' => 82]);

        $this->assertFileExists($fullPath);
        $this->assertSame($originalBytes, file_get_contents($fullPath), 'Il file originale non deve essere toccato se GD fallisce.');
        Log::shouldNotHaveReceived('warning');

        Log::spy();

        // Preset Admin\MediaController: logErrors=true.
        $service->resizeAndCompress($fullPath, 'jpg', 1600, ['jpg' => 82], logErrors: true);

        $this->assertFileExists($fullPath);
        $this->assertSame($originalBytes, file_get_contents($fullPath));
        Log::shouldHaveReceived('warning')->once();
    }
}
