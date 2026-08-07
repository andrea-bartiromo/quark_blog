<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleDailyView;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Query di lettura sull'aggregato giornaliero (article_daily_views) per la
 * dashboard Analytics: serie temporale, totali per intervallo, classifiche
 * per periodo. Nessuna di queste query tocca Article::views (il contatore
 * lifetime) o article_views (il log per-pageview) — sola lettura del nuovo
 * aggregato scritto da ArticleViewTrackingService.
 */
class ArticleAnalyticsService
{
    /** Intervalli di periodo supportati dalla dashboard. */
    public const ALLOWED_PERIODS = [7, 30, 90];

    public const DEFAULT_PERIOD = 30;

    public static function normalizePeriod(mixed $period): int
    {
        $period = (int) $period;

        return in_array($period, self::ALLOWED_PERIODS, true) ? $period : self::DEFAULT_PERIOD;
    }

    /** Giorno editoriale corrente (Europe/Rome), a mezzanotte. */
    public function editorialToday(): Carbon
    {
        return Carbon::now(Article::EDITORIAL_TIMEZONE)->startOfDay();
    }

    /**
     * Serie giornaliera di views totali (tutti gli articoli sommati) per
     * gli ultimi $days giorni editoriali, oggi incluso — con zero-fill
     * esplicito: un giorno senza righe in article_daily_views appare come
     * 0, non viene saltato.
     *
     * @return Collection<string,int> chiave = data (Y-m-d), valore = views
     */
    public function dailySeries(int $days): Collection
    {
        $today = $this->editorialToday();
        $start = $today->clone()->subDays($days - 1);

        $totals = ArticleDailyView::query()
            ->selectRaw('date, SUM(views) as total')
            ->whereBetween('date', [$start->toDateString(), $today->toDateString()])
            ->groupBy('date')
            ->pluck('total', 'date');

        $series = collect();

        for ($cursor = $start->clone(); $cursor->lte($today); $cursor->addDay()) {
            $key = $cursor->toDateString();
            $series->put($key, (int) ($totals[$key] ?? 0));
        }

        return $series;
    }

    public function totalViewsForDate(Carbon $date): int
    {
        return (int) ArticleDailyView::where('date', $date->toDateString())->sum('views');
    }

    public function totalViewsForRange(Carbon $start, Carbon $end): int
    {
        return (int) ArticleDailyView::whereBetween('date', [$start->toDateString(), $end->toDateString()])->sum('views');
    }

    /**
     * Articoli pubblicati con più views nell'intervallo, ordinati per
     * somma delle views del periodo (non per Article::views lifetime).
     */
    public function topArticlesForRange(Carbon $start, Carbon $end, int $limit = 10): Collection
    {
        return ArticleDailyView::query()
            ->join('articles', 'articles.id', '=', 'article_daily_views.article_id')
            ->whereBetween('article_daily_views.date', [$start->toDateString(), $end->toDateString()])
            ->where('articles.status', Article::STATUS_PUBLISHED)
            ->selectRaw(
                'articles.id, articles.title, articles.slug, articles.category,
                 articles.read_minutes, articles.views as lifetime_views,
                 SUM(article_daily_views.views) as period_views'
            )
            ->groupBy(
                'articles.id',
                'articles.title',
                'articles.slug',
                'articles.category',
                'articles.read_minutes',
                'articles.views'
            )
            ->orderByDesc('period_views')
            ->limit($limit)
            ->get();
    }

    /**
     * Somma delle views del periodo per categoria (articoli pubblicati),
     * con l'etichetta leggibile già risolta — pronta per il grafico.
     */
    public function categoryTotalsForRange(Carbon $start, Carbon $end): Collection
    {
        return ArticleDailyView::query()
            ->join('articles', 'articles.id', '=', 'article_daily_views.article_id')
            ->whereBetween('article_daily_views.date', [$start->toDateString(), $end->toDateString()])
            ->where('articles.status', Article::STATUS_PUBLISHED)
            ->selectRaw('articles.category, SUM(article_daily_views.views) as total')
            ->groupBy('articles.category')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => config('laboratorio.categories.'.$row->category, $row->category),
                'total' => (int) $row->total,
            ])
            ->values();
    }
}
