<?php

namespace App\Http\Controllers;

use App\Models\ContentCluster;
use App\Services\ContentClusters\ContentClusterPublicSequence;
use Illuminate\Contracts\View\View;

class ContentClusterController extends Controller
{
    public function index(): View
    {
        $clusters = ContentCluster::query()
            ->publiclyVisible()
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

        return view('content-clusters.show', compact(
            'cluster',
            'articles',
            'pillar',
            'hasHiddenRemainder',
            'canonical',
            'description',
            'structuredData',
        ));
    }
}
