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

    dateModified volutamente assente: updated_at NON rappresenta in modo
    affidabile l'ultima modifica editoriale del contenuto — viene toccato
    anche da $article->increment('views') (ogni singola visualizzazione,
    Builder::increment() aggiunge updated_at alle colonne scritte) e dal
    salvataggio del flusso di verifica editoriale in
    Admin\ArticleController (verification_status/verified_at/verified_by),
    che può avvenire senza alcuna modifica al contenuto. Va introdotto un
    campo dedicato (es. content_updated_at, toccato solo dai percorsi che
    modificano titolo/corpo/categoria/cover) prima di poter pubblicare
    dateModified in modo corretto — commit separato.

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
    // per NewsArticle. Solo 'url': nessuna larghezza/altezza dichiarata,
    // perché non sono garantite per ogni upload futuro (nessun vincolo nel
    // modello) — dichiararle sarebbe un'assunzione, non un dato verificato
    // per questo specifico record.
    $articleStructuredDataImageUrl = filled($article->cover_image)
        ? asset('assets/img/'.$article->cover_image)
        : asset(config('laboratorio.default_share_image'));

    // Stessa fonte già usata per articleSection: opzioni categoria DB-first
    // con fallback a config('laboratorio.categories'), risolta una sola
    // volta e riusata sia per l'etichetta sia per il controllo di
    // riconoscimento del breadcrumb.
    $articleCategoryOptions = \App\Models\Category::options(false);
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
                'image' => [
                    '@type' => 'ImageObject',
                    'url' => $articleStructuredDataImageUrl,
                ],
                'datePublished' => $article->published_at->toIso8601String(),
                'articleSection' => $articleCategoryOptions[$article->category] ?? $article->category,
                'author' => [
                    '@type' => 'Person',
                    'name' => $article->author->name,
                    'url' => route('autore', $article->author),
                ],
                'publisher' => $articleStructuredDataOrganization,
            ],
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => $articleBreadcrumbItems,
            ],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($articleStructuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}</script>
