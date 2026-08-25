<?php

namespace Tests\Unit\EditorialOperations;

use App\Models\Article;
use App\Models\User;
use App\Services\EditorialOperations\PublicationCadenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Missione 40 (secondo batch autonomo KAIRUS, Fase E — Editorial Quality &
 * Readiness): "publication gaps" — ritmo di pubblicazione a livello di
 * sito, distinto dal "published_beyond_gap" per-Percorso (Missione 21).
 */
class PublicationCadenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_is_unavailable_when_no_article_was_ever_published(): void
    {
        $this->article(Article::STATUS_DRAFT, null);

        $summary = app(PublicationCadenceService::class)->summary();

        $this->assertFalse($summary['available']);
        $this->assertNull($summary['last_published_at']);
        $this->assertNull($summary['days_since_last_publication']);
    }

    public function test_summary_reflects_the_most_recently_published_article(): void
    {
        $this->article(Article::STATUS_PUBLISHED, now()->subDays(10));
        $this->article(Article::STATUS_PUBLISHED, now()->subDays(2));

        $summary = app(PublicationCadenceService::class)->summary();

        $this->assertTrue($summary['available']);
        $this->assertSame(2, $summary['days_since_last_publication']);
    }

    public function test_a_scheduled_article_never_counts_as_the_most_recent_publication(): void
    {
        $this->article(Article::STATUS_PUBLISHED, now()->subDays(5));
        $this->article(Article::STATUS_SCHEDULED, now()->addHour());

        $summary = app(PublicationCadenceService::class)->summary();

        $this->assertSame(5, $summary['days_since_last_publication']);
    }

    private function article(string $status, ?\DateTimeInterface $publishedAt): Article
    {
        $author = User::factory()->create(['role' => 'editor']);

        return Article::withoutEvents(fn () => Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo cadenza '.uniqid(),
            'slug' => 'articolo-cadenza-'.uniqid(),
            'excerpt' => '',
            'body' => '<p>Corpo.</p>',
            'category' => 'spazio',
            'status' => $status,
            'published_at' => $publishedAt,
            'read_minutes' => 3,
        ]));
    }
}
