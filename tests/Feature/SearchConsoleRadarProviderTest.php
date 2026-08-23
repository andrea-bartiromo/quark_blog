<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\SearchConsoleQuery;
use App\Models\User;
use App\Services\EditorialRadar\Providers\SearchConsoleOpportunityProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SearchConsoleRadarProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_imported_search_console_data_produces_no_radar_signal(): void
    {
        $this->assertTrue(app(SearchConsoleOpportunityProvider::class)->opportunities()->isEmpty());
    }

    public function test_low_ctr_signal_is_explainable_without_exporting_numeric_score(): void
    {
        $article = $this->article('radar-search-public', Article::STATUS_PUBLISHED);
        $this->searchRow($article, 'relativita spiegata', impressions: 1000, ctr: 0.01, position: 3.0);

        $rows = app(SearchConsoleOpportunityProvider::class)->opportunities();
        $row = $rows->firstWhere('article_id', $article->id);

        $this->assertNotNull($row);
        $this->assertSame('search_console', $row['provider']);
        $this->assertSame('CTR_IMPROVEMENT', $row['type']);
        $this->assertSame('HIGH', $row['priority']);
        $this->assertArrayNotHasKey('score', $row);
        $this->assertNotEmpty($row['why']);
        $this->assertNotEmpty($row['suggested_action']);
    }

    public function test_linked_draft_review_and_scheduled_articles_never_become_public_radar_opportunities(): void
    {
        foreach ([Article::STATUS_DRAFT, Article::STATUS_REVIEW, Article::STATUS_SCHEDULED] as $status) {
            $article = $this->article('radar-search-'.$status, $status);
            $this->searchRow($article, 'query '.$status, impressions: 1000, ctr: 0.01, position: 3.0);
        }

        $rows = app(SearchConsoleOpportunityProvider::class)->opportunities();

        $this->assertTrue($rows->whereNotNull('article_id')->isEmpty());
    }

    public function test_unmatched_query_can_be_a_new_article_signal_without_inventing_an_article(): void
    {
        $this->searchRow(null, 'materia oscura spiegata', impressions: 500, ctr: 0.0, position: 18.0);

        $row = app(SearchConsoleOpportunityProvider::class)->opportunities()->first();

        $this->assertNotNull($row);
        $this->assertSame('NEW_ARTICLE', $row['type']);
        $this->assertNull($row['article_id']);
        $this->assertStringStartsWith('Query:', $row['title']);
        $this->assertArrayNotHasKey('score', $row);
    }

    public function test_provider_query_count_is_bounded_and_does_not_scale_per_signal(): void
    {
        $article = $this->article('radar-budget-public', Article::STATUS_PUBLISHED);
        $this->searchRow($article, 'radar budget one', impressions: 1000, ctr: 0.01, position: 3.0);

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(SearchConsoleOpportunityProvider::class)->opportunities();
        $oneSignalQueries = count(DB::getQueryLog());

        foreach (range(2, 20) as $index) {
            $this->searchRow($article, 'radar budget '.$index, impressions: 1000, ctr: 0.01, position: 3.0);
        }

        DB::flushQueryLog();
        app(SearchConsoleOpportunityProvider::class)->opportunities();
        $manySignalQueries = count(DB::getQueryLog());

        $this->assertSame($oneSignalQueries, $manySignalQueries);
        $this->assertLessThanOrEqual(6, $manySignalQueries);
    }

    private function searchRow(?Article $article, string $query, int $impressions, float $ctr, float $position): SearchConsoleQuery
    {
        return SearchConsoleQuery::create([
            'query' => $query,
            'page_url' => $article ? route('articolo', $article->slug) : 'https://example.test/nessuna-landing',
            'article_id' => $article?->id,
            'clicks' => (int) round($impressions * $ctr),
            'impressions' => $impressions,
            'ctr' => $ctr,
            'position' => $position,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-22',
            'import_batch' => 'radar-test',
            'imported_at' => now(),
        ]);
    }

    private function article(string $slug, string $status): Article
    {
        return Article::withoutEvents(fn () => Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'excerpt' => 'Excerpt',
            'body' => '<p>Body</p>',
            'category' => 'radar-test',
            'status' => $status,
            'published_at' => match ($status) {
                Article::STATUS_PUBLISHED => now()->subMinute(),
                Article::STATUS_SCHEDULED => now()->addDay(),
                default => null,
            },
            'read_minutes' => 1,
        ]));
    }
}
