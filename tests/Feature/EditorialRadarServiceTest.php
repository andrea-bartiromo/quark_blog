<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Services\EditorialRadar\EditorialRadarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorialRadarServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_explainable_multi_provider_opportunities_for_published_articles(): void
    {
        $article = Article::withoutEvents(fn () => Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo Radar Test',
            'slug' => 'articolo-radar-test',
            'excerpt' => '',
            'body' => '<p><a href="/articolo/target-mancante">Link rotto</a></p>',
            'category' => 'radar-test',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
            'read_minutes' => 1,
            'canonical_url' => 'not-an-absolute-url',
            'cover_image' => 'cover.jpg',
            'cover_alt' => 'Cover test',
            'cover_credit' => 'Autore',
            'cover_source' => 'Fonte',
        ]));

        $rows = app(EditorialRadarService::class)->opportunities();

        $this->assertTrue($rows->contains(fn (array $row) => $row['type'] === 'UPDATE_CONTENT' && $row['provider'] === 'content_health' && $row['article_id'] === $article->id));
        $this->assertTrue($rows->contains(fn (array $row) => $row['type'] === 'UPDATE_CONTENT' && $row['provider'] === 'attribution_health' && $row['article_id'] === $article->id));
        $this->assertTrue($rows->contains(fn (array $row) => $row['type'] === 'PERCORSO_OPPORTUNITY' && $row['article_id'] === $article->id));
        $this->assertTrue($rows->contains(fn (array $row) => $row['type'] === 'SEO_OPPORTUNITY' && $row['provider'] === 'seo_metadata' && $row['article_id'] === $article->id));
        $this->assertTrue($rows->contains(fn (array $row) => $row['type'] === 'INTERNAL_LINKING' && $row['provider'] === 'internal_link_audit' && $row['article_id'] === $article->id));
        $this->assertTrue($rows->every(fn (array $row) => isset($row['detected'], $row['why'], $row['suggested_action'], $row['priority'], $row['provider'])));
        $this->assertTrue($rows->every(fn (array $row) => ! array_key_exists('score', $row)));
    }

    public function test_draft_scheduled_and_future_published_articles_do_not_generate_article_opportunities(): void
    {
        $hiddenIds = [];
        foreach ([Article::STATUS_DRAFT, Article::STATUS_SCHEDULED] as $index => $status) {
            $hiddenIds[] = Article::withoutEvents(fn () => Article::create([
                'user_id' => User::factory()->create()->id,
                'title' => "Hidden Radar {$index}",
                'slug' => "hidden-radar-{$index}",
                'excerpt' => '',
                'body' => '<p>Hidden</p>',
                'category' => 'radar-test',
                'status' => $status,
                'published_at' => $status === Article::STATUS_SCHEDULED ? now()->addDay() : null,
                'read_minutes' => 1,
            ]))->id;
        }

        $hiddenIds[] = Article::withoutEvents(fn () => Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Future Published Flag',
            'slug' => 'future-published-flag',
            'excerpt' => '',
            'body' => '<p>Hidden</p>',
            'category' => 'radar-test',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->addDay(),
            'read_minutes' => 1,
        ]))->id;

        $rows = app(EditorialRadarService::class)->opportunities();

        $this->assertFalse($rows->contains(fn (array $row) => in_array($row['article_id'], $hiddenIds, true)));
    }

    public function test_opportunity_keys_are_unique_and_ordering_is_deterministic(): void
    {
        Article::withoutEvents(fn () => Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Deterministic Radar',
            'slug' => 'deterministic-radar',
            'excerpt' => '',
            'body' => '<p>Body</p>',
            'category' => 'radar-test',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
            'read_minutes' => 1,
        ]));

        $first = app(EditorialRadarService::class)->opportunities()->pluck('key')->all();
        $second = app(EditorialRadarService::class)->opportunities()->pluck('key')->all();

        $this->assertSame($first, $second);
        $this->assertSame($first, array_values(array_unique($first)));
    }
}
