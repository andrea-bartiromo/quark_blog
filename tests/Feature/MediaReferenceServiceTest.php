<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Article;
use App\Models\Category;
use App\Models\Media;
use App\Models\SpecialPage;
use App\Models\User;
use App\Services\MediaReferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MediaReferenceServiceTest extends TestCase
{
    use RefreshDatabase;

    private MediaReferenceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new MediaReferenceService;
    }

    private function media(string $diskName = 'foto-abc123.jpg'): Media
    {
        $user = User::factory()->create();

        return Media::create([
            'user_id' => $user->id,
            'filename' => basename($diskName),
            'disk_name' => $diskName,
            'mime_type' => 'image/jpeg',
            'size' => 1000,
        ]);
    }

    public function test_media_without_any_usage_is_reported_as_informational_and_movable(): void
    {
        $media = $this->media('senza-usi.jpg');

        $result = $this->service->preflight($media, 'archivio/senza-usi.jpg');

        $this->assertTrue($result['can_move']);
        $this->assertSame(0, $result['total_usage_count']);
        $this->assertSame([], $result['updatable_references']);
        $this->assertSame([], $result['blocking_references']);
        $this->assertCount(1, $result['informational_references']);
        $this->assertSame('no_usage', $result['informational_references'][0]['type']);
    }

    public function test_article_cover_image_is_updatable(): void
    {
        $media = $this->media('cover-turing.jpg');
        $article = Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Un articolo',
            'slug' => 'un-articolo',
            'body' => 'Testo generico senza riferimenti.',
            'category' => 'scienza',
            'cover_image' => 'cover-turing.jpg',
            'status' => 'draft',
            'read_minutes' => 1,
            'verification_status' => 'unverified',
        ]);

        $result = $this->service->preflight($media, 'articles/covers/cover-turing.jpg');

        $this->assertTrue($result['can_move']);
        $this->assertCount(1, $result['updatable_references']);
        $ref = $result['updatable_references'][0];
        $this->assertSame('article_cover_image', $ref['type']);
        $this->assertSame($article->id, $ref['record_id']);
        $this->assertSame('articles/covers/cover-turing.jpg', $ref['new_value']);
    }

    public function test_ad_banner_image_is_updatable(): void
    {
        $media = $this->media('banner-sidebar.jpg');
        $ad = Ad::create([
            'name' => 'Banner test',
            'position' => 'sidebar',
            'type' => 'banner',
            'active' => true,
            'priority' => 10,
            'banner_image' => 'banner-sidebar.jpg',
        ]);

        $result = $this->service->preflight($media, 'ads/banner-sidebar.jpg');

        $this->assertTrue($result['can_move']);
        $this->assertCount(1, $result['updatable_references']);
        $this->assertSame('ad_banner_image', $result['updatable_references'][0]['type']);
        $this->assertSame($ad->id, $result['updatable_references'][0]['record_id']);
    }

    public function test_user_photo_in_media_library_format_is_updatable(): void
    {
        $media = $this->media('author-9-20260722.jpg');
        $user = User::factory()->create(['photo' => 'author-9-20260722.jpg']);

        $result = $this->service->preflight($media, 'authors/author-9-20260722.jpg');

        $this->assertTrue($result['can_move']);
        $this->assertCount(1, $result['updatable_references']);
        $this->assertSame('user_photo', $result['updatable_references'][0]['type']);
        $this->assertSame($user->id, $result['updatable_references'][0]['record_id']);
    }

    public function test_user_photo_in_storage_disk_format_is_blocking_as_ambiguous(): void
    {
        $media = $this->media('photos/ambiguo.jpg');
        User::factory()->create(['photo' => 'photos/ambiguo.jpg']);

        $result = $this->service->preflight($media, 'authors/ambiguo.jpg');

        $this->assertFalse($result['can_move']);
        $this->assertCount(1, $result['blocking_references']);
        $this->assertSame('user_photo', $result['blocking_references'][0]['type']);
        $this->assertNotNull($result['blocking_references'][0]['blocking_reason']);
    }

    public function test_category_image_moved_within_categories_folder_is_updatable(): void
    {
        $media = $this->media('categories/scienza.jpg');
        $category = Category::create(['name' => 'Scienza', 'image' => 'scienza.jpg']);

        $result = $this->service->preflight($media, 'categories/scienza-nuova.jpg');

        $this->assertTrue($result['can_move']);
        $this->assertCount(1, $result['updatable_references']);
        $ref = $result['updatable_references'][0];
        $this->assertSame('category_image', $ref['type']);
        $this->assertSame($category->id, $ref['record_id']);
        $this->assertSame('scienza-nuova.jpg', $ref['new_value']);
    }

    public function test_category_image_moved_outside_categories_folder_is_blocking(): void
    {
        $media = $this->media('categories/scienza.jpg');
        Category::create(['name' => 'Scienza', 'image' => 'scienza.jpg']);

        $result = $this->service->preflight($media, 'archivio/scienza.jpg');

        $this->assertFalse($result['can_move']);
        $this->assertCount(1, $result['blocking_references']);
        $this->assertSame('category_image', $result['blocking_references'][0]['type']);
    }

    #[DataProvider('supportedJsonPaths')]
    public function test_each_supported_special_page_json_key_is_updatable(string $jsonPath): void
    {
        $media = $this->media('turing/hero-bg.jpg');
        $content = [];
        data_set($content, $jsonPath, 'turing/hero-bg.jpg');

        $page = SpecialPage::create([
            'slug' => 'turing',
            'title' => 'Turing',
            'content' => $content,
            'is_active' => true,
        ]);

        $result = $this->service->preflight($media, 'turing/hero/hero-bg.jpg');

        $this->assertTrue($result['can_move'], 'can_move deve essere true per '.$jsonPath);
        $this->assertCount(1, $result['updatable_references']);
        $ref = $result['updatable_references'][0];
        $this->assertSame('special_page_content', $ref['type']);
        $this->assertSame($page->id, $ref['record_id']);
        $this->assertSame($jsonPath, $ref['json_path']);
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

    public function test_unknown_json_key_matching_old_disk_name_is_blocking(): void
    {
        $media = $this->media('turing/hero-bg.jpg');
        $page = SpecialPage::create([
            'slug' => 'turing',
            'title' => 'Turing',
            'content' => ['some_future_field' => 'turing/hero-bg.jpg'],
            'is_active' => true,
        ]);

        $result = $this->service->preflight($media, 'turing/hero/hero-bg.jpg');

        $this->assertFalse($result['can_move']);
        $this->assertCount(1, $result['blocking_references']);
        $ref = $result['blocking_references'][0];
        $this->assertSame('special_page_content', $ref['type']);
        $this->assertSame($page->id, $ref['record_id']);
    }

    public function test_article_body_containing_the_disk_name_is_blocking(): void
    {
        $media = $this->media('embed-nel-testo.jpg');
        $article = Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo con immagine inline',
            'slug' => 'articolo-inline',
            'body' => 'Guarda questa immagine: <img src="/assets/img/embed-nel-testo.jpg">',
            'category' => 'scienza',
            'status' => 'draft',
            'read_minutes' => 1,
            'verification_status' => 'unverified',
        ]);

        $result = $this->service->preflight($media, 'archivio/embed-nel-testo.jpg');

        $this->assertFalse($result['can_move']);
        $this->assertCount(1, $result['blocking_references']);
        $ref = $result['blocking_references'][0];
        $this->assertSame('article_body', $ref['type']);
        $this->assertSame($article->id, $ref['record_id']);
    }

    public function test_ad_html_code_containing_the_local_url_is_blocking(): void
    {
        $media = $this->media('promo.jpg');
        $ad = Ad::create([
            'name' => 'Promo con HTML',
            'position' => 'footer',
            'type' => 'html',
            'active' => true,
            'priority' => 5,
            'html_code' => '<img src="https://example.test/assets/img/promo.jpg">',
        ]);

        $result = $this->service->preflight($media, 'archivio/promo.jpg');

        $this->assertFalse($result['can_move']);
        $this->assertCount(1, $result['blocking_references']);
        $this->assertSame('ad_html_code', $result['blocking_references'][0]['type']);
        $this->assertSame($ad->id, $result['blocking_references'][0]['record_id']);
    }

    public function test_statically_protected_disk_name_is_blocking(): void
    {
        $media = $this->media('placeholder-1.jpg');

        $result = $this->service->preflight($media, 'archivio/placeholder-1.jpg');

        $this->assertFalse($result['can_move']);
        $blocking = collect($result['blocking_references']);
        $this->assertTrue($blocking->contains(fn ($ref) => $ref['type'] === 'static_reference'));
    }

    public function test_similar_but_not_identical_value_does_not_produce_a_false_positive(): void
    {
        $media = $this->media('cover.jpg');
        Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Altro articolo',
            'slug' => 'altro-articolo',
            'body' => 'Testo qualsiasi.',
            'category' => 'scienza',
            'cover_image' => 'cover-nuova.jpg',
            'status' => 'draft',
            'read_minutes' => 1,
            'verification_status' => 'unverified',
        ]);

        $result = $this->service->preflight($media, 'archivio/cover.jpg');

        $this->assertTrue($result['can_move']);
        $this->assertSame(0, $result['total_usage_count']);
    }

    public function test_total_usage_count_sums_updatable_and_blocking_references(): void
    {
        $media = $this->media('conteggio.jpg');
        Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Con copertina',
            'slug' => 'con-copertina',
            'body' => 'Testo qualsiasi.',
            'category' => 'scienza',
            'cover_image' => 'conteggio.jpg',
            'status' => 'draft',
            'read_minutes' => 1,
            'verification_status' => 'unverified',
        ]);
        Ad::create([
            'name' => 'Banner conteggio',
            'position' => 'sidebar',
            'type' => 'banner',
            'active' => true,
            'priority' => 1,
            'html_code' => 'riferimento inline a conteggio.jpg nel codice',
        ]);

        $result = $this->service->preflight($media, 'archivio/conteggio.jpg');

        $this->assertFalse($result['can_move']);
        $this->assertSame(2, $result['total_usage_count']);
        $this->assertCount(1, $result['updatable_references']);
        $this->assertCount(1, $result['blocking_references']);
    }
}
