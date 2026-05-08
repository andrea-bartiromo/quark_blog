<?php
/**
 * Quark — Blog di divulgazione scientifica
 *
 * @author    Andrea Bartiromo <redazione@illaboratorio.it>
 * @copyright 2025 Andrea Bartiromo. Tutti i diritti riservati.
 */

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleView;
use App\Models\Category;
use Illuminate\Support\Carbon;

class HomeController extends Controller
{
    public function index()
    {
        $featured = Article::published()->featured()->with('author')->first();

        $latest = Article::published()->with('author')
                        ->when($featured, fn($q) => $q->where('id', '!=', $featured->id))
                        ->orderByDesc('published_at')
                        ->limit(6)
                        ->get();

        // Trending 24h basato su visualizzazioni reali
        $trendingIds = ArticleView::query()
            ->where('viewed_at', '>=', Carbon::now()->subDay())
            ->selectRaw('article_id, COUNT(*) as total_views')
            ->groupBy('article_id')
            ->orderByDesc('total_views')
            ->limit(5)
            ->pluck('article_id');

        $trending = Article::published()
            ->whereIn('id', $trendingIds)
            ->with('author')
            ->get();

        $categoryRecords = Category::ordered()->get()->keyBy('slug');
        $categoryOptions = Category::options();

        // Articoli per categoria
        $byCategory = [];

        foreach ($categoryOptions as $slug => $label) {
            $arts = Article::published()
                ->byCategory($slug)
                ->with('author')
                ->when($featured, fn($q) => $q->where('id', '!=', $featured->id))
                ->orderByDesc('published_at')
                ->limit(3)
                ->get();

            if ($arts->count() > 0) {
                $byCategory[$slug] = $arts;
            }
        }

        return view('home', compact(
            'featured',
            'latest',
            'byCategory',
            'trending',
            'categoryRecords',
            'categoryOptions'
        ));
    }
}
