<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ContentCluster;
use App\Services\ContentClusters\ContentClusterPublicSequence;
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

    public function show(string $slug, ContentClusterPublicSequence $publicSequence): View
    {
        $cluster = ContentCluster::query()
            ->publiclyVisible()
            ->where('slug', $slug)
            ->firstOrFail();

        $sequence = $publicSequence->resolve($cluster);
        $articles = $sequence['articles'];
        $hasHiddenRemainder = $sequence['has_hidden_remainder'];
        $pillar = $articles->first(fn ($article) => $article->id === $cluster->pillar_article_id);

        $canonical = route('percorsi.show', $cluster->slug);
        $description = $cluster->seo_description
            ?: $cluster->short_description
            ?: str($cluster->description)->stripTags()->squish()->limit(160)->toString();

        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $cluster->seo_title ?: $cluster->name,
            'description' => $description,
            'url' => $canonical,
            'breadcrumb' => [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Percorsi', 'item' => route('percorsi.index')],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $cluster->name, 'item' => $canonical],
                ],
            ],
            'mainEntity' => [
                '@type' => 'ItemList',
                'numberOfItems' => $articles->count(),
                'itemListElement' => $articles->values()->map(fn ($article, $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $article->title,
                    'url' => route('articolo', $article->slug),
                ])->all(),
            ],
        ];

        // DB-first (stessa fonte di Category::options() usata altrove):
        // le etichette di categoria per-tappa devono riconoscere anche una
        // categoria creata dall'admin dopo il deploy — vedi
        // $categoryLabels in content-clusters/show.blade.php.
        $categoryOptions = Category::options(false);

        return view('content-clusters.show', compact(
            'cluster',
            'articles',
            'pillar',
            'hasHiddenRemainder',
            'canonical',
            'description',
            'structuredData',
            'categoryOptions',
        ));
    }
}
