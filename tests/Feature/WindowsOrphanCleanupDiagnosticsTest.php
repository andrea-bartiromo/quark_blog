<?php

namespace Tests\Feature;

use App\Services\ImageService;
use App\Services\PublicMediaSyncService;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

/**
 * Riproduzione minima e diagnostica del ciclo upload -> cleanup, isolata da
 * HTTP/controller/GD-resize/PublicMediaSyncService::create(): serve a
 * capire se un eventuale fallimento di cleanup su Windows reale (vedi
 * CategoryProfileTuringMediaSyncTest e MediaPublicSyncTest, ancora falliti
 * dopo il retry introdotto in PR #123) dipende dal solo
 * upload()+cleanupAfterFailedCreate() oppure da qualcos'altro nella catena
 * piu' ampia (validazione, resize GD, la simulazione di root pubblica non
 * utilizzabile, ecc.).
 *
 * Usa esattamente la stessa costruzione di path di UsesIsolatedPublicPath
 * (sys_get_temp_dir() piu' marker, potenzialmente a separatori misti su
 * Windows) usata dai test che falliscono, ma chiama i servizi direttamente,
 * senza passare da una richiesta HTTP.
 *
 * cleanupAfterFailedCreate() e removeFile() non sono mockati qui: usano il
 * vero @unlink() di produzione, quindi i log di diagnostica temporanea
 * aggiunti a PublicMediaSyncService/ImageService vengono prodotti anche da
 * questo test.
 */
class WindowsOrphanCleanupDiagnosticsTest extends TestCase
{
    use UsesIsolatedPublicPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedPublicPath();
    }

    protected function tearDown(): void
    {
        $this->tearDownIsolatedPublicPath();
        parent::tearDown();
    }

    public function test_upload_then_cleanup_removes_the_file_without_going_through_http(): void
    {
        $imageService = app(ImageService::class);
        $publicMediaSync = app(PublicMediaSyncService::class);

        $file = UploadedFile::fake()->image('diagnostica.jpg', 800, 600);
        $uploadPath = public_path('assets/img/categories');

        $fullPath = $imageService->upload($file, $uploadPath, 'diagnostica-test.jpg');

        $this->assertFileExists(
            $fullPath,
            'Il file deve esistere subito dopo upload(), prima di qualunque tentativo di cleanup.'
        );

        $this->assertSame(
            realpath($fullPath),
            realpath($uploadPath.'/diagnostica-test.jpg'),
            'Il path restituito da upload() deve puntare esattamente al file scritto su disco (nessuna ricostruzione altrove).'
        );

        $publicMediaSync->cleanupAfterFailedCreate($fullPath);

        clearstatcache(true, $fullPath);

        $this->assertFileDoesNotExist(
            $fullPath,
            'cleanupAfterFailedCreate() deve rimuovere il file appena caricato quando esiste ed e\' raggiungibile, anche senza passare da HTTP/controller.'
        );
    }

    public function test_cleanup_is_idempotent_when_the_file_was_already_removed(): void
    {
        $imageService = app(ImageService::class);
        $publicMediaSync = app(PublicMediaSyncService::class);

        $file = UploadedFile::fake()->image('diagnostica-idempotente.jpg', 800, 600);
        $uploadPath = public_path('assets/img/categories');

        $fullPath = $imageService->upload($file, $uploadPath, 'diagnostica-idempotente-test.jpg');

        unlink($fullPath);
        clearstatcache(true, $fullPath);

        // Non deve lanciare ne' tentare di rimuovere un file gia' assente:
        // cleanupAfterFailedCreate() esce subito dopo file_exists() === false.
        $publicMediaSync->cleanupAfterFailedCreate($fullPath);

        $this->assertFileDoesNotExist($fullPath);
    }
}
