{{--
    Dati strutturati NewsArticle + BreadcrumbList (schema.org), solo pagina
    articolo. Terzo intervento dell'audit dati strutturati (dopo Organization
    + WebSite sulla homepage, e dopo NewsArticle). Person standalone e
    CollectionPage restano commit separati successivi.

    NewsArticle (non Article generico, non BlogPosting): il contenuto
    editoriale e' cronaca scientifica su sviluppi/ricerche recenti (non
    materiale enciclopedico evergreen), e il progetto ha gia' una
    news-sitemap.xml attiva che tratta gli articoli pubblicati come
    contenuto per Google News.

    dateModified: mai da updated_at (continua a non essere affidabile —
    viene toccato anche da $article->increment('views') e dal salvataggio
    del flusso di verifica editoriale in Admin\ArticleController, nessuno
    dei due un cambiamento di contenuto). Trust Layer — riconciliazione
    Kairus (#517): $lastEditorialUpdate (passato da
    ArticleController::show(), calcolato da
    ArticleRevisionTransparencyService) è null a meno che esista una
    revisione post-pubblicazione con contenuto editoriale davvero diverso
    dallo stato attuale. La chiave resta del tutto assente quando è
    null — mai una data indovinata.

    BreadcrumbList: percorso Home → Categoria → Articolo quando la categoria
    dell'articolo risolve realmente a /categoria/{slug} (stessa logica di
    riconoscimento usata da ArticleController::category()); altrimenti
    Home → Articolo, per non pubblicare mai un link noto per restituire 404.
    Questo commit copre solo il JSON-LD: non introduce alcun breadcrumb
    visibile in UI (previsto come intervento separato della FASE 4), quindi
    l'audit breadcrumb complessivo resta parziale dopo questo commit.
