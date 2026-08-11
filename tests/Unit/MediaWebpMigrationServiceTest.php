<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\Media;
use App\Models\User;
use App\Services\ImageService;
use App\Services\MediaReferenceService;
use App\Services\MediaWebpAuditService;
use App\Services\MediaWebpMigrationService;
use App\Services\PublicMediaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use RuntimeException;
use Tests\Concerns\UsesIsolatedMediaPublicRoot;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

/**
 * FASE 6/8 della missione WebP: MediaWebpMigrationService e' il servizio
 * che converte davvero un singolo Media legacy gia' esistente, mantenendo
 * sempre l'originale. Copre in particolare i failure mode espliciti della
 * missione: encoder assente, sorgente mancante/symlink/traversal,
 * collisione di destinazione, fallimento dopo la creazione del WebP
 * (rollback DB + rimozione del WebP orfano), idempotenza.
 */
class MediaWebpMigrationServiceTest extends TestCase
{
    use RefreshDatabase;
    use UsesIsolatedMediaPublicRoot;
    use UsesIsolatedPublicPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedPublicPath();
        $this->setUpIsolatedMediaPublicRoot();
    }

    protected function tearDown(): void
    {
        $this->tearDownIsolatedMediaPublicRoot();
        $this->tearDownIsolatedPublicPath();
        parent::tearDown();
    }

    private function service(
        ?MediaWebpAuditService $auditService = null,
        ?ImageService $imageService = null,
        ?PublicMediaSyncService $publicMediaSync = null,
    ): MediaWebpMigrationService {
        return new MediaWebpMigrationService(
            $auditService ?? new MediaWebpAuditService(new MediaReferenceService, new ImageService),
            $imageService ?? new ImageService,
            $publicMediaSync ?? new PublicMediaSyncService,
        );
    }

    private function mediaDir(): string
    {
        return public_path('assets/img');
    }

    private function putImage(string $relativePath, int $width = 100, int $height = 100, string $ext = 'jpg', bool $alpha = false): string
    {
        $path = $this->mediaDir().'/'.$relativePath;
        @mkdir(dirname($path), 0775, true);

        $image = imagecreatetruecolor($width, $height);

        if ($alpha) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $color = imagecolorallocatealpha($image, 10, 200, 10, 127);
        } else {
            $color = imagecolorallocate($image, 120, 80, 200);
        }
        imagefill($image, 0, 0, $color);

        match ($ext) {
            'png' => imagepng($image, $path),
            'jpg', 'jpeg' => imagejpeg($image, $path, 90),
            'webp' => imagewebp($image, $path, 90),
            'gif' => imagegif($image, $path),
        };
        imagedestroy($image);

        return $path;
    }

    private function media(string $diskName, string $mimeType = 'image/jpeg'): Media
    {
        return Media::create([
            'user_id' => User::factory()->create()->id,
            'filename' => basename($diskName),
            'disk_name' => $diskName,
            'mime_type' => $mimeType,
            'size' => filesize($this->mediaDir().'/'.$diskName) ?: 0,
        ]);
    }

    // ── plan() — sola lettura ────────────────────────────────────────

    public function test_plan_measures_real_webp_size_without_writing_to_production_dir(): void
    {
        $this->putImage('photo.jpg', 300, 200, 'jpg');
        $media = $this->media('photo.jpg');

        $result = $this->service()->plan($media);

        $this->assertSame('planned', $result->status);
        $this->assertSame('photo.webp', $result->newDiskName);
        $this->assertNotNull($result->webpBytes);
        $this->assertFileDoesNotExist($this->mediaDir().'/photo.webp', 'plan() e sola lettura: non deve mai scrivere in produzione.');
        $this->assertSame('photo.jpg', $media->fresh()->disk_name, 'plan() non deve mai modificare il database.');
    }

    public function test_plan_reports_missing_source_when_file_does_not_exist_on_disk(): void
    {
        $media = Media::create([
            'user_id' => User::factory()->create()->id,
            'filename' => 'ghost.jpg',
            'disk_name' => 'ghost.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 100,
        ]);

        $result = $this->service()->plan($media);

        $this->assertSame('missing_source', $result->status);
    }

    public function test_plan_reports_missing_source_when_source_is_a_symlink(): void
    {
        $this->putImage('real.jpg');
        $linkPath = $this->mediaDir().'/link.jpg';

        // symlink() richiede Developer Mode o privilegi elevati su Windows:
        // un ambiente senza quel privilegio non e' un bug del servizio, e'
        // un limite dell'ambiente stesso. "@" sopprime il warning nativo
        // cosi' che PHPUnit non lo converta in un'eccezione che farebbe
        // fallire il test per il motivo sbagliato — il comportamento reale
        // del servizio (rifiutare un sorgente symlink) resta verificato
        // ovunque symlink() sia effettivamente disponibile.
        if (! @symlink($this->mediaDir().'/real.jpg', $linkPath)) {
            $this->markTestSkipped('symlink() non disponibile in questo ambiente (su Windows richiede Developer Mode o privilegi elevati): impossibile verificare il rifiuto di un source symlink.');
        }

        $media = $this->media('link.jpg');

        $result = $this->service()->plan($media);

        $this->assertSame('missing_source', $result->status);
        $this->assertStringContainsString('collegamento simbolico', $result->reason);
    }

    public function test_plan_skips_gif_to_preserve_animation(): void
    {
        $this->putImage('anim.gif', ext: 'gif');
        $media = $this->media('anim.gif', 'image/gif');

        $result = $this->service()->plan($media);

        $this->assertSame('skipped_gif', $result->status);
    }

    public function test_plan_skips_protected_disk_names(): void
    {
        Config::set('media.protected_disk_names', ['special/protected.jpg']);
        $this->putImage('special/protected.jpg');
        $media = $this->media('special/protected.jpg');

        $result = $this->service()->plan($media);

        $this->assertSame('skipped_protected', $result->status);
    }

    public function test_plan_skips_turing_unmanaged_paths(): void
    {
        $this->putImage('turing/backgrounds/x.jpg');
        $media = $this->media('turing/backgrounds/x.jpg');

        $result = $this->service()->plan($media);

        $this->assertSame('skipped_turing_unmanaged', $result->status);
    }

    public function test_plan_skips_when_a_blocking_free_text_reference_exists(): void
    {
        $this->putImage('body-ref.jpg');
        $this->media('body-ref.jpg');

        Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo di test',
            'slug' => 'articolo-di-test-'.uniqid(),
            'body' => 'Testo che menziona body-ref.jpg nel contenuto.',
            'category' => 'scienza',
            'status' => 'draft',
            'read_minutes' => 1,
            'verification_status' => 'unverified',
        ]);

        $media = Media::where('disk_name', 'body-ref.jpg')->firstOrFail();
        $result = $this->service()->plan($media);

        $this->assertSame('skipped_blocked_references', $result->status);
    }

    public function test_plan_skips_when_the_webp_destination_already_exists(): void
    {
        $this->putImage('picture.jpg');
        $media = $this->media('picture.jpg');
        $this->putImage('picture.webp', ext: 'webp');

        $result = $this->service()->plan($media);

        $this->assertSame('skipped_webp_destination_conflict', $result->status);
    }

    public function test_plan_is_a_no_op_for_a_media_already_in_webp(): void
    {
        $this->putImage('already.webp', ext: 'webp');
        $media = $this->media('already.webp', 'image/webp');

        $result = $this->service()->plan($media);

        $this->assertSame('skipped_already_webp', $result->status);
    }

    // ── apply() — happy path ─────────────────────────────────────────

    public function test_apply_converts_jpg_replaces_media_reference_and_keeps_the_original(): void
    {
        $this->putImage('photo.jpg', 300, 200, 'jpg');
        $media = $this->media('photo.jpg');

        $result = $this->service()->apply($media->id);

        $this->assertSame('converted', $result->status);
        $this->assertSame('photo.webp', $result->newDiskName);
        $this->assertFileExists($this->mediaDir().'/photo.webp');
        $this->assertFileExists($this->mediaDir().'/photo.jpg', 'l\'originale non deve mai essere eliminato da questo servizio.');

        $fresh = $media->fresh();
        $this->assertSame('photo.webp', $fresh->disk_name);
        $this->assertSame('image/webp', $fresh->mime_type);
        $this->assertSame(filesize($this->mediaDir().'/photo.webp'), $fresh->size);
    }

    public function test_apply_preserves_transparency_for_png_sources(): void
    {
        $this->putImage('logo.png', 100, 100, 'png', alpha: true);
        $media = $this->media('logo.png', 'image/png');

        $result = $this->service()->apply($media->id);

        $this->assertSame('converted', $result->status);
        $decoded = imagecreatefromwebp($this->mediaDir().'/logo.webp');
        $this->assertNotFalse($decoded);
        $rgba = imagecolorsforindex($decoded, imagecolorat($decoded, 0, 0));
        $this->assertGreaterThan(0, $rgba['alpha']);
        imagedestroy($decoded);
    }

    public function test_apply_resizes_when_wider_than_the_configured_max_width(): void
    {
        Config::set('media.webp_max_width', 800);
        $this->putImage('wide.jpg', 2000, 1000, 'jpg');
        $media = $this->media('wide.jpg');

        $this->service()->apply($media->id);

        [$width, $height] = getimagesize($this->mediaDir().'/wide.webp');
        $this->assertSame(800, $width);
        $this->assertSame(400, $height);
    }

    public function test_apply_syncs_the_new_webp_file_to_the_secondary_public_root(): void
    {
        $this->putImage('synced.jpg');
        $media = $this->media('synced.jpg');

        $this->service()->apply($media->id);

        $this->assertFileExists($this->isolatedMediaPublicRoot.'/synced.webp');
    }

    public function test_apply_updates_the_article_cover_image_reference(): void
    {
        $this->putImage('cover.jpg');
        $this->media('cover.jpg');
        $article = Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo con copertina',
            'slug' => 'articolo-con-copertina-'.uniqid(),
            'body' => 'Corpo generico senza riferimenti al file.',
            'category' => 'scienza',
            'status' => 'draft',
            'read_minutes' => 1,
            'verification_status' => 'unverified',
            'cover_image' => 'cover.jpg',
        ]);

        $media = Media::where('disk_name', 'cover.jpg')->firstOrFail();
        $result = $this->service()->apply($media->id);

        $this->assertSame('converted', $result->status);
        $this->assertSame(1, $result->updatedReferenceCount);
        $this->assertSame('cover.webp', $article->fresh()->cover_image);
    }

    // ── apply() — idempotenza ────────────────────────────────────────

    public function test_apply_run_twice_is_idempotent_and_safe(): void
    {
        $this->putImage('idem.jpg');
        $media = $this->media('idem.jpg');

        $first = $this->service()->apply($media->id);
        $this->assertSame('converted', $first->status);

        $second = $this->service()->apply($media->id);

        $this->assertSame('skipped_already_webp', $second->status);
        $this->assertFileExists($this->mediaDir().'/idem.webp');
        $this->assertFileExists($this->mediaDir().'/idem.jpg');
        $this->assertSame('idem.webp', $media->fresh()->disk_name);
    }

    // ── apply() — failure modes ──────────────────────────────────────

    public function test_apply_returns_missing_source_and_writes_nothing_when_file_is_gone(): void
    {
        $media = Media::create([
            'user_id' => User::factory()->create()->id,
            'filename' => 'ghost.jpg',
            'disk_name' => 'ghost.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 100,
        ]);

        $result = $this->service()->apply($media->id);

        $this->assertSame('missing_source', $result->status);
        $this->assertSame('ghost.jpg', $media->fresh()->disk_name);
    }

    public function test_apply_falls_back_safely_when_the_source_is_corrupt(): void
    {
        $path = $this->mediaDir().'/broken.jpg';
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, 'not really a jpeg');
        $media = $this->media('broken.jpg');

        $result = $this->service()->apply($media->id);

        $this->assertSame('failed', $result->status);
        $this->assertFileExists($path, 'un fallimento di conversione non deve mai toccare il sorgente.');
        $this->assertFileDoesNotExist($this->mediaDir().'/broken.webp');
        $this->assertSame('broken.jpg', $media->fresh()->disk_name);
    }

    public function test_apply_rolls_back_cleanly_when_the_webp_encoder_is_unavailable(): void
    {
        $this->putImage('photo.jpg');
        $media = $this->media('photo.jpg');

        $brokenImageService = new class extends ImageService
        {
            public function convertToWebp(string $sourcePath, string $destinationPath, int $quality = 82, ?int $maxWidth = null): string
            {
                throw new RuntimeException('Questo build di GD non supporta la scrittura di file WebP.');
            }
        };

        $service = $this->service(imageService: $brokenImageService);

        $result = $service->apply($media->id);

        $this->assertSame('failed', $result->status);
        $this->assertFileExists($this->mediaDir().'/photo.jpg');
        $this->assertFileDoesNotExist($this->mediaDir().'/photo.webp');
        $this->assertSame('photo.jpg', $media->fresh()->disk_name);
    }

    public function test_apply_rolls_back_db_and_removes_the_orphaned_webp_when_reference_update_fails_after_creation(): void
    {
        $this->putImage('photo.jpg');
        $media = $this->media('photo.jpg');

        $service = new class(new MediaWebpAuditService(new MediaReferenceService, new ImageService), new ImageService, new PublicMediaSyncService) extends MediaWebpMigrationService
        {
            protected function applyReferenceUpdates(array $updatable): void
            {
                throw new RuntimeException('Simulazione di un aggiornamento riferimenti fallito.');
            }
        };

        $result = $service->apply($media->id);

        $this->assertSame('failed', $result->status);
        $this->assertFileExists($this->mediaDir().'/photo.jpg', 'l\'originale non deve mai sparire.');
        $this->assertFileDoesNotExist(
            $this->mediaDir().'/photo.webp',
            'il WebP generato ma reso orfano da un fallimento successivo deve essere rimosso, non lasciato accanto all\'originale.'
        );
        $this->assertSame('photo.jpg', $media->fresh()->disk_name, 'il rollback DB deve riportare il Media al disk_name originale.');
    }

    public function test_apply_removes_the_secondary_public_root_copy_when_rolling_back(): void
    {
        $this->putImage('photo.jpg');
        $media = $this->media('photo.jpg');

        $service = new class(new MediaWebpAuditService(new MediaReferenceService, new ImageService), new ImageService, new PublicMediaSyncService) extends MediaWebpMigrationService
        {
            protected function applyReferenceUpdates(array $updatable): void
            {
                throw new RuntimeException('Simulazione di un aggiornamento riferimenti fallito.');
            }
        };

        $service->apply($media->id);

        $this->assertFileDoesNotExist($this->isolatedMediaPublicRoot.'/photo.webp');
    }

    public function test_apply_never_deletes_a_secondary_root_file_that_preexisted_the_run(): void
    {
        $this->putImage('photo.jpg');
        $media = $this->media('photo.jpg');

        $secondaryPath = $this->isolatedMediaPublicRoot.'/photo.webp';
        @mkdir(dirname($secondaryPath), 0775, true);
        file_put_contents($secondaryPath, 'unrelated pre-existing content, not written by this migration');

        $result = $this->service()->apply($media->id);

        $this->assertSame('failed', $result->status);
        $this->assertFileExists(
            $secondaryPath,
            'un file preesistente nella radice pubblica secondaria non deve mai essere cancellato da un rollback, anche se il fallimento e\' successivo alla generazione del WebP.'
        );
        $this->assertSame(
            'unrelated pre-existing content, not written by this migration',
            file_get_contents($secondaryPath),
            'il contenuto del file preesistente non deve essere alterato.'
        );
    }

    public function test_apply_does_not_clobber_a_reference_changed_concurrently_after_preflight(): void
    {
        $this->putImage('cover.jpg');
        $this->media('cover.jpg');
        $article = Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo con copertina',
            'slug' => 'articolo-con-copertina-'.uniqid(),
            'body' => 'Corpo generico senza riferimenti al file.',
            'category' => 'scienza',
            'status' => 'draft',
            'read_minutes' => 1,
            'verification_status' => 'unverified',
            'cover_image' => 'cover.jpg',
        ]);
        $media = Media::where('disk_name', 'cover.jpg')->firstOrFail();

        $service = new class(new MediaWebpAuditService(new MediaReferenceService, new ImageService), new ImageService, new PublicMediaSyncService) extends MediaWebpMigrationService
        {
            public ?Article $articleToRaceEdit = null;

            protected function applyReferenceUpdates(array $updatable): void
            {
                // Simula un redattore che cambia la copertina esattamente
                // tra il preflight (gia' eseguito) e questa scrittura.
                $this->articleToRaceEdit?->update(['cover_image' => 'edited-concurrently.jpg']);

                parent::applyReferenceUpdates($updatable);
            }
        };
        $service->articleToRaceEdit = $article;

        $result = $service->apply($media->id);

        $this->assertSame('converted', $result->status);
        $this->assertSame(
            'edited-concurrently.jpg',
            $article->fresh()->cover_image,
            'un riferimento cambiato dopo il preflight non deve mai essere sovrascritto dal path migrato.'
        );
    }

    public function test_apply_rejects_a_destination_that_already_exists_as_a_file(): void
    {
        $this->putImage('photo.jpg');
        $media = $this->media('photo.jpg');
        $this->putImage('photo.webp', ext: 'webp');

        $result = $this->service()->apply($media->id);

        $this->assertSame('skipped_webp_destination_conflict', $result->status);
        $this->assertSame('photo.jpg', $media->fresh()->disk_name);
    }

    public function test_apply_rejects_a_destination_already_owned_by_another_media_record(): void
    {
        $this->putImage('photo.jpg');
        $media = $this->media('photo.jpg');
        $this->putImage('unrelated.jpg');
        $unrelated = $this->media('unrelated.jpg');
        $unrelated->update(['disk_name' => 'photo.webp']);

        $result = $this->service()->apply($media->id);

        $this->assertSame('skipped_webp_destination_conflict', $result->status);
    }

    public function test_apply_does_not_write_when_media_record_no_longer_exists(): void
    {
        $result = $this->service()->apply(999999);

        $this->assertSame('failed', $result->status);
    }
}
