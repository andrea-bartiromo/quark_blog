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
}
