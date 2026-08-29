<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleContinuationEvent;
use App\Services\EditorialRadar\Providers\SecondReadOpportunityProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecondReadRadarProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_weak_signal_is_suppressed_and_explainable_threshold_is_emitted(): void
    {
        $author = \App\Models\User::factory()->create(['role' => 'author']);
        $source = Article::withoutEvents(fn () => Article::create([
            'user_id' => $author->id, 'title' => 'Second Read source',
            'slug' => 'second-read-source', 'body' => '<p>Corpo.</p>',
            'category' => 'tecnologia', 'status' => Article::STATUS_PUBLISHED,
            'published_at' => now(),
        ]));
        $target = Article::withoutEvents(fn () => Article::create([
            'user_id' => $author->id, 'title' => 'Second Read target',
            'slug' => 'second-read-target', 'body' => '<p>Corpo.</p>',
            'category' => 'tecnologia', 'status' => Article::STATUS_PUBLISHED,
            'published_at' => now(),
        ]));

        for ($i = 0; $i < 19; $i++) {
            ArticleContinuationEvent::create(['source_article_id' => $source->id, 'target_article_id' => $target->id, 'event_type' => ArticleContinuationEvent::EVENT_IMPRESSION]);
        }
        $this->assertTrue(app(SecondReadOpportunityProvider::class)->opportunities()->isEmpty());

        ArticleContinuationEvent::create(['source_article_id' => $source->id, 'target_article_id' => $target->id, 'event_type' => ArticleContinuationEvent::EVENT_IMPRESSION]);
        $row = app(SecondReadOpportunityProvider::class)->opportunities()->first();

        $this->assertSame('SECOND_READ', $row['type']);
        $this->assertSame(20, $row['evidence']['impressions']);
        $this->assertStringContainsString('0 second read su 20 impression', $row['why']);
        $this->assertStringContainsString('manualmente', $row['suggested_action']);
    }

    public function test_output_limit_is_applied_after_threshold_selection(): void
    {
        $author = \App\Models\User::factory()->create(['role' => 'author']);
        $createArticle = fn (string $slug) => Article::withoutEvents(fn () => Article::create([
            'user_id' => $author->id,
            'title' => $slug,
            'slug' => $slug,
            'body' => '<p>Corpo.</p>',
            'category' => 'tecnologia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now(),
        ]));

        $weakSource = $createArticle('qualifying-weak-source');
        $target = $createArticle('threshold-target');

        for ($i = 0; $i < 100; $i++) {
            ArticleContinuationEvent::create([
                'source_article_id' => $weakSource->id,
                'target_article_id' => $target->id,
                'event_type' => ArticleContinuationEvent::EVENT_IMPRESSION,
            ]);
        }

        for ($i = 0; $i < 50; $i++) {
            $distractor = $createArticle('non-qualifying-'.$i);
            ArticleContinuationEvent::create([
                'source_article_id' => $distractor->id,
                'target_article_id' => $target->id,
                'event_type' => ArticleContinuationEvent::EVENT_SECOND_READ_START,
            ]);
        }

        $rows = app(SecondReadOpportunityProvider::class)->opportunities();

        $this->assertTrue($rows->contains('article_id', $weakSource->id));
    }
}
