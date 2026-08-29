<?php

namespace Tests\Feature\SocialDistribution;

use App\Events\ArticlePublished;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ArticlePublishedEventTest extends TestCase
{
    use RefreshDatabase;

    private function article(string $status, $publishedAt = null): Article
    {
        return Article::create([
            'user_id' => User::factory()->create(['role' => 'author'])->id,
            'title' => 'Evento pubblicazione '.uniqid(),
            'slug' => 'evento-pubblicazione-'.uniqid(),
            'excerpt' => 'Sommario',
            'body' => '<p>Corpo.</p>',
            'category' => 'tecnologia',
            'status' => $status,
            'published_at' => $publishedAt,
        ]);
    }

    public function test_manual_transition_dispatches_once_and_later_saves_do_not_repeat_it(): void
    {
        Event::fake([ArticlePublished::class]);
        $article = $this->article(Article::STATUS_DRAFT);

        $article->update(['status' => Article::STATUS_PUBLISHED]);
        $article->update(['title' => 'Titolo aggiornato dopo publish']);

        Event::assertDispatchedTimes(ArticlePublished::class, 1);
        Event::assertDispatched(ArticlePublished::class, fn ($event) =>
            $event->article->is($article) && str_starts_with($event->eventKey, 'article:'.$article->id.':published:')
        );
    }

    public function test_scheduler_transition_dispatches_the_same_event_once(): void
    {
        Event::fake([ArticlePublished::class]);
        $article = $this->article(Article::STATUS_SCHEDULED, now()->subMinute());

        $this->artisan('articles:publish-scheduled')->assertExitCode(0);

        $this->assertSame(Article::STATUS_PUBLISHED, $article->fresh()->status);
        Event::assertDispatchedTimes(ArticlePublished::class, 1);
    }

    public function test_draft_review_and_future_scheduled_saves_do_not_dispatch(): void
    {
        Event::fake([ArticlePublished::class]);

        $draft = $this->article(Article::STATUS_DRAFT);
        $draft->update(['status' => Article::STATUS_REVIEW]);
        $this->article(Article::STATUS_SCHEDULED, now()->addDay());

        Event::assertNotDispatched(ArticlePublished::class);
    }
}
