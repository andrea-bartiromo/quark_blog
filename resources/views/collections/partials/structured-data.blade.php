{{--
    Dati strutturati CollectionPage (schema.org) per le pagine archivio:
    /notizie e /categoria/{slug}. Partial condiviso perché la struttura è
    identica in entrambi i casi: cambia solo $collectionName, passato
    esplicitamente dalla vista che include questo partial.

    Nessun ItemList: gli articoli elencati hanno già il proprio nodo
    NewsArticle completo sulla propria pagina (headline, url, publisher...).
    Duplicarli qui non corrisponde a un rich result Google dedicato per un
    elenco generico di NewsArticle, e aggiungerebbe solo onere di
    manutenzione (posizione, paginazione) senza un beneficio verificabile —
    stessa disciplina già seguita per SearchAction e dateModified.

    url/@id devono descrivere lo stesso URL normalizzato dichiarato dal
    canonical della pagina: pagina 1 senza ?page=1, pagina N su se stessa,
    nessun parametro UTM/tracking. Le viste /notizie e /categoria impostano
    entrambe la sezione canonical prima di includere questo partial.

    isPartOf referenzia il WebSite della homepage per solo @id (non un nodo
    duplicato): a differenza di NewsArticle.publisher, isPartOf non ha un
    requisito Google di oggetto inline completo — è un collegamento
    relazionale tra pagine dello stesso sito.
--}}
@php
    $collectionPageUrl = trim((string) $__env->yieldContent('canonical'));

    $collectionPageDescription = trim((string) $__env->yieldContent('description'));

    $collectionStructuredData = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'CollectionPage',
                '@id' => $collectionPageUrl.'#collectionpage',
                'url' => $collectionPageUrl,
                'name' => $collectionName,
                'description' => $collectionPageDescription,
                'isPartOf' => [
                    '@id' => url('/').'/#website',
                ],
            ],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($collectionStructuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}</script>
