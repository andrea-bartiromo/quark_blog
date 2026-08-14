<?php

/**
 * Kairus — Blog di divulgazione scientifica
 *
 * @author    Andrea Bartiromo <redazione@kairus.it>
 * @copyright 2025 Andrea Bartiromo. Tutti i diritti riservati.
 */

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleView;
use App\Models\Category;
use App\Models\ContentCluster;
use App\Models\SpecialPage;
use Illuminate\Support\Carbon;

class HomeController extends Controller
{
    public function index()
    {
        $featured = Article::published()->featured()->with('author')->first();

        $latest = Article::published()->with('author')
            ->when($featured, fn ($q) => $q->where('id', '!=', $featured->id))
            ->orderByDesc('published_at')
            ->limit(6)
            ->get();

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
        $byCategory = [];

        foreach ($categoryOptions as $slug => $label) {
            $arts = Article::published()
                ->byCategory($slug)
                ->with('author')
                ->orderByDesc('published_at')
                ->limit(3)
                ->get();

            if ($arts->count() > 0) {
                $byCategory[$slug] = $arts;
            }
        }

        $homePaths = ContentCluster::query()
            ->active()
            ->ordered()
            ->withCount([
                'articles as published_articles_count' => fn ($query) => $query->published(),
            ])
            ->having('published_articles_count', '>', 0)
            ->limit(2)
            ->get();

        $turingHome = $this->turingHomeTeaser();

        return view('home', compact(
            'featured',
            'latest',
            'byCategory',
            'trending',
            'categoryRecords',
            'categoryOptions',
            'turingHome',
            'homePaths'
        ));
    }

    private function turingHomeTeaser(): array
    {
        $page = SpecialPage::where('slug', 'turing')->first();
        $content = $page?->content ?? [];
        $homeTeaser = $content['home_teaser'] ?? [];
        $backgroundImage = $homeTeaser['background_image'] ?? null;

        return [
            'kicker' => $homeTeaser['kicker'] ?? 'Special Project',
            'title' => $homeTeaser['title'] ?? 'Alan Turing: l’uomo che ha decifrato il futuro.',
            'lead' => $homeTeaser['text'] ?? 'Una nuova area speciale di Kairus dedicata a Enigma, alla nascita del computer, al Test di Turing e al legame con l’intelligenza artificiale moderna.',
            'cta' => $homeTeaser['cta_label'] ?? 'Entra nella Turing Experience',
            'terminalTitle' => $homeTeaser['terminal_title'] ?? 'TURING ARCHIVE',
            'terminalLines' => $homeTeaser['terminal_lines'] ?? [
                'ENIGMA SIGNAL FOUND',
                'MACHINE INTELLIGENCE: ACTIVE',
                'QUESTION: CAN MACHINES THINK?',
                'STATUS: STILL OPEN',
            ],
            'style' => $backgroundImage
                ? "background-image:linear-gradient(90deg,rgba(255,255,255,.18),rgba(255,255,255,.06),rgba(255,255,255,0)),url('".asset('assets/img/'.$backgroundImage)."');"
                : 'background:linear-gradient(135deg,#ecfeff,#f8fafc);',
        ];
    }
}
