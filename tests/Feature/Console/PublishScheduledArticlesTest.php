<?php

namespace Tests\Feature\Console;

use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishScheduledArticlesTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $this->author()->id,
            'title' => 'Articolo',
            'slug' => 'articolo-'.uniqid(),
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_DRAFT,
        ], $overrides));
    }

    public function test_publishes_a_due_scheduled_article(): void
    {
        $article = $this->article([
            'title' => 'Articolo scaduto',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->subMinute(),
        ]);

        $this->artisan('articles:publish-scheduled')
            ->expectsOutputToContain('Pubblicati: 1')
            ->assertExitCode(0);

        $fresh = $article->fresh();
        $this->assertSame(Article::STATUS_PUBLISHED, $fresh->status);
    }

    public function test_keeps_the_originally_scheduled_published_at_instant(): void
    {
        $scheduledFor = now()->subMinutes(5)->startOfSecond();

        $article = $this->article([
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => $scheduledFor,
        ]);

        $this->artisan('articles:publish-scheduled')->assertExitCode(0);

        $this->assertSame(
            $scheduledFor->toIso8601String(),
            $article->fresh()->published_at->toIso8601String()
        );
    }

    public function test_does_not_touch_scheduled_articles_in_the_future(): void
    {
        $article = $this->article([
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDay(),
        ]);

        $this->artisan('articles:publish-scheduled')
            ->expectsOutputToContain('Nessun articolo programmato da pubblicare')
            ->assertExitCode(0);

        $this->assertSame(Article::STATUS_SCHEDULED, $article->fresh()->status);
    }

    public function test_does_not_touch_drafts_reviews_or_already_published_articles(): void
    {
        $draft = $this->article(['title' => 'Bozza', 'status' => Article::STATUS_DRAFT]);
        $review = $this->article(['title' => 'In revisione', 'status' => Article::STATUS_REVIEW]);
        $published = $this->article([
            'title' => 'Già pubblicato',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        $this->artisan('articles:publish-scheduled')
            ->expectsOutputToContain('Nessun articolo programmato da pubblicare')
            ->assertExitCode(0);

        $this->assertSame(Article::STATUS_DRAFT, $draft->fresh()->status);
        $this->assertSame(Article::STATUS_REVIEW, $review->fresh()->status);
        $this->assertSame(Article::STATUS_PUBLISHED, $published->fresh()->status);
    }

    public function test_running_twice_is_idempotent(): void
    {
        $article = $this->article([
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->subMinute(),
        ]);

        $this->artisan('articles:publish-scheduled')->assertExitCode(0);
        $publishedAtAfterFirstRun = $article->fresh()->published_at->toIso8601String();

        $this->artisan('articles:publish-scheduled')
            ->expectsOutputToContain('Nessun articolo programmato da pubblicare')
            ->assertExitCode(0);

        $fresh = $article->fresh();
        $this->assertSame(Article::STATUS_PUBLISHED, $fresh->status);
        $this->assertSame($publishedAtAfterFirstRun, $fresh->published_at->toIso8601String());

        // Un solo ActivityLog registrato, non uno per esecuzione.
        $this->assertSame(
            1,
            ActivityLog::where('subject_id', $article->id)
                ->where('action', 'Articolo pubblicato automaticamente (programmazione)')
                ->count()
        );
    }

    public function test_records_activity_log_with_null_user_for_system_action(): void
    {
        $article = $this->article([
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->subMinute(),
        ]);

        $this->artisan('articles:publish-scheduled')->assertExitCode(0);

        $log = ActivityLog::where('subject_id', $article->id)->firstOrFail();
        $this->assertNull($log->user_id);
    }

    public function test_publishes_multiple_due_articles_in_a_single_run(): void
    {
        $this->article(['title' => 'A', 'status' => Article::STATUS_SCHEDULED, 'published_at' => now()->subMinutes(3)]);
        $this->article(['title' => 'B', 'status' => Article::STATUS_SCHEDULED, 'published_at' => now()->subMinutes(2)]);
        $this->article(['title' => 'C', 'status' => Article::STATUS_SCHEDULED, 'published_at' => now()->addDay()]);

        $this->artisan('articles:publish-scheduled')
            ->expectsOutputToContain('Pubblicati: 2')
            ->assertExitCode(0);

        $this->assertSame(2, Article::where('status', Article::STATUS_PUBLISHED)->count());
    }
}
