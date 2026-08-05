<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ArticleSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function baseAttributes(User $author, array $overrides = []): array
    {
        return array_merge([
            'user_id' => $author->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Sommario di prova',
            'body' => 'Corpo articolo di prova.',
            'category' => 'intelligenza-artificiale',
            'cover_image' => 'copertina.jpg',
            'status' => Article::STATUS_DRAFT,
        ], $overrides);
    }

    public function test_published_at_is_forced_to_null_for_draft(): void
    {
        $article = Article::create($this->baseAttributes($this->author(), [
            'status' => Article::STATUS_DRAFT,
            'published_at' => now(), // deve essere ignorato dal model
        ]));

        $this->assertNull($article->fresh()->published_at);
    }

    public function test_published_at_is_forced_to_null_for_review(): void
    {
        $article = Article::create($this->baseAttributes($this->author(), [
            'status' => Article::STATUS_REVIEW,
            'published_at' => now(),
        ]));

        $this->assertNull($article->fresh()->published_at);
    }

    public function test_published_at_defaults_to_now_when_publishing_without_a_date(): void
    {
        $article = Article::create($this->baseAttributes($this->author(), [
            'status' => Article::STATUS_PUBLISHED,
        ]));

        $this->assertNotNull($article->fresh()->published_at);
        $this->assertTrue($article->fresh()->published_at->diffInSeconds(now()) < 5);
    }

    public function test_scheduled_article_keeps_its_future_published_at(): void
    {
        $future = now()->addDays(3);

        $article = Article::create($this->baseAttributes($this->author(), [
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => $future,
        ]));

        $this->assertSame($future->format('Y-m-d H:i:s'), $article->fresh()->published_at->format('Y-m-d H:i:s'));
    }

    public function test_reverting_a_scheduled_article_to_draft_clears_published_at(): void
    {
        $article = Article::create($this->baseAttributes($this->author(), [
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDay(),
        ]));

        $article->update(['status' => Article::STATUS_DRAFT]);

        $this->assertNull($article->fresh()->published_at);
    }

    public function test_scope_published_excludes_scheduled_articles_even_if_overdue(): void
    {
        $author = $this->author();

        $scheduled = Article::create($this->baseAttributes($author, [
            'title' => 'Programmato nel passato',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->subMinute(),
        ]));

        $published = Article::create($this->baseAttributes($author, [
            'title' => 'Pubblicato',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
        ]));

        $ids = Article::published()->pluck('id');

        $this->assertTrue($ids->contains($published->id));
        $this->assertFalse($ids->contains($scheduled->id));
    }

    public function test_scope_published_excludes_scheduled_articles_with_future_date(): void
    {
        $author = $this->author();

        Article::create($this->baseAttributes($author, [
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDays(2),
        ]));

        $this->assertSame(0, Article::published()->count());
    }

    public function test_scope_scheduled_returns_only_scheduled_articles_ordered_by_date(): void
    {
        $author = $this->author();

        $later = Article::create($this->baseAttributes($author, [
            'title' => 'Programmato più tardi',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDays(5),
        ]));

        $sooner = Article::create($this->baseAttributes($author, [
            'title' => 'Programmato prima',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDay(),
        ]));

        Article::create($this->baseAttributes($author, [
            'title' => 'Bozza',
            'status' => Article::STATUS_DRAFT,
        ]));

        $ids = Article::scheduled()->pluck('id');

        $this->assertSame([$sooner->id, $later->id], $ids->all());
    }

    public function test_status_helper_methods(): void
    {
        $author = $this->author();

        $draft = Article::create($this->baseAttributes($author, ['status' => Article::STATUS_DRAFT]));
        $review = Article::create($this->baseAttributes($author, ['status' => Article::STATUS_REVIEW]));
        $scheduled = Article::create($this->baseAttributes($author, [
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDay(),
        ]));
        $published = Article::create($this->baseAttributes($author, ['status' => Article::STATUS_PUBLISHED]));

        $this->assertTrue($draft->isDraft());
        $this->assertTrue($review->isInReview());
        $this->assertTrue($scheduled->isScheduled());
        $this->assertTrue($published->isPublished());

        $this->assertFalse($draft->isScheduled());
        $this->assertFalse($scheduled->isPublished());
    }

    public function test_is_schedule_overdue(): void
    {
        $author = $this->author();

        $overdue = Article::create($this->baseAttributes($author, [
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->subMinute(),
        ]));

        $future = Article::create($this->baseAttributes($author, [
            'title' => 'Nel futuro',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDay(),
        ]));

        $this->assertTrue($overdue->fresh()->isScheduleOverdue());
        $this->assertFalse($future->fresh()->isScheduleOverdue());
    }

    public function test_invalid_status_is_rejected_by_the_database_enum(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Article::create($this->baseAttributes($this->author(), ['status' => 'not-a-real-status']));
    }

    public function test_scheduled_at_from_editorial_input_converts_rome_time_to_utc(): void
    {
        // 5 agosto 2026, 09:00 a Roma (CEST, UTC+2) => 07:00 UTC.
        $utc = Article::scheduledAtFromEditorialInput('2026-08-05', '09:00');

        $this->assertSame('UTC', $utc->timezoneName);
        $this->assertSame('2026-08-05 07:00:00', $utc->format('Y-m-d H:i:s'));
    }

    public function test_scheduled_at_from_editorial_input_handles_winter_time(): void
    {
        // 5 gennaio 2026, 09:00 a Roma (CET, UTC+1) => 08:00 UTC.
        $utc = Article::scheduledAtFromEditorialInput('2026-01-05', '09:00');

        $this->assertSame('2026-01-05 08:00:00', $utc->format('Y-m-d H:i:s'));
    }

    public function test_published_at_for_editors_converts_utc_to_rome_time(): void
    {
        $article = Article::create($this->baseAttributes($this->author(), [
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => Carbon::create(2026, 8, 5, 7, 0, 0, 'UTC'),
        ]));

        $rome = $article->fresh()->publishedAtForEditors();

        $this->assertSame('Europe/Rome', $rome->timezoneName);
        $this->assertSame('2026-08-05 09:00:00', $rome->format('Y-m-d H:i:s'));
    }
}
