<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Article;
use App\Models\Category;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\SpecialPage;
use App\Models\User;
use App\Services\MediaClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

class MediaClassificationServiceTest extends TestCase
{
    use RefreshDatabase;
    use UsesIsolatedPublicPath;

    private MediaClassificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedPublicPath();
        $this->service = app(MediaClassificationService::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownIsolatedPublicPath();
        parent::tearDown();
    }

    private function mediaWithFile(string $diskName, string $content = 'fake-bytes'): Media
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

    private function folder(string $path): MediaFolder
    {
        @mkdir(public_path('assets/img/'.$path), 0775, true);

        return MediaFolder::create(['name' => ucfirst($path), 'slug' => basename($path), 'path' => $path]);
    }

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo di test',
            'slug' => 'articolo-di-test-'.uniqid(),
            'body' => 'Testo generico.',
            'category' => 'scienza',
            'status' => 'draft',
            'read_minutes' => 1,
            'verification_status' => 'unverified',
        ], $overrides));
    }

    public function test_article_cover_image_classifies_into_articles_covers(): void
    {
        $media = $this->mediaWithFile('cover.jpg');
        $this->article(['cover_image' => 'cover.jpg']);

        $result = $this->service->planFor($media);

        $this->assertSame('movable', $result->status);
        $this->assertSame('article', $result->domain);
        $this->assertSame('articles/covers', $result->proposedFolder);
        $this->assertSame('articles/covers/cover.jpg', $result->proposedDiskName);
    }

    public function test_ad_banner_image_classifies_into_ads(): void
    {
        $media = $this->mediaWithFile('banner.jpg');
        Ad::create([
            'name' => 'Banner test',
            'position' => 'sidebar',
            'type' => 'banner',
            'active' => true,
            'priority' => 1,
            'banner_image' => 'banner.jpg',
        ]);

        $result = $this->service->planFor($media);

        $this->assertSame('movable', $result->status);
        $this->assertSame('ad', $result->domain);
        $this->assertSame('ads', $result->proposedFolder);
    }

    public function test_category_image_already_in_categories_is_a_noop(): void
    {
        $media = $this->mediaWithFile('categories/scienza.jpg');
        Category::create(['name' => 'Scienza', 'image' => 'scienza.jpg']);

        $result = $this->service->planFor($media);

        $this->assertSame('noop', $result->status);
        $this->assertSame('category', $result->domain);
        $this->assertSame('categories/scienza.jpg', $result->proposedDiskName);
    }

    public function test_category_image_with_mismatched_implicit_prefix_is_not_detected(): void
    {
        // Media.disk_name non e sotto categories/: il match virtuale
        // 'categories/'.image === old non puo verificarsi per costruzione.
        $media = $this->mediaWithFile('scienza.jpg');
        Category::create(['name' => 'Scienza', 'image' => 'scienza.jpg']);

        $result = $this->service->planFor($media);

        $this->assertSame('unclassified', $result->status);
        $this->assertNull($result->domain);
    }

    public function test_user_photo_in_media_library_format_classifies_into_authors(): void
    {
        $media = $this->mediaWithFile('author-9-20260722.jpg');
        User::factory()->create(['photo' => 'author-9-20260722.jpg']);

        $result = $this->service->planFor($media);

        $this->assertSame('movable', $result->status);
        $this->assertSame('user', $result->domain);
        $this->assertSame('authors', $result->proposedFolder);
    }

    public function test_user_photo_in_ambiguous_storage_format_is_blocked(): void
    {
        $media = $this->mediaWithFile('photos/ambiguo.jpg');
        User::factory()->create(['photo' => 'photos/ambiguo.jpg']);

        $result = $this->service->planFor($media);

        $this->assertSame('blocked', $result->status);
    }

    public function test_special_page_content_uses_the_existing_slug_folder_when_present(): void
    {
        $this->folder('turing');
        $media = $this->mediaWithFile('hero-bg.jpg');
        SpecialPage::create([
            'slug' => 'turing',
            'title' => 'Turing',
            'content' => ['hero' => ['background_image' => 'hero-bg.jpg']],
            'is_active' => true,
        ]);

        $result = $this->service->planFor($media);

        $this->assertSame('movable', $result->status);
        $this->assertSame('special_page', $result->domain);
        $this->assertSame('turing', $result->proposedFolder);
    }

    public function test_special_page_content_falls_back_to_the_generic_folder_when_the_slug_folder_is_missing(): void
    {
        $media = $this->mediaWithFile('hero-bg.jpg');
        SpecialPage::create([
            'slug' => 'una-pagina-senza-cartella',
            'title' => 'Senza cartella',
            'content' => ['hero' => ['background_image' => 'hero-bg.jpg']],
            'is_active' => true,
        ]);

        $result = $this->service->planFor($media);

        $this->assertSame('movable', $result->status);
        $this->assertSame('special-pages', $result->proposedFolder);
    }

    public function test_unknown_json_key_is_blocked(): void
    {
        $media = $this->mediaWithFile('hero-bg.jpg');
        SpecialPage::create([
            'slug' => 'turing',
            'title' => 'Turing',
            'content' => ['campo_futuro_non_censito' => 'hero-bg.jpg'],
            'is_active' => true,
        ]);

        $result = $this->service->planFor($media);

        $this->assertSame('blocked', $result->status);
    }

    public function test_article_body_free_text_reference_is_blocked(): void
    {
        $media = $this->mediaWithFile('embed.jpg');
        $this->article(['body' => 'Vedi <img src="/assets/img/embed.jpg">']);

        $result = $this->service->planFor($media);

        $this->assertSame('blocked', $result->status);
    }

    public function test_static_protected_reference_is_blocked(): void
    {
        $media = $this->mediaWithFile('placeholder-1.jpg');

        $result = $this->service->planFor($media);

        $this->assertSame('blocked', $result->status);
    }

    public function test_media_without_any_reference_is_unclassified(): void
    {
        $media = $this->mediaWithFile('senza-usi.jpg');

        $result = $this->service->planFor($media);

        $this->assertSame('unclassified', $result->status);
        $this->assertSame('unclassified', $result->proposedFolder);
        $this->assertNull($result->proposedDiskName);
    }

    public function test_media_used_multiple_times_within_the_same_domain_is_still_movable(): void
    {
        $media = $this->mediaWithFile('cover-condivisa.jpg');
        $this->article(['cover_image' => 'cover-condivisa.jpg']);
        $this->article(['cover_image' => 'cover-condivisa.jpg']);

        $result = $this->service->planFor($media);

        $this->assertSame('movable', $result->status);
        $this->assertSame('article', $result->domain);
        $this->assertSame(2, $result->updatableCount);
    }

    public function test_media_used_in_incompatible_domains_is_ambiguous(): void
    {
        $media = $this->mediaWithFile('condivisa.jpg');
        $this->article(['cover_image' => 'condivisa.jpg']);
        Ad::create([
            'name' => 'Banner condiviso',
            'position' => 'sidebar',
            'type' => 'banner',
            'active' => true,
            'priority' => 1,
            'banner_image' => 'condivisa.jpg',
        ]);

        $result = $this->service->planFor($media);

        $this->assertSame('ambiguous', $result->status);
        $this->assertNull($result->domain);
    }

    public function test_media_in_the_wrong_folder_is_movable_to_the_correct_one(): void
    {
        $media = $this->mediaWithFile('categories/cover-sbagliata.jpg');
        $this->article(['cover_image' => 'categories/cover-sbagliata.jpg']);

        $result = $this->service->planFor($media);

        $this->assertSame('movable', $result->status);
        $this->assertSame('articles/covers/cover-sbagliata.jpg', $result->proposedDiskName);
    }

    public function test_root_media_is_classified_correctly(): void
    {
        $media = $this->mediaWithFile('root-cover.jpg');
        $this->article(['cover_image' => 'root-cover.jpg']);

        $result = $this->service->planFor($media);

        $this->assertNull($result->currentFolder);
        $this->assertSame('movable', $result->status);
    }

    public function test_missing_source_file_is_reported_without_side_effects(): void
    {
        $media = Media::create([
            'user_id' => User::factory()->create()->id,
            'filename' => 'fantasma.jpg',
            'disk_name' => 'fantasma.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 10,
        ]);
        $this->article(['cover_image' => 'fantasma.jpg']);

        $result = $this->service->planFor($media);

        $this->assertSame('missing_source', $result->status);
    }

    public function test_database_collision_on_the_proposed_disk_name_is_reported(): void
    {
        $media = $this->mediaWithFile('cover-collisione.jpg');
        $this->article(['cover_image' => 'cover-collisione.jpg']);
        $this->mediaWithFile('articles/covers/cover-collisione.jpg');

        $result = $this->service->planFor($media);

        $this->assertSame('collision', $result->status);
    }

    public function test_filesystem_collision_without_a_db_record_is_reported(): void
    {
        $media = $this->mediaWithFile('cover-fs.jpg');
        $this->article(['cover_image' => 'cover-fs.jpg']);
        @mkdir(public_path('assets/img/articles/covers'), 0775, true);
        file_put_contents(public_path('assets/img/articles/covers/cover-fs.jpg'), 'orfano');

        $result = $this->service->planFor($media);

        $this->assertSame('collision', $result->status);
    }

    public function test_dry_run_planning_never_writes_to_disk_or_database(): void
    {
        $media = $this->mediaWithFile('non-toccare.jpg');
        $this->article(['cover_image' => 'non-toccare.jpg']);

        $this->service->planFor($media);

        $this->assertSame('non-toccare.jpg', $media->fresh()->disk_name);
        $this->assertFileExists(public_path('assets/img/non-toccare.jpg'));
        $this->assertFileDoesNotExist(public_path('assets/img/articles/covers/non-toccare.jpg'));
        $this->assertDatabaseMissing('media_folders', ['path' => 'articles/covers']);
    }

    public function test_ensure_target_folder_creates_a_missing_folder_idempotently(): void
    {
        $media = $this->mediaWithFile('banner-nuovo.jpg');
        Ad::create([
            'name' => 'Banner nuovo',
            'position' => 'sidebar',
            'type' => 'banner',
            'active' => true,
            'priority' => 1,
            'banner_image' => 'banner-nuovo.jpg',
        ]);
        $result = $this->service->planFor($media);

        $this->assertDatabaseMissing('media_folders', ['path' => 'ads']);

        $folder = $this->service->ensureTargetFolder($result);
        $this->assertNotNull($folder);
        $this->assertSame('ads', $folder->path);
        $this->assertDirectoryExists(public_path('assets/img/ads'));

        $again = $this->service->ensureTargetFolder($result);
        $this->assertSame($folder->id, $again->id);
        $this->assertSame(1, MediaFolder::where('path', 'ads')->count());
    }

    public function test_ensure_target_folder_does_nothing_for_a_non_movable_result(): void
    {
        $media = $this->mediaWithFile('senza-usi-2.jpg');
        $result = $this->service->planFor($media);

        $this->assertSame('unclassified', $result->status);
        $this->assertNull($this->service->ensureTargetFolder($result));
        $this->assertDatabaseCount('media_folders', 0);
    }

    public function test_find_unregistered_files_lists_files_without_a_media_record(): void
    {
        @mkdir(public_path('assets/img'), 0775, true);
        file_put_contents(public_path('assets/img/orfano.jpg'), 'x');
        $this->mediaWithFile('registrato.jpg');

        $orphans = $this->service->findUnregisteredFiles();

        $paths = array_column($orphans, 'path');
        $this->assertContains('orfano.jpg', $paths);
        $this->assertNotContains('registrato.jpg', $paths);
    }

    #[DataProvider('supportedJsonPaths')]
    public function test_each_supported_json_key_leads_to_a_movable_result(string $jsonPath): void
    {
        $this->folder('turing');
        $media = $this->mediaWithFile('immagine.jpg');
        $content = [];
        data_set($content, $jsonPath, 'immagine.jpg');

        SpecialPage::create([
            'slug' => 'turing',
            'title' => 'Turing',
            'content' => $content,
            'is_active' => true,
        ]);

        $result = $this->service->planFor($media);

        $this->assertSame('movable', $result->status, 'movable atteso per '.$jsonPath);
        $this->assertSame('special_page', $result->domain);
    }

    public static function supportedJsonPaths(): array
    {
        return [
            'hero.background_image' => ['hero.background_image'],
            'hero.portrait_image' => ['hero.portrait_image'],
            'home_teaser.background_image' => ['home_teaser.background_image'],
            'intro.background_image' => ['intro.background_image'],
            'why.background_image' => ['why.background_image'],
            'final.background_image' => ['final.background_image'],
            'cards.0.image' => ['cards.0.image'],
            'editorial_blocks.0.image' => ['editorial_blocks.0.image'],
            'editorial_blocks.0.background_image' => ['editorial_blocks.0.background_image'],
            'internal_links.0.image' => ['internal_links.0.image'],
            'decorative_images.0.image' => ['decorative_images.0.image'],
            'why.items.0.image' => ['why.items.0.image'],
            'timeline.0.image' => ['timeline.0.image'],
        ];
    }
}
