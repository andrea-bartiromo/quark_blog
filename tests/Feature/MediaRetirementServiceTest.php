<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use App\Services\MediaRetirementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

/**
 * Copre MediaRetirementService in isolamento, in particolare il
 * comportamento quando la rimozione del file dalla directory primaria
 * (public/assets/img) fallisce davvero: il valore di ritorno di unlink()
 * non veniva controllato, lasciando un file orfano sul disco senza piu'
 * alcun record Media che lo tracciasse.
 */
class MediaRetirementServiceTest extends TestCase
{
    use RefreshDatabase;
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

    private function service(): MediaRetirementService
    {
        return app(MediaRetirementService::class);
    }

    private function unusedMedia(string $diskName, string $content = 'contenuto-non-referenziato'): Media
    {
        $user = User::factory()->create();
        $path = public_path('assets/img/'.$diskName);
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, $content);

        return Media::create([
            'user_id' => $user->id,
            'filename' => basename($diskName),
            'disk_name' => $diskName,
            'mime_type' => 'image/jpeg',
            'size' => strlen($content),
        ]);
    }

    public function test_retiring_an_unused_media_removes_the_file_and_the_record(): void
    {
        $media = $this->unusedMedia('da-ritirare.jpg');

        $result = $this->service()->retireIfUnused('da-ritirare.jpg', 'test_replaced');

        $this->assertTrue($result);
        $this->assertFileDoesNotExist(public_path('assets/img/da-ritirare.jpg'));
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    public function test_retirement_is_a_noop_when_the_disk_name_was_never_synced_locally(): void
    {
        // Nessun file scritto in public/assets/img: puo' capitare per un
        // disk_name mai arrivato localmente, o gia' ripulito in precedenza.
        // Non deve sollevare errori ne' bloccarsi.
        $result = $this->service()->retireIfUnused('mai-esistito.jpg', 'test_replaced');

        $this->assertTrue($result);
    }

    public function test_primary_root_unlink_failure_logs_a_warning_and_does_not_delete_the_media_record(): void
    {
        // Un vero fallimento di unlink() (a differenza di un fallimento di
        // permessi, non simulabile in modo affidabile qui perche' il
        // processo di test gira come root) viene simulato sostituendo il
        // file con una directory allo stesso path: unlink() su una
        // directory fallisce sempre, indipendentemente dai privilegi.
        $user = User::factory()->create();
        $diskName = 'bloccata.jpg';
        $path = public_path('assets/img/'.$diskName);
        mkdir($path, 0775, true);

        $media = Media::create([
            'user_id' => $user->id,
            'filename' => 'bloccata.jpg',
            'disk_name' => $diskName,
            'mime_type' => 'image/jpeg',
            'size' => 0,
        ]);

        Log::spy();

        $result = $this->service()->retireIfUnused($diskName, 'test_cleanup_failure');

        $this->assertFalse($result);

        // Nessuna modifica parziale: il record resta, esattamente come nel
        // caso gia' coperto del fallimento sulla radice pubblica secondaria.
        $this->assertDatabaseHas('media', ['id' => $media->id]);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context) use ($diskName, $path) {
                return $context['operation'] === 'test_cleanup_failure'
                    && $context['disk_name'] === $diskName
                    && $context['path'] === $path;
            });

        rmdir($path);
    }
}
