<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\User;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $query    = trim($request->input('q', ''));
        $category = $request->input('categoria', '');
        $authorId = $request->input('autore', '');
        $from     = $request->input('da', '');
        $to       = $request->input('a', '');
        $results  = collect();

        $hasFilter = $query || $category || $authorId || $from || $to;

        if ($hasFilter) {
            $builder = Article::published()->with('author');

            // Filtro testo libero
            if (strlen($query) >= 2) {
                $builder->where(function ($q) use ($query) {
                    $q->where('title',   'like', "%{$query}%")
                      ->orWhere('excerpt', 'like', "%{$query}%")
                      ->orWhere('body',    'like', "%{$query}%");
                });
            }

            // Filtro categoria
            if ($category) {
                $builder->where('category', $category);
            }

            // Filtro autore
            if ($authorId) {
                $builder->where('user_id', $authorId);
            }

            // Filtro data da
            if ($from) {
                $builder->where('published_at', '>=', $from . ' 00:00:00');
            }

            // Filtro data a
            if ($to) {
                $builder->where('published_at', '<=', $to . ' 23:59:59');
            }

            // Ordinamento per rilevanza se c'è una query testuale
            if (strlen($query) >= 2) {
                $builder->orderByRaw("
                    CASE
                        WHEN title LIKE ? THEN 1
                        WHEN title LIKE ? THEN 2
                        WHEN excerpt LIKE ? THEN 3
                        ELSE 4
                    END, published_at DESC
                ", [
                    $query,
                    "%{$query}%",
                    "%{$query}%",
                ]);
            } else {
                $builder->orderByDesc('published_at');
            }

            $results = $builder->paginate(15)->withQueryString();
        }

        $authors    = User::has('articles')->orderBy('name')->get(['id', 'name']);
        $categories = config('laboratorio.categories');

        return view('ricerca', compact('query', 'results', 'category', 'authorId', 'from', 'to', 'authors', 'categories'));
    }
}
