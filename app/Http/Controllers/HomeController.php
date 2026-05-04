<?php
/**
 * Quark — Blog di divulgazione scientifica
 *
 * @author    Andrea Bartiromo <redazione@illaboratorio.it>
 * @copyright 2025 Andrea Bartiromo. Tutti i diritti riservati.
 */

namespace App\Http\Controllers;

use App\Models\Article;

class HomeController extends Controller
{
    public function index()
    {
        $featured   = Article::published()->featured()->with('author')->first();
        $latest     = Article::published()->with('author')
                        ->when($featured, fn($q) => $q->where('id', '!=', $featured->id))
                        ->orderByDesc('published_at')
                        ->limit(6)
                        ->get();

        // Articoli per categoria (2 per categoria, escluso il featured)
        $byCategory = [];
        foreach (config('laboratorio.categories') as $slug => $label) {
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

        return view('home', compact('featured', 'latest', 'byCategory'));
    }
}
