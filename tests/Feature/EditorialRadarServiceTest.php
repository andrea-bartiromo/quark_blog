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

    public function test_it_exposes_explainable_update_and_percorso_opportunities_for_published_articles(): void
    {
        $article = Article::withoutEvents(fn () => Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo Radar Test',
            'slug' => 'articolo-radar-test',
            'excerpt' => '',
            'body' => '<p>Test body senza link interni.</p>',
            'category' => 'radar-test',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
            'read_minutes' => 1,
        ]));

        $rows = app(EditorialRadarService::class)->opportunities();

        $this->assertTrue($rows->contains(fn (array $row) => $row['type'] === 'UPDATE_CONTENT' && $row['article_id'] === $article->id));
        $this->assertTrue($rows->contains(fn (array $row) => $row['type'] === 'PERCORSO_OPPORTUNITY' && $row['article_id'] === $article->id));
        $this->assertTrue($rows->every(fn (array $row) => isset($row['detected'], $row['why'], $row['suggested_action'], $row['priority'])));
        $this->assertTrue($rows->every(fn (array $row) => ! array_key_exists('score', $row)));
    }

    public function test_draft_and_scheduled_articles_do_not_generate_article_health_opportunities(): void
    {
        foreach ([Article::STATUS_DRAFT, Article::STATUS_SCHEDULED] as $index => $status) {
            Article::withoutEvents(fn () => Article::create([
                'user_id' => User::factory()->create()->id,
                'title' => "Hidden Radar {$index}",
                'slug' => "hidden-radar-{$index}",
                'excerpt' => '',
                'body' => '<p>Hidden</p>',
                'category' => 'radar-test',
                'status' => $status,
                'published_at' => $status === Article::STATUS_SCHEDULED ? now()->addDay() : null,
                'read_minutes' => 1,
            ]));
        }

        $rows = app(EditorialRadarService::class)->opportunities();

        $this->assertFalse($rows->contains(fn (array $row) => $row['type'] === 'UPDATE_CONTENT'));
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
