<?php

namespace App\Services\ContentClusters;

use App\Models\Category;
use App\Models\ContentCluster;

/**
 * Cantiere 48 (programma "100 cantieri Kairus", dipende dai Cantieri 46-47):
 * estrae la costruzione dei dati della pagina pubblica di un Percorso
 * (`ContentClusterController::show()`) in un servizio riusabile, cosa
 * che `CategoryDiscoveryPageData` già faceva per Category ma che
 * `ContentClusterController` non aveva ancora — necessario per
 * l'anteprima admin (`Admin\ContentClusterController::preview()`), che
 * deve renderizzare la STESSA vista con gli STESSI dati di un Percorso
 * non ancora pubblico, senza duplicare la logica (structured data,
 * canonical, sequenza pubblica) in due posti che potrebbero divergere.
 *
 * Nessun comportamento della pagina pubblica cambia: `show()` continua a
 * filtrare con `publiclyVisible()` PRIMA di chiamare `build()` — questo
 * servizio si limita a costruire l'array di dati per un cluster già
 * risolto, verificato bit-per-bit invariato dalla suite
 * `tests/Feature/ContentClusterPublicTest.php` esistente.
 */
class ContentClusterShowPageData
{
    public function __construct(
        private readonly ContentClusterPublicSequence $publicSequence,
    ) {}

    /** @return array<string, mixed> */
    public function build(ContentCluster $cluster): array
    {
        $sequence = $this->publicSequence->resolve($cluster);
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

        $categoryOptions = Category::options(false);

        return [
            'cluster' => $cluster,
            'articles' => $articles,
            'pillar' => $pillar,
            'hasHiddenRemainder' => $hasHiddenRemainder,
            'canonical' => $canonical,
            'description' => $description,
            'structuredData' => $structuredData,
            'categoryOptions' => $categoryOptions,
        ];
    }
}
