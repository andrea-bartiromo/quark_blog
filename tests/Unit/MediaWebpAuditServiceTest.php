<?php

namespace Tests\Unit;

use App\Models\Ad;
use App\Models\Article;
use App\Models\Category;
use App\Models\Media;
use App\Models\User;
use App\Services\ImageService;
use App\Services\MediaReferenceService;
use App\Services\MediaWebpAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

class MediaWebpAuditServiceTest extends TestCase
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

    private function service(): MediaWebpAuditService
    {
        return new MediaWebpAuditService(new MediaReferenceService, new ImageService);
    }

    private function mediaDir(): string
    {
        return public_path('assets/img');
    }

    private function putFile(string $relativePath, int $width = 100, int $height = 100, string $ext = 'png'): string
    {
        $path = $this->mediaDir().'/'.$relativePath;
        @mkdir(dirname($path), 0775, true);

        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 120, 80, 200));

        match ($ext) {
            'png' => imagepng($image, $path),
            'jpg', 'jpeg' => imagejpeg($image, $path, 90),
            'webp' => imagewebp($image, $path, 90),
            'gif' => imagegif($image, $path),
        };
        imagedestroy($image);

        return $path;
    }

    private function media(string $diskName): Media
    {
        return Media::create([
            'user_id' => User::factory()->create()->id,
            'filename' => basename($diskName),
            'disk_name' => $diskName,
            'mime_type' => 'image/png',
            'size' => filesize($this->mediaDir().'/'.$diskName) ?: 0,
        ]);
    }

    // ── Classificazione base ─────────────────────────────────────────

    public function test_empty_directory_produces_an_empty_report(): void
    {
        $report = $this->service()->audit();

        $this->assertSame(0, $report['scanned_count']);
        $this->assertSame(0, $report['candidates']['count']);
    }

    public function test_gif_is_excluded_for_animation_preservation(): void
    {
        $this->putFile('anim.gif', ext: 'gif');
        $this->media('anim.gif');

        $report = $this->service()->audit(['measureActual' => false]);

        $this->assertSame(1, $report['excluded']['gif']['count']);
        $this->assertSame(0, $report['candidates']['count']);
    }

    public function test_already_webp_is_not_a_candidate(): void
    {
        $this->putFile('photo.webp', ext: 'webp');
        $this->media('photo.webp');

        $report = $this->service()->audit(['measureActual' => false]);

        $this->assertSame(1, $report['already_webp']['count']);
        $this->assertSame(0, $report['candidates']['count']);
    }

    public function test_turing_namespaced_file_is_excluded_regardless_of_media_record(): void
    {
        $this->putFile('turing/portraits/someone.png');
        // Nessun record Media creato deliberatamente: i file Turing non ne
        // hanno mai uno nella realta' (vedi docs/EDITORIAL_MEDIA_WEBP.md).

        $report = $this->service()->audit(['measureActual' => false]);

        $this->assertSame(1, $report['excluded']['turing_unmanaged']['count']);
        $this->assertSame(0, $report['candidates']['count']);
    }

    public function test_protected_disk_name_is_excluded(): void
    {
        Config::set('media.protected_disk_names', ['special/protected.png']);
        $this->putFile('special/protected.png');
        $this->media('special/protected.png');

        $report = $this->service()->audit(['measureActual' => false]);

        $this->assertSame(1, $report['excluded']['protected']['count']);
        $this->assertSame(0, $report['candidates']['count']);
    }

    public function test_file_without_media_record_requires_manual_review(): void
    {
        $this->putFile('unregistered.png');

        $report = $this->service()->audit(['measureActual' => false]);

        $this->assertSame(1, $report['excluded']['no_media_record']['count']);
        $this->assertSame(0, $report['candidates']['count']);
    }

    // ── Riuso di MediaReferenceService (nessuna seconda definizione) ───

    public function test_media_referenced_in_free_text_body_is_blocked_not_a_candidate(): void
    {
        $this->putFile('body-ref.png');
        $this->media('body-ref.png');

        Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo di test',
            'slug' => 'articolo-di-test-'.uniqid(),
            'body' => 'Testo che menziona body-ref.png nel contenuto.',
            'category' => 'scienza',
            'status' => 'draft',
            'read_minutes' => 1,
            'verification_status' => 'unverified',
        ]);

        $report = $this->service()->audit(['measureActual' => false]);

        $this->assertSame(1, $report['excluded']['blocked_references']['count']);
        $this->assertSame(0, $report['candidates']['count']);
        $this->assertStringContainsString('testo libero', strtolower($report['excluded']['blocked_references']['files'][0]['reason']));
    }

    public function test_media_referenced_only_as_article_cover_is_a_candidate(): void
    {
        $this->putFile('cover.png');
        $this->media('cover.png');

        Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo con copertina',
            'slug' => 'articolo-con-copertina-'.uniqid(),
            'body' => 'Corpo generico senza riferimenti al file.',
            'category' => 'scienza',
            'status' => 'draft',
            'read_minutes' => 1,
            'verification_status' => 'unverified',
            'cover_image' => 'cover.png',
        ]);

        $report = $this->service()->audit(['measureActual' => false]);

        $this->assertSame(1, $report['candidates']['count']);
        $this->assertSame('cover.png', $report['candidates']['files'][0]['relative_path']);
        $this->assertSame(1, $report['candidates']['files'][0]['updatable_reference_count']);
    }

    public function test_ad_banner_image_reference_is_updatable_and_a_candidate(): void
    {
        $this->putFile('banner.jpg', ext: 'jpg');
        $this->media('banner.jpg');

        Ad::create([
            'name' => 'Banner test',
            'position' => 'sidebar',
            'type' => 'banner',
            'active' => true,
            'priority' => 1,
            'banner_image' => 'banner.jpg',
        ]);

        $report = $this->service()->audit(['measureActual' => false]);

        $this->assertSame(1, $report['candidates']['count']);
    }

    public function test_category_image_correctly_placed_under_categories_is_a_candidate(): void
    {
        // Category.image memorizza solo il basename: il confronto interno
        // di MediaReferenceService lo ricostruisce come
        // "categories/{basename}", quindi solo un file gia' fisicamente
        // sotto categories/ puo' corrispondere. Una conversione di solo
        // formato (stessa directory, cambia solo l'estensione) non altera
        // mai quella directory, quindi per questo tipo di riferimento il
        // blocco "destinazione fuori da categories/" non e' raggiungibile
        // dalla pipeline di conversione — qui si verifica il percorso
        // realmente possibile: candidato regolare.
        $this->putFile('categories/icon.png');
        $this->media('categories/icon.png');

        Category::create([
            'name' => 'Categoria test',
            'slug' => 'categoria-test-'.uniqid(),
            'image' => 'icon.png',
        ]);

        $report = $this->service()->audit(['measureActual' => false]);

        $this->assertSame(1, $report['candidates']['count']);
        $this->assertSame(0, $report['excluded']['blocked_references']['count']);
    }

    // ── Filtri ───────────────────────────────────────────────────────

    public function test_path_filter_restricts_the_scan(): void
    {
        $this->putFile('categories/a.png');
        $this->media('categories/a.png');
        $this->putFile('other/b.png');
        $this->media('other/b.png');

        $report = $this->service()->audit(['path' => 'categories', 'measureActual' => false]);

        $this->assertSame(1, $report['scanned_count']);
    }

    public function test_only_filter_restricts_by_source_extension(): void
    {
        $this->putFile('a.png');
        $this->putFile('b.jpg', ext: 'jpg');

        $report = $this->service()->audit(['only' => ['png'], 'measureActual' => false]);

        $this->assertSame(1, $report['scanned_count']);
    }

    public function test_min_size_filter_excludes_small_files(): void
    {
        // Immagini a tinta unita comprimono benissimo in PNG (pochi byte
        // anche a risoluzioni non banali): la soglia va scelta in base al
        // peso reale dei fixture, non a un valore arbitrario che finirebbe
        // per escludere entrambi i file.
        $tinyPath = $this->putFile('tiny.png', 5, 5);
        $bigPath = $this->putFile('big.png', 500, 500);
        $threshold = filesize($tinyPath) + 1;
        $this->assertGreaterThan($threshold, filesize($bigPath), 'Precondizione fixture non valida per questo test.');

        $report = $this->service()->audit(['minSize' => $threshold, 'measureActual' => false]);

        $this->assertSame(1, $report['scanned_count']);
    }

    public function test_article_filter_restricts_to_that_articles_cover(): void
    {
        $this->putFile('cover-a.png');
        $this->media('cover-a.png');
        $this->putFile('cover-b.png');
        $this->media('cover-b.png');

        $article = Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo A',
            'slug' => 'articolo-a-'.uniqid(),
            'body' => 'Corpo.',
            'category' => 'scienza',
            'status' => 'draft',
            'read_minutes' => 1,
            'verification_status' => 'unverified',
            'cover_image' => 'cover-a.png',
        ]);

        $report = $this->service()->audit(['article' => (string) $article->id, 'measureActual' => false]);

        $this->assertSame(1, $report['scanned_count']);
        $this->assertSame(1, $report['candidates']['count']);
    }

    // ── Misura reale (temp dir, mai in produzione) ─────────────────────

    public function test_measures_actual_webp_size_without_touching_production_path(): void
    {
        $this->putFile('measured.png', 300, 300);
        $this->media('measured.png');

        $report = $this->service()->audit(['measureActual' => true]);

        $this->assertSame(1, $report['candidates']['count']);
        $this->assertNotNull($report['candidates']['files'][0]['estimated_webp_size_bytes']);
        $this->assertGreaterThan(0, $report['candidates']['files'][0]['estimated_webp_size_bytes']);

        // Nessun file .webp deve comparire nella directory media reale.
        $this->assertFileDoesNotExist($this->mediaDir().'/measured.webp');
    }

    public function test_no_measure_flag_skips_actual_measurement(): void
    {
        $this->putFile('unmeasured.png');
        $this->media('unmeasured.png');

        $report = $this->service()->audit(['measureActual' => false]);

        $this->assertSame(1, $report['candidates']['count']);
        $this->assertNull($report['candidates']['files'][0]['estimated_webp_size_bytes']);
    }

    // ── Duplicati sicuri ─────────────────────────────────────────────

    public function test_detects_byte_identical_duplicates_by_content_hash(): void
    {
        $path = $this->putFile('original.png', 150, 150);
        copy($path, $this->mediaDir().'/copy.png');

        $report = $this->service()->audit(['measureActual' => false]);

        $this->assertCount(1, $report['safe_duplicates']);
        $this->assertCount(2, $report['safe_duplicates'][0]['paths']);
    }

    public function test_same_size_but_different_content_is_not_a_duplicate(): void
    {
        $this->putFile('a.png', 150, 150);
        // Stessa dimensione pixel (quindi probabile stesso peso file per
        // un'immagine a tinta unita generata da GD) ma colore diverso:
        // deve rimanere un file diverso, mai un falso positivo.
        $path = $this->mediaDir().'/b.png';
        $image = imagecreatetruecolor(150, 150);
        imagefill($image, 0, 0, imagecolorallocate($image, 1, 2, 3));
        imagepng($image, $path);
        imagedestroy($image);

        $report = $this->service()->audit(['measureActual' => false]);

        // Se per coincidenza i due file pesano uguale, il confronto SHA-256
        // deve comunque distinguerli: nessun gruppo di duplicati deve
        // contenere entrambi i path. L'asserzione e' incondizionata (non
        // dentro un ciclo sull'array, che potrebbe risultare vuoto e far
        // "passare" il test senza aver verificato nulla).
        $matchingGroups = array_filter(
            $report['safe_duplicates'],
            fn (array $group) => in_array('a.png', $group['paths'], true) && in_array('b.png', $group['paths'], true)
        );

        $this->assertSame([], $matchingGroups, 'a.png e b.png hanno contenuto diverso: non devono comparire nello stesso gruppo di duplicati.');
    }

    // ── Missing media files ────────────────────────────────────────────

    public function test_media_record_without_file_is_reported_as_missing(): void
    {
        Media::create([
            'user_id' => User::factory()->create()->id,
            'filename' => 'ghost.png',
            'disk_name' => 'ghost.png',
            'mime_type' => 'image/png',
            'size' => 1,
        ]);

        $report = $this->service()->audit(['measureActual' => false]);

        $this->assertContains('ghost.png', $report['missing_media_files']);
    }
}