--}}
@php
    // Stessi valori (name/url/logo) dell'Organization già dichiarata sulla
    // homepage (resources/views/home/partials/structured-data.blade.php),
    // duplicati qui deliberatamente — vedi nota sotto — non referenziati per
    // @id soltanto: la pagina articolo non condivide lo stesso documento
    // JSON-LD della homepage, quindi un riferimento @id isolato senza il
    // nodo completo non sarebbe risolvibile per un parser che legge questa
    // sola pagina.
    $articleStructuredDataBaseUrl = url('/');
    $articleStructuredDataOrganizationId = $articleStructuredDataBaseUrl.'/#organization';

    // NOTA MANUTENZIONE: questi tre valori (name/url/logo) devono restare
    // identici a quelli in home/partials/structured-data.blade.php, stesso
    // @id incluso — se cambia l'uno va cambiato anche l'altro. Con un solo
    // altro punto di uso non introduco qui un partial condiviso (fuori
    // scope per questo commit); da valutare se un terzo caso d'uso emerge.
    $articleStructuredDataOrganization = [
        '@type' => 'Organization',
        '@id' => $articleStructuredDataOrganizationId,
        'name' => config('laboratorio.name'),
        'url' => $articleStructuredDataBaseUrl,
        'logo' => [
            '@type' => 'ImageObject',
            'url' => asset('assets/icons/icon-512.png'),
            'width' => 512,
            'height' => 512,
        ],
    ];

    // Cover reale se presente; altrimenti il fallback raster globale (Fase
    // 1), mai hero-placeholder.svg — Google non accetta SVG come immagine
    // per NewsArticle.
    $articleStructuredDataImageIsCover = filled($article->cover_image);
    $articleStructuredDataImageUrl = $articleStructuredDataImageIsCover
        ? asset('assets/img/'.$article->cover_image)
        : asset(config('laboratorio.default_share_image'));

    // Larghezza/altezza reali, non assunte: stesso pattern già in uso altrove
    // per immagini locali (es. ResponsiveImageVariantService::resolveForMarkup(),
    // components/media/image-viewer.blade.php) — getimagesize() su file
    // locale sotto public/, guardia is_file() prima, mai una richiesta di
    // rete. Se il file non esiste o non è leggibile come immagine, i campi
    // restano assenti invece di dichiarare un valore inventato.
    $articleStructuredDataImagePath = $articleStructuredDataImageIsCover
        ? public_path('assets/img/'.$article->cover_image)
        : public_path(config('laboratorio.default_share_image'));
    $articleStructuredDataImageSize = is_file($articleStructuredDataImagePath)
        ? @getimagesize($articleStructuredDataImagePath)
        : false;

    // Stessa fonte già usata per articleSection: opzioni categoria DB-first
    // con fallback a config('laboratorio.categories'), risolta una sola
    // volta e riusata sia per l'etichetta sia per il controllo di
    // riconoscimento del breadcrumb.
    // Passato dal controller (ArticleController::show()) e già usato anche
    // da articolo.blade.php e breadcrumb.blade.php: prima ciascuno dei tre
    // rieseguiva la stessa query "select name, slug from categories".
    $articleCategoryOptions = $categoryOptions;
    $articleCategoryRecognized = array_key_exists($article->category, $articleCategoryOptions);

    $articleBreadcrumbItems = [
        [
            '@type' => 'ListItem',
            'position' => 1,
            'name' => 'Home',
            'item' => $articleStructuredDataBaseUrl,
        ],
    ];

    if ($articleCategoryRecognized) {
        $articleBreadcrumbItems[] = [
            '@type' => 'ListItem',
            'position' => 2,
            'name' => $articleCategoryOptions[$article->category],
            'item' => route('categoria', $article->category),
        ];
    }

    // Ultima posizione rinumerata in base a quanti livelli precedono
    // (2 se la categoria è riconosciuta, altrimenti 1): nessun salto di
    // position quando il livello Categoria viene omesso.
    $articleBreadcrumbItems[] = [
        '@type' => 'ListItem',
        'position' => count($articleBreadcrumbItems) + 1,
        'name' => $article->title,
        'item' => $article->metaCanonicalUrl(),
    ];

    // Mission 24/25 — Content Graph Public Consumer (design + V1
    // condizionale): unico consumer pubblico del Content Graph, e
    // deliberatamente solo qui — nessun blocco UI visibile (vedi
    // ArticleController::show()). $discoverableConcepts arriva già
    // filtrato da ContentGraphService::discoverableConceptsForArticle(),
    // quindi qui non si ricontrolla né si ricalcola nulla: solo il
    // reshape in schema.org Thing. Chiave 'about' del tutto assente (non
    // un array vuoto) quando non c'è nulla da dichiarare — degradazione
    // invisibile, mai una sezione "0 concetti" da qualche parte.
    $articleStructuredDataAbout = collect($discoverableConcepts ?? [])
        ->map(fn ($link) => [
            '@type' => 'Thing',
            'name' => $link->concept->name,
        ])
        ->values()
        ->all();

    $articleStructuredData = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'NewsArticle',
                'mainEntityOfPage' => $article->metaCanonicalUrl(),
                // Titolo editoriale visibile, non metaTitle() (potrebbe
                // differire per un SEO title dedicato) e senza troncamento.
                'headline' => $article->title,
                'description' => $article->metaDescription(),
                'image' => array_filter([
                    '@type' => 'ImageObject',
                    'url' => $articleStructuredDataImageUrl,
                    'width' => $articleStructuredDataImageSize ? $articleStructuredDataImageSize[0] : null,
                    'height' => $articleStructuredDataImageSize ? $articleStructuredDataImageSize[1] : null,
                ]),
                'datePublished' => $article->published_at->toIso8601String(),
                ...($lastEditorialUpdate ? ['dateModified' => $lastEditorialUpdate->toIso8601String()] : []),
                'articleSection' => $articleCategoryOptions[$article->category] ?? $article->category,
                'author' => [
                    '@type' => 'Person',
                    'name' => $article->author->name,
                    'url' => route('autore', $article->author),
                ],
                'publisher' => $articleStructuredDataOrganization,
                ...($articleStructuredDataAbout !== [] ? ['about' => $articleStructuredDataAbout] : []),
            ],
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => $articleBreadcrumbItems,
            ],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($articleStructuredData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}</script>
