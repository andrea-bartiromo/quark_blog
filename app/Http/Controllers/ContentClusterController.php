<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ContentCluster;
use Illuminate\Contracts\View\View;

class ContentClusterController extends Controller
{
    public function index(): View
    {
        $clusters = ContentCluster::query()
            ->active()
            ->ordered()
            ->withCount([
                'articles as published_articles_count' => fn ($query) => $query->published(),
            ])
            ->with([
                'pillarArticle' => fn ($query) => $query->published(),
            ])
            ->get();

        return view('content-clusters.index', compact('clusters'));
    }

    public function show(string $slug): View
    {
        $cluster = ContentCluster::query()
            ->active()
            ->where('slug', $slug)
            ->firstOrFail();

        $articles = $cluster->articles()
            ->published()
            ->get();
        $pillar = $cluster->pillarArticle()
            ->published()
            ->first();

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
            'canonical',
            'description',
            'structuredData',
            'categoryOptions',
        ));
    }
}
