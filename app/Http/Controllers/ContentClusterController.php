<?php

namespace App\Http\Controllers;

use App\Models\ContentCluster;
use App\Services\ContentClusters\ContentClusterPublicSequence;
use App\Services\ContentClusters\ContentClusterShowPageData;
use Illuminate\Contracts\View\View;

class ContentClusterController extends Controller
{
    public function index(ContentClusterPublicSequence $publicSequence): View
    {
        $clusters = ContentCluster::query()
            ->publiclyVisible()
            ->ordered()
            ->orderBy('id')
            ->withCount([
                'articles as published_articles_count' => fn ($query) => $query->published(),
            ])
            ->with([
                'pillarArticle' => fn ($query) => $query->published(),
            ])
            ->paginate(6);

        $publicPreviews = $publicSequence->resolvePage($clusters->getCollection())
            ->map(fn (array $sequence) => $sequence['articles']->take(3)->values());

        if ($clusters->total() > 0 && $clusters->currentPage() > $clusters->lastPage()) {
            abort(404);
        }

        $pageUrl = static fn (int $page): string => $page === 1
            ? route('percorsi.index')
            : route('percorsi.index', ['page' => $page]);

        $canonical = $pageUrl($clusters->currentPage());
        $previousPageUrl = $clusters->onFirstPage() ? null : $pageUrl($clusters->currentPage() - 1);
        $nextPageUrl = $clusters->hasMorePages() ? $pageUrl($clusters->currentPage() + 1) : null;

        return view('content-clusters.index', compact(
            'clusters',
            'canonical',
            'previousPageUrl',
            'nextPageUrl',
            'publicPreviews',
        ));
    }

    public function show(string $slug, ContentClusterShowPageData $pageData): View
    {
        $cluster = ContentCluster::query()
            ->publiclyVisible()
            ->where('slug', $slug)
            ->firstOrFail();

        return view('content-clusters.show', $pageData->build($cluster));
    }
}
