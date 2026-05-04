<?php
/**
 * Il Laboratorio — Rivista italiana di divulgazione scientifica
 *
 * @author    Andrea Bartiromo <redazione@illaboratorio.it>
 * @copyright 2025 Andrea Bartiromo. Tutti i diritti riservati.
 * @license   Proprietario — tutti i diritti riservati
 * @link      https://www.illaboratorio.it
 */
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Newsletter;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Contatori principali
        $stats = [
            'published'    => Article::where('status', 'published')->count(),
            'drafts'       => Article::where('status', 'draft')->count(),
            'unverified'   => Article::where('status', 'published')
                                ->whereIn('verification_status', ['unverified', 'in_progress'])
                                ->count(),
            'newsletter'   => Newsletter::where('confirmed', true)->count(),
            'comments'     => Comment::where('approved', false)->count(),
            'total_views'  => Article::sum('views'),
        ];

        // Articoli più letti (top 5)
        $topArticles = Article::where('status', 'published')
            ->orderByDesc('views')
            ->with('author')
            ->limit(5)
            ->get(['id', 'title', 'slug', 'category', 'views', 'user_id', 'published_at']);

        // Distribuzione per categoria
        $byCategory = Article::where('status', 'published')
            ->select('category', DB::raw('COUNT(*) as count'), DB::raw('SUM(views) as views'))
            ->groupBy('category')
            ->orderByDesc('count')
            ->get();

        // Articoli recenti (ultimi 8)
        $recentArticles = Article::with('author')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['id', 'title', 'slug', 'status', 'category', 'user_id', 'created_at', 'verification_status']);

        // Attività per mese (ultimi 6 mesi) - simulata con dati reali
        $monthlyActivity = Article::where('status', 'published')
            ->where('published_at', '>=', now()->subMonths(6))
            ->select(
                DB::raw("strftime('%Y-%m', published_at) as month"),
                DB::raw('COUNT(*) as articles'),
                DB::raw('SUM(views) as views')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return view('admin.dashboard', compact(
            'stats',
            'topArticles',
            'byCategory',
            'recentArticles',
            'monthlyActivity'
        ));
    }
}
