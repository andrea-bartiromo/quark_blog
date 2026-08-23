<?php

namespace Tests\Feature\Discover;

use App\Models\Article;
use App\Models\User;
use App\Services\Discover\DiscoverReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscoverReadinessV2Test extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create(['name' => 'Redattrice Kairus']);
    }

    public function test_complete_article_produces_all_pass_checks_without_score(): void
    {
        $path = $this->writeCoverFile($filename = $this->uniqueCoverName(), 1600, 900);

        try {
            $article = $this->publishedArticle([
                'cover_image' => $filename,
                'seo_title' => 'Titolo SEO',
                'seo_description' => 'Descrizione SEO',
            ]);

            $checks = $this->service()->evaluate($article);

            $this->assertTrue($checks->every(fn (array $check) => array_keys($check) === [
                'id', 'label', 'status', 'reason', 'action_url',
            ]));
            $this->assertFalse($checks->pluck('id')->contains('score'));
            $this->assertTrue($checks->every(fn (array $check) => $check['status'] === DiscoverReadinessService::STATUS_PASS));
            $this->assertSame(
                ['publication_state', 'cover_image', 'cover_file_integrity', 'cover_image_size', 'author', 'seo_metadata'],
                $checks->pluck('id')->all()
            );
        } finally {
            @unlink($path);
        }
    }

    public function test_missing_cover_image_is_an_error_and_skips_dependent_checks(): void
    {
        $article = $this->publishedArticle(['cover_image' => null]);

        $checks = $this->service()->evaluate($article);

        $this->assertSame(DiscoverReadinessService::STATUS_ERROR, $checks->firstWhere('id', 'cover_image')['status']);
        $this->assertNull($checks->firstWhere('id', 'cover_file_integrity'));
        $this->assertNull($checks->firstWhere('id', 'cover_image_size'));
    }

    public function test_cover_file_missing_on_disk_is_reported_as_a_mismatch_error(): void
    {
        $article = $this->publishedArticle(['cover_image' => 'copertina-inesistente-'.uniqid().'.jpg']);

        $checks = $this->service()->evaluate($article);

        $this->assertSame(DiscoverReadinessService::STATUS_PASS, $checks->firstWhere('id', 'cover_image')['status']);
        $this->assertSame(DiscoverReadinessService::STATUS_ERROR, $checks->firstWhere('id', 'cover_file_integrity')['status']);
        $this->assertNull($checks->firstWhere('id', 'cover_image_size'));
    }

    public function test_cover_image_below_the_large_image_threshold_is_a_warning_not_an_error(): void
    {
        $path = $this->writeCoverFile($filename = $this->uniqueCoverName(), 800, 450);

        try {
            $article = $this->publishedArticle(['cover_image' => $filename]);

            $checks = $this->service()->evaluate($article);

            $this->assertSame(DiscoverReadinessService::STATUS_PASS, $checks->firstWhere('id', 'cover_file_integrity')['status']);
            $this->assertSame(DiscoverReadinessService::STATUS_WARNING, $checks->firstWhere('id', 'cover_image_size')['status']);
            $this->assertStringContainsString('800px', $checks->firstWhere('id', 'cover_image_size')['reason']);
        } finally {
            @unlink($path);
        }
    }

    public function test_cover_image_at_exactly_the_threshold_passes(): void
    {
        $path = $this->writeCoverFile($filename = $this->uniqueCoverName(), DiscoverReadinessService::MIN_LARGE_IMAGE_WIDTH, 700);

        try {
            $article = $this->publishedArticle(['cover_image' => $filename]);

            $checks = $this->service()->evaluate($article);

            $this->assertSame(DiscoverReadinessService::STATUS_PASS, $checks->firstWhere('id', 'cover_image_size')['status']);
        } finally {
            @unlink($path);
        }
    }

    public function test_missing_author_name_is_an_error(): void
    {
        $blankNameAuthor = User::factory()->create(['name' => '']);
        $article = $this->publishedArticle([], $blankNameAuthor);

        $checks = $this->service()->evaluate($article);

        $this->assertSame(DiscoverReadinessService::STATUS_ERROR, $checks->firstWhere('id', 'author')['status']);
    }

    public function test_scheduled_article_fails_the_publication_state_check(): void
    {
        $article = $this->article([
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDay(),
        ]);

        $checks = $this->service()->evaluate($article);

        $this->assertSame(DiscoverReadinessService::STATUS_ERROR, $checks->firstWhere('id', 'publication_state')['status']);
        $this->assertStringContainsString('programmato', $checks->firstWhere('id', 'publication_state')['reason']);
    }

    public function test_draft_article_fails_the_publication_state_check(): void
    {
        $article = $this->article(['status' => Article::STATUS_DRAFT]);

        $checks = $this->service()->evaluate($article);

        $this->assertSame(DiscoverReadinessService::STATUS_ERROR, $checks->firstWhere('id', 'publication_state')['status']);
    }

    public function test_missing_seo_metadata_is_a_warning_reused_from_content_health(): void
    {
        $article = $this->publishedArticle(['seo_title' => null, 'seo_description' => null]);

        $checks = $this->service()->evaluate($article);

        $this->assertSame(DiscoverReadinessService::STATUS_WARNING, $checks->firstWhere('id', 'seo_metadata')['status']);
    }

    public function test_never_declares_notapplicable_or_any_status_outside_the_three_documented(): void
    {
        $article = $this->publishedArticle(['cover_image' => null]);

        $checks = $this->service()->evaluate($article);

        $this->assertTrue($checks->pluck('status')->every(fn (string $status) => in_array($status, [
            DiscoverReadinessService::STATUS_PASS,
            DiscoverReadinessService::STATUS_WARNING,
            DiscoverReadinessService::STATUS_ERROR,
        ], true)));
    }

    public function test_admin_route_renders_the_checklist(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $article = $this->publishedArticle(['cover_image' => null]);

        $response = $this->actingAs($editor)->get(route('admin.articles.discover', $article));

        $response->assertOk();
        $response->assertSeeText('Idoneità Google Discover');
        $response->assertSeeText('ERROR');
    }

    public function test_admin_route_requires_authentication(): void
    {
        $article = $this->publishedArticle();

        $response = $this->get(route('admin.articles.discover', $article));

        $response->assertRedirect(route('login'));
    }

    private function service(): DiscoverReadinessService
    {
        return app(DiscoverReadinessService::class);
    }

    private function uniqueCoverName(): string
    {
        return 'discover-readiness-'.uniqid().'.jpg';
    }

    private function writeCoverFile(string $filename, int $width, int $height): string
    {
        $path = public_path('assets/img/'.$filename);
        $image = imagecreatetruecolor($width, $height);
        imagejpeg($image, $path, 90);
        imagedestroy($image);

        return $path;
    }

    private function publishedArticle(array $overrides = [], ?User $author = null): Article
    {
        return $this->article(array_merge([
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ], $overrides), $author);
    }

    private function article(array $overrides = [], ?User $author = null): Article
    {
        $title = $overrides['title'] ?? 'Articolo Discover Readiness';

        return Article::create(array_merge([
            'user_id' => ($author ?? $this->author)->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'excerpt' => 'Sommario editoriale.',
            'body' => '<p>Corpo articolo.</p>',
            'category' => 'fisica',
            'status' => Article::STATUS_DRAFT,
            'read_minutes' => 2,
        ], $overrides));
    }
}
