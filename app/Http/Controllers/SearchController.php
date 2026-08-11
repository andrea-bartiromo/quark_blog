<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Search\ArticleSearchService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(private readonly ArticleSearchService $searchService) {}

    public function index(Request $request)
    {
        $query = trim($request->input('q', ''));
        $category = $request->input('categoria', '');
        $authorId = $request->input('autore', '');
        $from = $request->input('da', '');
        $to = $request->input('a', '');
        $results = collect();

        $hasFilter = $query || $category || $authorId || $from || $to;

        if ($hasFilter) {
            $results = $this->searchService->search(
                $query,
                $category ?: null,
                $authorId ? (int) $authorId : null,
                $from ?: null,
                $to ?: null
            );
        }

        $authors = User::has('articles')->orderBy('name')->get(['id', 'name']);
        $categories = config('laboratorio.categories');

        return view('ricerca', compact('query', 'results', 'category', 'authorId', 'from', 'to', 'authors', 'categories'));
    }
}
