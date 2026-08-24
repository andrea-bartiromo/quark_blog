<?php

/**
 * Kairus — Rivista italiana di divulgazione scientifica
 *
 * @author    Andrea Bartiromo <redazione@kairus.it>
 * @copyright 2025 Andrea Bartiromo. Tutti i diritti riservati.
 * @license   Proprietario — tutti i diritti riservati
 *
 * @link      https://kairus.it
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Newsletter;
use App\Models\NewsletterClick;
use App\Models\NewsletterOpen;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Contatori principali
        $stats = [
            'published' => Article::where('status', 'published')->count(),
            'drafts' => Article::where('status', 'draft')->count(),
            'unverified' => Article::where('status', 'published')
                ->whereIn('verification_status', ['unverified', 'in_progress'])
                ->count(),
            'newsletter' => Newsletter::where('confirmed', true)->count(),
            'comments' => Comment::pending()->count(),
            'total_views' => Article::sum('views'),
        ];

        // Analytics newsletter
        $newsletterSubscribers = Newsletter::where('confirmed', true)->count();
        $newsletterOpens = NewsletterOpen::count();
        $newsletterClicks = NewsletterClick::count();

        $newsletterOpenRate = $newsletterSubscribers > 0
            ? round(($newsletterOpens / $newsletterSubscribers) * 100, 1)
            : 0;

        $newsletterClickRate = $newsletterOpens > 0
            ? round(($newsletterClicks / $newsletterOpens) * 100, 1)
            : 0;

        $topClickedArticles = NewsletterClick::selectRaw(
            'article_id, COUNT(*) as clicks'
        )
            ->whereNotNull('article_id')
            ->groupBy('article_id')
            ->orderByDesc('clicks')
            ->with('article')
            ->limit(5)
            ->get();

        // Articoli più letti
        $topArticles = Article::where('status', 'published')
            ->orderByDesc('views')
            ->with('author')
            ->limit(5)
            ->get([
                'id',
                'title',
                'slug',
                'category',
                'views',
                'user_id',
                'published_at',
            ]);

        // Distribuzione per categoria
        $byCategory = Article::where('status', 'published')
            ->select(
                'category',
                DB::raw('COUNT(*) as count'),
                DB::raw('COALESCE(SUM(views), 0) as views')
            )
            ->groupBy('category')
            ->orderByDesc('count')
            ->get();

        // Articoli recenti
        $recentArticles = Article::with('author')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get([
                'id',
                'title',
                'slug',
                'status',
                'category',
                'user_id',
                'created_at',
                'verification_status',
            ]);

        /*
         * Attività degli ultimi 6 mesi.
         *
         * In produzione viene normalmente utilizzato MySQL, che espone
         * DATE_FORMAT(). La suite di test utilizza invece SQLite in memoria,
         * dove la funzione equivalente è strftime().
         */
        $dateExpression = match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', published_at)",
            'pgsql' => "TO_CHAR(published_at, 'YYYY-MM')",
            default => "DATE_FORMAT(published_at, '%Y-%m')",
        };

        $monthlyActivity = Article::where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '>=', now()->subMonths(6))
            ->selectRaw(
                "{$dateExpression} as month,
                COUNT(*) as articles,
                COALESCE(SUM(views), 0) as views"
            )
            ->groupByRaw($dateExpression)
            ->orderBy('month')
            ->get();

        return view('admin.dashboard', compact(
            'stats',
            'newsletterSubscribers',
            'newsletterOpens',
            'newsletterClicks',
            'newsletterOpenRate',
            'newsletterClickRate',
            'topClickedArticles',
            'topArticles',
            'byCategory',
            'recentArticles',
            'monthlyActivity'
        ));
    }
}
