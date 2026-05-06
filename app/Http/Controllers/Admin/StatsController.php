<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Newsletter;
use App\Models\Comment;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function index()
    {
        // Top articoli per views
        $articles = Article::published()
            ->orderByDesc('views')
            ->limit(15)
            ->get(['id', 'title', 'slug', 'category', 'views', 'published_at', 'read_minutes']);

        // Per categoria
        $byCategory = [];
        foreach (config('laboratorio.categories') as $slug => $label) {
            $top = $articles->where('category', $slug)->take(3);
            if ($top->count() > 0) {
                $byCategory[$slug] = [
                    'label'       => $label,
                    'articles'    => $top,
                    'total_views' => $top->sum('views'),
                ];
            }
        }

        // Crescita newsletter per mese
        $newsletterGrowth = DB::table('newsletter')
            ->selectRaw("strftime('%Y-%m', created_at) as month, COUNT(*) as count")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Articoli per mese
        $articlesByMonth = DB::table('articles')
            ->where('status', 'published')
            ->selectRaw("strftime('%Y-%m', published_at) as month, COUNT(*) as count")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Più commentati — query semplice senza withCount
        $topCommented = DB::table('articles')
            ->join('comments', 'articles.id', '=', 'comments.article_id')
            ->where('comments.status', 'approved')
            ->where('articles.status', 'published')
            ->selectRaw('articles.id, articles.title, articles.slug, COUNT(comments.id) as comments_count')
            ->groupBy('articles.id', 'articles.title', 'articles.slug')
            ->orderByDesc('comments_count')
            ->limit(5)
            ->get();

        return view('admin.stats', compact(
            'articles', 'byCategory', 'newsletterGrowth',
            'articlesByMonth', 'topCommented'
        ));
    }
}