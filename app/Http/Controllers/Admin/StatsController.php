<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Services\ArticleAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function index(Request $request, ArticleAnalyticsService $analytics)
    {
        $period = ArticleAnalyticsService::normalizePeriod($request->query('period'));

        $today = $analytics->editorialToday();
        $yesterday = $today->clone()->subDay();
        $periodStart = $today->clone()->subDays($period - 1);
        $previousPeriodEnd = $periodStart->clone()->subDay();
        $previousPeriodStart = $previousPeriodEnd->clone()->subDays($period - 1);

        $viewsToday = $analytics->totalViewsForDate($today);
        $viewsYesterday = $analytics->totalViewsForDate($yesterday);
        $viewsPeriod = $analytics->totalViewsForRange($periodStart, $today);
        $viewsPreviousPeriod = $analytics->totalViewsForRange($previousPeriodStart, $previousPeriodEnd);

        // null quando non esiste un periodo precedente comparabile (somma
        // zero): mostrare "+100%"/"-100%" sarebbe fuorviante quanto un
        // vero e proprio 0% "nessuna variazione".
        $periodChangePercent = $viewsPreviousPeriod > 0
            ? round((($viewsPeriod - $viewsPreviousPeriod) / $viewsPreviousPeriod) * 100, 1)
            : null;

        $avgViewsPerDay = round($viewsPeriod / $period, 1);

        $topArticlesPeriod = $analytics->topArticlesForRange($periodStart, $today, 10);

        // Top articoli per views lifetime (dato storico, distinto da quello
        // del periodo — resta qui solo per il KPI "Top article" lifetime).
        $articles = Article::published()
            ->orderByDesc('views')
            ->limit(15)
            ->get([
                'id',
                'title',
                'slug',
                'category',
                'views',
                'published_at',
                'read_minutes',
            ]);

        // Per categoria — DB-first (stessa fonte di Category::options()
        // usata altrove): con lo snapshot statico di config() qui, un
        // articolo in una categoria creata dall'admin dopo il deploy
        // spariva silenziosamente da questo breakdown (non solo con
        // un'etichetta sbagliata: l'intera categoria non compariva).
        $byCategory = [];

        foreach (Category::options(false) as $slug => $label) {
            $top = $articles->where('category', $slug)->take(3);

            if ($top->count() > 0) {
                $byCategory[$slug] = [
                    'label' => $label,
                    'articles' => $top,
                    'total_views' => $top->sum('views'),
                ];
            }
        }

        /*
         * Compatibilità database:
         * SQLite usa strftime(), MySQL usa DATE_FORMAT(),
         * PostgreSQL usa TO_CHAR().
         */
        $monthFromCreatedAt = match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', created_at)",
            'pgsql' => "TO_CHAR(created_at, 'YYYY-MM')",
            default => "DATE_FORMAT(created_at, '%Y-%m')",
        };

        $monthFromPublishedAt = match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', published_at)",
            'pgsql' => "TO_CHAR(published_at, 'YYYY-MM')",
            default => "DATE_FORMAT(published_at, '%Y-%m')",
        };

        // Crescita newsletter per mese
        $newsletterGrowth = DB::table('newsletter')
            ->selectRaw("{$monthFromCreatedAt} as month, COUNT(*) as count")
            ->groupByRaw($monthFromCreatedAt)
            ->orderBy('month')
            ->get();

        // Articoli per mese
        $articlesByMonth = DB::table('articles')
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->selectRaw("{$monthFromPublishedAt} as month, COUNT(*) as count")
            ->groupByRaw($monthFromPublishedAt)
            ->orderBy('month')
            ->get();

        // Più commentati
        $topCommented = DB::table('articles')
            ->join('comments', 'articles.id', '=', 'comments.article_id')
            ->where('comments.status', 'approved')
            ->where('articles.status', 'published')
            ->selectRaw(
                'articles.id,
                articles.title,
                articles.slug,
                COUNT(comments.id) as comments_count'
            )
            ->groupBy(
                'articles.id',
                'articles.title',
                'articles.slug'
            )
            ->orderByDesc('comments_count')
            ->limit(5)
            ->get();

        return view('admin.stats', compact(
            'articles',
            'byCategory',
            'newsletterGrowth',
            'articlesByMonth',
            'topCommented',
            'period',
            'viewsToday',
            'viewsYesterday',
            'viewsPeriod',
            'periodChangePercent',
            'avgViewsPerDay',
            'topArticlesPeriod'
        ));
    }

    public function charts(Request $request, ArticleAnalyticsService $analytics)
    {
        $period = ArticleAnalyticsService::normalizePeriod($request->query('period'));
        $today = $analytics->editorialToday();
        $periodStart = $today->clone()->subDays($period - 1);

        $viewsSeries = $analytics->dailySeries($period)
            ->map(fn (int $views, string $date) => ['day' => $date, 'views' => $views])
            ->values();

        $newsletterGrowth = DB::table('newsletter')
            ->selectRaw('date(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        // Views del periodo per categoria (non conteggio articoli): quale
        // categoria sta ricevendo più traffico reale, non solo quante ne
        // pubblichiamo.
        $categoryDistribution = $analytics->categoryTotalsForRange($periodStart, $today);

        return response()->json([
            'views' => $viewsSeries,
            'newsletter' => $newsletterGrowth,
            'categories' => $categoryDistribution,
        ]);
    }
}