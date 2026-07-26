<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Article;
use App\Models\Category;
use App\Models\Media;
use App\Models\SpecialPage;
use App\Models\User;
use App\Services\MediaUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaUsageServiceTest extends TestCase
{
    use RefreshDatabase;

    private MediaUsageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new MediaUsageService;
    }

    private function media(string $diskName): Media
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

    // 1. Media non utilizzato
    public function test_unused_media_reports_no_usage(): void
    {
        $media = $this->media('senza-usi.jpg');

        $this->assertSame([], $this->service->usageFor($media));
        $this->assertFalse($this->service->isUsed($media));
    }

    // 2. Media usato come copertina
    public function test_article_cover_image_is_detected_as_usage(): void
    {
        $media = $this->media('cover-diagnosi.jpg');
        $article = Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Diagnosi assistita da AI',
            'slug' => 'diagnosi-ai',
            'body' => 'Testo senza riferimenti.',
            'category' => 'scienza',
            'cover_image' => 'cover-diagnosi.jpg',
            'status' => 'published',
            'read_minutes' => 3,
            'verification_status' => 'unverified',
        ]);

        $usage = $this->service->usageFor($media);

        $this->assertCount(1, $usage);
        $this->assertSame('article_cover_image', $usage[0]['type']);
        $this->assertSame('Copertina', $usage[0]['usage_type_label']);
        $this->assertSame('Articolo', $usage[0]['content_type']);
        $this->assertSame('Diagnosi assistita da AI', $usage[0]['title']);
        $this->assertSame('Pubblicato', $usage[0]['status']);
        $this->assertSame(route('admin.articles.edit', $article), $usage[0]['edit_url']);
    }

    // 3. Media usato come hero (contenuto JSON di una pagina speciale)
    public function test_special_page_hero_image_is_detected_as_hero_usage(): void
    {
        $media = $this->media('turing/hero/hero-bg.webp');
        SpecialPage::create([
            'slug' => 'turing',
            'title' => 'Alan Turing',
            'content' => ['hero' => ['background_image' => 'turing/hero/hero-bg.webp']],
            'is_active' => true,
        ]);

        $usage = $this->service->usageFor($media);

        $this->assertCount(1, $usage);
        $this->assertSame('special_page_hero', $usage[0]['type']);
        $this->assertSame('Hero', $usage[0]['usage_type_label']);
        $this->assertSame('Pagina speciale', $usage[0]['content_type']);
        $this->assertSame('Alan Turing', $usage[0]['title']);
        $this->assertSame(route('admin.turing'), $usage[0]['edit_url']);
    }

    // 4. Media richiamato nel contenuto (body) di un articolo
    public function test_article_body_reference_is_detected_as_usage(): void
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

        $usage = $this->service->usageFor($media);

        $this->assertCount(1, $usage);
        $this->assertSame('article_body', $usage[0]['type']);
        $this->assertSame('Contenuto articolo', $usage[0]['usage_type_label']);
        $this->assertSame($article->title, $usage[0]['title']);
    }

    // Evita falsi positivi tra file dal nome simile (confronto esatto, non "contiene")
    public function test_similar_but_not_identical_disk_name_does_not_produce_a_false_positive(): void
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

        $this->assertSame([], $this->service->usageFor($media));
    }

    // 5. Conteggio corretto con più utilizzi sullo stesso file
    public function test_multiple_usages_of_the_same_media_are_all_counted(): void
    {
        $media = $this->media('multi-uso.jpg');
        Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Con copertina',
            'slug' => 'con-copertina',
            'body' => 'Testo qualsiasi.',
            'category' => 'scienza',
            'cover_image' => 'multi-uso.jpg',
            'status' => 'published',
            'read_minutes' => 2,
            'verification_status' => 'unverified',
        ]);
        Ad::create([
            'name' => 'Banner con stesso file',
            'position' => 'sidebar',
            'type' => 'banner',
            'active' => true,
            'priority' => 1,
            'banner_image' => 'multi-uso.jpg',
        ]);

        $usage = $this->service->usageFor($media);

        $this->assertCount(2, $usage);
        $types = collect($usage)->pluck('type')->all();
        $this->assertContains('article_cover_image', $types);
        $this->assertContains('ad_banner_image', $types);
    }

    // usageForMany: nessuna N+1 concettuale, batch corretto su piu media contemporaneamente
    public function test_usage_for_many_correctly_isolates_usage_per_media(): void
    {
        $used = $this->media('usato.jpg');
        $unused = $this->media('non-usato.jpg');
        Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo con copertina',
            'slug' => 'articolo-copertina',
            'body' => 'Testo qualsiasi.',
            'category' => 'scienza',
            'cover_image' => 'usato.jpg',
            'status' => 'published',
            'read_minutes' => 2,
            'verification_status' => 'unverified',
        ]);

        $map = $this->service->usageForMany([$used, $unused]);

        $this->assertCount(1, $map['usato.jpg']);
        $this->assertSame([], $map['non-usato.jpg']);
    }

    public function test_category_image_usage_is_detected_via_virtual_path(): void
    {
        $media = $this->media('categories/scienza.jpg');
        Category::create(['name' => 'Scienza', 'image' => 'scienza.jpg', 'is_active' => true]);

        $usage = $this->service->usageFor($media);

        $this->assertCount(1, $usage);
        $this->assertSame('category_image', $usage[0]['type']);
        $this->assertSame('Attiva', $usage[0]['status']);
    }

    public function test_user_photo_usage_is_detected(): void
    {
        $media = $this->media('author-9.jpg');
        User::factory()->create(['photo' => 'author-9.jpg', 'name' => 'Elena Romano']);

        $usage = $this->service->usageFor($media);

        $this->assertCount(1, $usage);
        $this->assertSame('user_photo', $usage[0]['type']);
        $this->assertSame('Elena Romano', $usage[0]['title']);
    }

    public function test_statically_protected_disk_name_is_reported_as_protected(): void
    {
        $protected = $this->media('placeholder-1.svg');
        $ordinary = $this->media('foto-libera.jpg');

        $this->assertTrue($this->service->isProtected($protected));
        $this->assertFalse($this->service->isProtected($ordinary));
    }

    public function test_protection_is_independent_from_content_usage(): void
    {
        // hero-placeholder.svg e protetto ma non ha (in questo test) alcun
        // riferimento in un contenuto dinamico: le due nozioni non vanno
        // confuse, "protetto" non implica "usato" e viceversa.
        $media = $this->media('hero-placeholder.svg');

        $this->assertTrue($this->service->isProtected($media));
        $this->assertSame([], $this->service->usageFor($media));
    }
}
