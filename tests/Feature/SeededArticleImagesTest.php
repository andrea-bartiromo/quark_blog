<?php

namespace Tests\Feature;

use App\Models\Article;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeededArticleImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_article_cover_images_point_to_existing_assets(): void
    {
        $this->seed(DatabaseSeeder::class);

        Article::query()
            ->whereNotNull('cover_image')
            ->pluck('cover_image')
            ->each(function (string $coverImage): void {
                $this->assertFileExists(
                    public_path('assets/img/'.$coverImage),
                    "Seeded cover image [{$coverImage}] must exist in public/assets/img."
                );
            });
    }

    public function test_seeded_published_articles_use_distinct_webp_covers_instead_of_placeholders(): void
    {
        $this->seed(DatabaseSeeder::class);

        $coverImages = Article::query()
            ->where('status', 'published')
            ->pluck('cover_image');

        $this->assertCount(6, $coverImages);
        $this->assertCount(6, $coverImages->unique());
        $this->assertFalse($coverImages->contains('hero-placeholder.svg'));
        $this->assertFalse($coverImages->contains('placeholder-1.svg'));

        $coverImages->each(function (string $coverImage): void {
            $this->assertStringEndsWith('.webp', $coverImage);
            $this->assertFileExists(public_path('assets/img/'.$coverImage));
        });
    }
}
