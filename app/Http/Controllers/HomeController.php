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

        // Articoli per categoria: dopo la rimozione dell'eager load author
        // inutilizzato (commit precedente), il costo era comunque ancora
        // O(N) — una query "select * from articles where category = ?" per
        // ciascuna categoria (Q(N) = 10 + N, misurato con 0/1/6/10/25
        // categorie). Sostituita da un'unica query con ROW_NUMBER() OVER
        // (PARTITION BY category ORDER BY published_at DESC) — sintassi SQL
        // standard, verificata su entrambi i driver del progetto (SQLite
        // 3.45+, MariaDB 10.11 locali; supportata anche da MySQL 8+ e
        // PostgreSQL) — che porta il costo di questa sezione a una costante
        // indipendente da N. Stesso risultato esatto: fino a 3 articoli
        // pubblicati più recenti per categoria, nessuna esclusione
        // dell'articolo featured (la tile mostra la categoria, non
        // l'articolo — escluderlo farebbe sparire l'intera categoria quando
        // il suo unico articolo pubblicato è quello in evidenza).
        $categorySlugs = array_keys($categoryOptions);
        $byCategory = [];

        if ($categorySlugs !== []) {
            $rankedByCategory = Article::query()
                ->fromSub(
                    Article::published()
                        ->whereIn('category', $categorySlugs)
                        ->selectRaw('articles.*, ROW_NUMBER() OVER (PARTITION BY category ORDER BY published_at DESC) as category_rank'),
                    'articles'
                )
                ->where('category_rank', '<=', 3)
                ->orderBy('category')
                ->orderByDesc('published_at')
                ->get()
                ->groupBy('category');

            // Ripercorsa nell'ordine di $categoryOptions (sort_order, non
            // alfabetico): il GROUP BY sopra restituisce le categorie in
            // ordine alfabetico di slug (serviva solo a raggruppare i
            // risultati di un'unica query), ma home.blade.php costruisce
            // $categoryHighlights direttamente da collect($byCategory), che
            // preserva l'ordine di inserimento dell'array associativo —
            // deve quindi rispettare lo stesso sort_order del loop che
            // sostituisce (vedi HomeCategoriesTest::
            // test_categories_appear_in_sort_order).
            foreach ($categoryOptions as $slug => $label) {
                if ($rankedByCategory->has($slug)) {
                    $byCategory[$slug] = $rankedByCategory->get($slug)->values();
                }
            }
        }

        $homePaths = ContentCluster::query()
            ->publiclyVisible()
            ->whereHas('articles', fn ($query) => $query->published())
            ->ordered()
            ->withCount(['articles as published_articles_count' => fn ($query) => $query->published()])
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
