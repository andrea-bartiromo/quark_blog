<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\ArticleDailyView;
use App\Models\User;
use App\Services\ArticleAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ArticleAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private ArticleAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ArticleAnalyticsService::class);

        // Ancora il "oggi" editoriale a una data fissa per rendere gli
        // intervalli deterministici in ogni test di questa classe.
        Carbon::setTestNow(Carbon::create(2026, 8, 20, 12, 0, 0, Article::EDITORIAL_TIMEZONE));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'editor'])->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'body' => 'Corpo articolo di prova.',
            'category' => 'energia',
            'status' => 'published',
            'published_at' => now(),
        ], $overrides));
    }

    // 1. Selettore periodo: valori ammessi passano, il resto ricade sul default
    public function test_normalize_period_accepts_only_the_allowed_values(): void
    {
        $this->assertSame(7, ArticleAnalyticsService::normalizePeriod(7));
        $this->assertSame(30, ArticleAnalyticsService::normalizePeriod(30));
        $this->assertSame(90, ArticleAnalyticsService::normalizePeriod(90));
        $this->assertSame(30, ArticleAnalyticsService::normalizePeriod(365));
        $this->assertSame(30, ArticleAnalyticsService::normalizePeriod('non-numerico'));
        $this->assertSame(30, ArticleAnalyticsService::normalizePeriod(null));
    }

    // 2. I giorni senza views appaiono come 0, non vengono saltati
    public function test_daily_series_zero_fills_days_with_no_views(): void
    {
        $article = $this->article();
        ArticleDailyView::create(['article_id' => $article->id, 'date' => '2026-08-20', 'views' => 5]);
        ArticleDailyView::create(['article_id' => $article->id, 'date' => '2026-08-18', 'views' => 3]);

        $series = $this->service->dailySeries(7); // 2026-08-14 .. 2026-08-20

        $this->assertCount(7, $series);
        $this->assertSame(0, $series['2026-08-14']);
        $this->assertSame(3, $series['2026-08-18']);
        $this->assertSame(0, $series['2026-08-19']);
        $this->assertSame(5, $series['2026-08-20']);
        // Ordine cronologico
        $this->assertSame(['2026-08-14', '2026-08-15', '2026-08-16', '2026-08-17', '2026-08-18', '2026-08-19', '2026-08-20'], $series->keys()->all());
    }

    // 3. La serie somma le views di più articoli nello stesso giorno
    public function test_daily_series_sums_views_across_multiple_articles(): void
    {
        $a = $this->article();
        $b = $this->article();
        ArticleDailyView::create(['article_id' => $a->id, 'date' => '2026-08-20', 'views' => 4]);
        ArticleDailyView::create(['article_id' => $b->id, 'date' => '2026-08-20', 'views' => 6]);

        $series = $this->service->dailySeries(7);

        $this->assertSame(10, $series['2026-08-20']);
    }

    // 4. Totali per singolo giorno e per intervallo
    public function test_total_views_for_date_and_range(): void
    {
        $article = $this->article();
        ArticleDailyView::create(['article_id' => $article->id, 'date' => '2026-08-18', 'views' => 3]);
        ArticleDailyView::create(['article_id' => $article->id, 'date' => '2026-08-19', 'views' => 4]);
        ArticleDailyView::create(['article_id' => $article->id, 'date' => '2026-08-20', 'views' => 5]);

        $this->assertSame(4, $this->service->totalViewsForDate(Carbon::parse('2026-08-19')));
        $this->assertSame(12, $this->service->totalViewsForRange(Carbon::parse('2026-08-18'), Carbon::parse('2026-08-20')));
    }

    // 5. Top articoli ordinati per views del PERIODO, non per Article::views lifetime
    public function test_top_articles_for_range_ranks_by_period_views_not_lifetime(): void
    {
        $highLifetimeLowPeriod = $this->article(['title' => 'Storico ma fermo', 'views' => 5000]);
        $lowLifetimeHighPeriod = $this->article(['title' => 'In crescita ora', 'views' => 10]);

        ArticleDailyView::create(['article_id' => $highLifetimeLowPeriod->id, 'date' => '2026-08-20', 'views' => 2]);
        ArticleDailyView::create(['article_id' => $lowLifetimeHighPeriod->id, 'date' => '2026-08-20', 'views' => 50]);

        $top = $this->service->topArticlesForRange(Carbon::parse('2026-08-14'), Carbon::parse('2026-08-20'));

        $this->assertSame('In crescita ora', $top->first()->title);
        $this->assertSame(50, (int) $top->first()->period_views);
        $this->assertSame(10, (int) $top->first()->lifetime_views);
    }

    // 6. Solo articoli pubblicati compaiono in classifica
    public function test_top_articles_for_range_excludes_unpublished_articles(): void
    {
        $draft = $this->article(['status' => 'draft', 'published_at' => null]);
        ArticleDailyView::create(['article_id' => $draft->id, 'date' => '2026-08-20', 'views' => 100]);

        $top = $this->service->topArticlesForRange(Carbon::parse('2026-08-14'), Carbon::parse('2026-08-20'));

        $this->assertTrue($top->isEmpty());
    }

    // 7. Distribuzione per categoria: somma corretta e etichetta risolta
    public function test_category_totals_for_range_sums_and_resolves_labels(): void
    {
        $energia1 = $this->article(['category' => 'energia']);
        $energia2 = $this->article(['category' => 'energia']);
        $spazio = $this->article(['category' => 'spazio']);

        ArticleDailyView::create(['article_id' => $energia1->id, 'date' => '2026-08-20', 'views' => 3]);
        ArticleDailyView::create(['article_id' => $energia2->id, 'date' => '2026-08-20', 'views' => 4]);
        ArticleDailyView::create(['article_id' => $spazio->id, 'date' => '2026-08-20', 'views' => 2]);

        $totals = $this->service->categoryTotalsForRange(Carbon::parse('2026-08-14'), Carbon::parse('2026-08-20'));

        $energiaRow = $totals->firstWhere('label', config('laboratorio.categories.energia'));
        $this->assertSame(7, $energiaRow['total']);
    }
}
