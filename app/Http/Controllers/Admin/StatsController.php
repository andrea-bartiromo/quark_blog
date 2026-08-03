<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function index()
    {
        // Top articoli per views
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

        // Per categoria
        $byCategory = [];

        foreach (config('laboratorio.categories') as $slug => $label) {
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
            'topCommented'
        ));
    }

    public function charts()
    {
        $viewsLast7Days = DB::table('article_views')
            ->selectRaw('date(viewed_at) as day, COUNT(*) as views')
            ->where('viewed_at', '>=', now()->subDays(7))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $newsletterGrowth = DB::table('newsletter')
            ->selectRaw('date(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $categoryDistribution = DB::table('articles')
            ->selectRaw('category, COUNT(*) as total')
            ->where('status', 'published')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(function ($item) {
                return [
                    'label' => config(
                        'laboratorio.categories.'.$item->category,
                        $item->category
                    ),
                    'total' => (int) $item->total,
                ];
            })
            ->values();

        return response()->json([
            'views' => $viewsLast7Days,
            'newsletter' => $newsletterGrowth,
            'categories' => $categoryDistribution,
        ]);
    }
}