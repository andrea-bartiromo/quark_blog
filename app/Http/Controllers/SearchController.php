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
                $this->normalizeAuthorFilter($authorId),
                $from ?: null,
                $to ?: null
            );
        }

        $authors = User::has('articles')->orderBy('name')->get(['id', 'name']);
        $categories = config('laboratorio.categories');

        return view('ricerca', compact('query', 'results', 'category', 'authorId', 'from', 'to', 'authors', 'categories'));
    }

    /**
     * Codex (PR #166, round 1): un cast diretto a (int) su un valore
     * arbitrario in query string produce risultati silenziosamente
     * sbagliati — "abc" diventa 0 (falsy, il filtro autore verrebbe
     * saltato del tutto, mostrando TUTTI gli articoli invece di nessuno),
     * "1abc" diventa 1 (un ID valido ma non quello digitato, filtro
     * silenziosamente sbagliato). Un valore non composto solo da cifre
     * diventa qui l'ID 0, che ArticleSearchService::search() applica
     * comunque come vincolo esplicito (mai saltato solo perché "falsy") —
     * nessun autore reale ha id 0 (autoincrement da 1), quindi produce
     * correttamente zero risultati, come faceva il confronto letterale
     * precedente.
     */
    private function normalizeAuthorFilter(string $authorId): ?int
    {
        if ($authorId === '') {
            return null;
        }

        return ctype_digit($authorId) ? (int) $authorId : 0;
    }
}
