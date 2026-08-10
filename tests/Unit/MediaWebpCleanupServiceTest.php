<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\Category;
use App\Models\Media;
use App\Models\User;
use App\Services\ImageService;
use App\Services\MediaReferenceService;
use App\Services\MediaWebpCleanupService;
use App\Services\PublicMediaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\UsesIsolatedMediaPublicRoot;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

/**
 * FASE 12 della missione WebP: MediaWebpCleanupService e' sola lettura al
 * 100% — nessun test qui deve mai scrivere o cancellare nulla tramite il
 * servizio stesso (solo i fixture di setup lo fanno, direttamente).
 */
class MediaWebpCleanupServiceTest extends TestCase
{
    use RefreshDatabase;
    use UsesIsolatedMediaPublicRoot;
    use UsesIsolatedPublicPath;

    private string $isolatedSourceDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedPublicPath();

        $this->isolatedSourceDir = sys_get_temp_dir().'/kairus-test-webp-cleanup-source-'.uniqid('', true);
        mkdir($this->isolatedSourceDir.'/views', 0775, true);
        Config::set('media.webp_cleanup_source_directories', [$this->isolatedSourceDir.'/views']);
    }

    protected function tearDown(): void
    {
        if (isset($this->isolatedMediaPublicRoot)) {
            $this->tearDownIsolatedMediaPublicRoot();
        }
        $this->tearDownIsolatedPublicPath();
        $this->deleteDirectory($this->isolatedSourceDir);
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

    private function service(): MediaWebpCleanupService
    {
        return new MediaWebpCleanupService(new MediaReferenceService, new ImageService, new PublicMediaSyncService);
    }

    private function mediaDir(): string
    {
        return public_path('assets/img');
    }

    private function putImage(string $relativePath, string $ext = 'jpg'): string
    {
        $path = $this->mediaDir().'/'.$relativePath;
        @mkdir(dirname($path), 0775, true);
        $image = imagecreatetruecolor(50, 50);
        imagefill($image, 0, 0, imagecolorallocate($image, 90, 60, 180));

        match ($ext) {
            'jpg', 'jpeg' => imagejpeg($image, $path, 90),
            'png' => imagepng($image, $path),
            'webp' => imagewebp($image, $path, 90),
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

    private function ageMedia(Media $media, int $days): void
    {
        $media->timestamps = false;
        $media->updated_at = now()->subDays($days);
        $media->save();
        $media->timestamps = true;
    }

    /**
     * Simula lo stato dopo una migrazione media:convert-webp riuscita:
     * l'originale resta sul disco, il Media punta ormai al .webp.
     */
    private function migratedPair(string $baseName, int $ageDays = 30): Media
    {
        $this->putImage($baseName.'.jpg');
        $this->putImage($baseName.'.webp', 'webp');
        $media = $this->media($baseName.'.webp', 'image/webp');
        $this->ageMedia($media, $ageDays);

        return $media;
    }

    // ── Candidato happy path ────────────────────────────────────────

    public function test_a_migrated_and_aged_original_is_a_candidate(): void
    {
        $this->migratedPair('photo', ageDays: 30);

        $report = $this->service()->scan();

        $this->assertSame(1, $report['candidates']['count']);
        $this->assertSame('photo.jpg', $report['candidates']['files'][0]['relative_path']);
        $this->assertSame('photo.webp', $report['candidates']['files'][0]['webp_disk_name']);
    }

    public function test_scan_never_writes_to_filesystem_or_database(): void
    {
        $media = $this->migratedPair('photo', ageDays: 30);
        $beforeFiles = array_diff(scandir($this->mediaDir()), ['.', '..']);
        $beforeUpdatedAt = $media->fresh()->updated_at;
        $beforeMediaCount = Media::count();

        $this->service()->scan();

        $afterFiles = array_diff(scandir($this->mediaDir()), ['.', '..']);
        sort($beforeFiles);
        sort($afterFiles);
        $this->assertSame($beforeFiles, $afterFiles);
        $this->assertEquals($beforeUpdatedAt, $media->fresh()->updated_at);
        $this->assertSame($beforeMediaCount, Media::count());
    }

    // ── Esclusioni ───────────────────────────────────────────────────

    public function test_original_without_a_webp_media_counterpart_is_not_a_candidate(): void
    {
        // Nessun record Media affatto: ne' per l'originale ne' per un
        // eventuale .webp — un file mai toccato dalla pipeline WebP.
        $this->putImage('never-migrated.jpg');

        $report = $this->service()->scan();

        $this->assertSame(0, $report['candidates']['count']);
        $this->assertSame(1, $report['excluded']['not_migrated']['count']);
    }

    public function test_original_still_owned_by_a_media_record_is_excluded(): void
    {
        // Caso limite: un Media punta ancora esattamente al disk_name
        // dell'originale (es. duplicato di contenuto con un webp
        // indipendente): non e' un residuo di QUESTA migrazione.
        $this->putImage('still-owned.jpg');
        $this->media('still-owned.jpg');
        $this->putImage('still-owned.webp', 'webp');
        $webpMedia = $this->media('still-owned.webp', 'image/webp');
        $this->ageMedia($webpMedia, 30);

        $report = $this->service()->scan();

        $this->assertSame(0, $report['candidates']['count']);
        $this->assertSame(1, $report['excluded']['still_referenced_by_media']['count']);
    }

    public function test_protected_disk_names_are_never_candidates(): void
    {
        Config::set('media.protected_disk_names', ['special/protected.jpg']);
        $this->migratedPair('special/protected', ageDays: 30);

        $report = $this->service()->scan();

        $this->assertSame(0, $report['candidates']['count']);
        $this->assertSame(1, $report['excluded']['protected']['count']);
    }

    public function test_turing_paths_are_never_candidates(): void
    {
        $this->putImage('turing/backgrounds/x.jpg');

        $report = $this->service()->scan();

        $this->assertSame(0, $report['candidates']['count']);
        $this->assertSame(1, $report['excluded']['turing_unmanaged']['count']);
    }

    public function test_a_missing_replacement_webp_file_is_never_a_candidate(): void
    {
        // Il piu' pericoloso dei casi da NON candidare: il Media dice che
        // il WebP esiste, ma il file e' sparito dal disco.
        $this->putImage('orphan.jpg');
        $media = Media::create([
            'user_id' => User::factory()->create()->id,
            'filename' => 'orphan.webp',
            'disk_name' => 'orphan.webp',
            'mime_type' => 'image/webp',
            'size' => 100,
        ]);
        $this->ageMedia($media, 30);

        $report = $this->service()->scan();

        $this->assertSame(0, $report['candidates']['count']);
        $this->assertSame(1, $report['excluded']['webp_missing_or_invalid']['count']);
    }

    public function test_a_corrupt_replacement_webp_file_is_never_a_candidate(): void
    {
        $this->putImage('corrupt.jpg');
        $corruptPath = $this->mediaDir().'/corrupt.webp';
        file_put_contents($corruptPath, 'not really a webp');
        $media = $this->media('corrupt.webp', 'image/webp');
        $this->ageMedia($media, 30);

        $report = $this->service()->scan();

        $this->assertSame(0, $report['candidates']['count']);
        $this->assertSame(1, $report['excluded']['webp_missing_or_invalid']['count']);
    }

    public function test_a_replacement_that_is_a_valid_image_but_not_actually_webp_is_never_a_candidate(): void
    {
        // getimagesize() valida "e' un'immagine decodificabile", non "e'
        // WebP": un file .webp che in realta' contiene un JPEG valido
        // (rinominato per errore, o corrotto in modo "morbido") non deve
        // mai passare come sostituto affidabile.
        $this->putImage('mislabeled.jpg');
        $this->putImage('mislabeled.webp', 'jpg');
        $media = $this->media('mislabeled.webp', 'image/webp');
        $this->ageMedia($media, 30);

        $report = $this->service()->scan();

        $this->assertSame(0, $report['candidates']['count']);
        $this->assertSame(1, $report['excluded']['webp_missing_or_invalid']['count']);
    }

    public function test_a_missing_copy_in_the_secondary_public_root_excludes_the_candidate(): void
    {
        $this->setUpIsolatedMediaPublicRoot();

        // Il WebP e' valido nella radice primaria, ma non e' mai stato
        // sincronizzato (o e' andato perso) nella radice pubblica
        // secondaria configurata: rimuovere l'originale lascerebbe il
        // sito, se servito da li', senza un sostituto reale.
        $this->migratedPair('drifted', ageDays: 30);

        $report = $this->service()->scan();

        $this->assertSame(0, $report['candidates']['count']);
        $this->assertSame(1, $report['excluded']['webp_missing_in_secondary_root']['count']);
    }

    public function test_a_synced_copy_in_the_secondary_public_root_allows_the_candidate(): void
    {
        $this->setUpIsolatedMediaPublicRoot();
        $this->migratedPair('synced', ageDays: 30);

        // Simula una sincronizzazione gia' avvenuta: stesso file presente
        // anche nella radice pubblica secondaria.
        file_put_contents(
            $this->isolatedMediaPublicRoot.'/synced.webp',
            file_get_contents($this->mediaDir().'/synced.webp')
        );

        $report = $this->service()->scan();

        $this->assertSame(1, $report['candidates']['count']);
    }

    public function test_a_structured_reference_without_an_owning_media_excludes_the_candidate(): void
    {
        // Caso limite che il solo confronto per nome file (originale vs
        // controparte .webp) non puo' escludere da solo: un campo
        // strutturato menziona ancora il nome dell'originale anche se
        // nessun Media possiede piu' quel disk_name (es. dato residuo,
        // o due file con lo stesso basename per puro caso).
        $this->migratedPair('still-linked', ageDays: 30);

        Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo con riferimento residuo',
            'slug' => 'articolo-residuo-'.uniqid(),
            'body' => 'Corpo generico senza menzioni testuali.',
            'category' => 'scienza',
            'status' => 'draft',
            'read_minutes' => 1,
            'verification_status' => 'unverified',
            'cover_image' => 'still-linked.jpg',
        ]);

        $report = $this->service()->scan();

        $this->assertSame(0, $report['candidates']['count']);
        $this->assertSame(1, $report['excluded']['structured_reference_without_media']['count']);
    }

    public function test_a_category_structured_reference_without_an_owning_media_excludes_the_candidate(): void
    {
        $this->migratedPair('categories/icon', ageDays: 30);

        Category::create([
            'name' => 'Categoria di test',
            'slug' => 'categoria-di-test-'.uniqid(),
            'image' => 'icon.jpg',
        ]);

        $report = $this->service()->scan();

        $this->assertSame(0, $report['candidates']['count']);
        $this->assertSame(1, $report['excluded']['structured_reference_without_media']['count']);
    }

    public function test_a_free_text_mention_excludes_the_candidate(): void
    {
        $this->migratedPair('body-ref', ageDays: 30);

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

        $report = $this->service()->scan();

        $this->assertSame(0, $report['candidates']['count']);
        $this->assertSame(1, $report['excluded']['free_text_reference']['count']);
    }

    public function test_a_static_blade_mention_excludes_the_candidate(): void
    {
        $this->migratedPair('hero-banner', ageDays: 30);

        file_put_contents(
            $this->isolatedSourceDir.'/views/welcome.blade.php',
            '<img src="{{ asset(\'assets/img/hero-banner.jpg\') }}">'
        );

        $report = $this->service()->scan();

        $this->assertSame(0, $report['candidates']['count']);
        $this->assertSame(1, $report['excluded']['static_source_reference']['count']);
    }

    public function test_an_unrelated_blade_file_does_not_exclude_the_candidate(): void
    {
        $this->migratedPair('untouched', ageDays: 30);

        file_put_contents(
            $this->isolatedSourceDir.'/views/other.blade.php',
            '<img src="{{ asset(\'assets/img/completely-different.jpg\') }}">'
        );

        $report = $this->service()->scan();

        $this->assertSame(1, $report['candidates']['count']);
        $this->assertSame(0, $report['excluded']['static_source_reference']['count']);
    }

    public function test_a_recently_migrated_original_is_not_yet_a_candidate(): void
    {
        $this->migratedPair('too-recent', ageDays: 2);

        $report = $this->service()->scan(['minAgeDays' => 14]);

        $this->assertSame(0, $report['candidates']['count']);
        $this->assertSame(1, $report['excluded']['observation_period_not_elapsed']['count']);
    }

    public function test_min_age_days_option_overrides_the_config_default(): void
    {
        $this->migratedPair('shorter-window', ageDays: 5);

        $report = $this->service()->scan(['minAgeDays' => 3]);

        $this->assertSame(1, $report['candidates']['count']);
    }

    public function test_path_filter_restricts_the_scan(): void
    {
        $this->migratedPair('categories/a', ageDays: 30);
        $this->migratedPair('other/b', ageDays: 30);

        $report = $this->service()->scan(['path' => 'categories']);

        $this->assertSame(1, $report['scanned_count']);
    }

    public function test_report_always_reminds_about_manual_backup_confirmation(): void
    {
        $report = $this->service()->scan();

        $this->assertTrue($report['backup_confirmation_required']);
    }
}
