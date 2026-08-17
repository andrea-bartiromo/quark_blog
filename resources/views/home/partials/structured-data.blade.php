{{--
    Dati strutturati Organization + WebSite (schema.org), solo homepage.
    Primo intervento minimo dell'audit dati strutturati: Article/NewsArticle,
    BreadcrumbList, Person e CollectionPage arriveranno in commit separati.

    Un unico blocco @graph con identificatori stabili (.../#organization,
    .../#website) cosi' che i prossimi schema (es. Article.publisher) possano
    referenziare l'Organization per @id invece di duplicarne i dati.

    Ogni valore e' reale e verificato, nessun dato inventato: niente sameAs
    (i profili social non sono ancora configurati, config('laboratorio.social')
    e' vuoto), niente address/telephone/legalName (non presenti da nessuna
    parte nel progetto), niente SearchAction (il sitelinks search box che
    supportava e' stato rimosso da Google il 21 novembre 2024).
--}}
@php
    $structuredDataBaseUrl = url('/');
    $structuredDataOrganizationId = $structuredDataBaseUrl.'/#organization';
    $structuredDataWebsiteId = $structuredDataBaseUrl.'/#website';

    // Riferimento diretto all'asset, non a config('laboratorio.default_share_image'):
    // quel valore serve al fallback og:image/twitter:image (Fase 1), un
    // requisito diverso che oggi punta allo stesso file per coincidenza, non
    // per necessita'. PNG raster 512x512 (verificato su disco), non
    // logo.svg: Google richiede un formato raster per Organization.logo.
    $structuredDataLogoPath = 'assets/icons/icon-512.png';

    $structuredData = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => $structuredDataOrganizationId,
                'name' => config('laboratorio.name'),
                'url' => $structuredDataBaseUrl,
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => asset($structuredDataLogoPath),
                    'width' => 512,
                    'height' => 512,
                ],
            ],
            [
                '@type' => 'WebSite',
                '@id' => $structuredDataWebsiteId,
                'url' => $structuredDataBaseUrl,
                'name' => config('laboratorio.name'),
                'description' => config('laboratorio.description'),
                'publisher' => ['@id' => $structuredDataOrganizationId],
            ],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($structuredData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}</script>
