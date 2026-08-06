@props([
    /* Sorgente e testo alternativo: unici dati davvero obbligatori. Il
       componente non conosce alcun modello (Article, Media, ...): riceve
       stringhe già risolte dal chiamante. */
    'src',
    'alt',

    /* Titolo accessibile del dialog. Se assente, si usa $alt: il dialog
       ha sempre un nome accessibile, senza richiedere un campo dedicato
       quando il chiamante non ne ha uno editoriale distinto. */
    'title' => null,

    /* Metadati editoriali, tutti opzionali: renderizzati solo se valorizzati. */
    'caption' => null,
    'credit' => null,
    'source' => null,
    'sourceUrl' => null,
    'license' => null,

    /* Dimensioni intrinseche note al chiamante. Se assenti e $src risolve a
       un file leggibile sul disco locale, vengono dedotte automaticamente
       (stessa tecnica già in uso in components/special/hotspot-diagram.blade.php
       per evitare layout shift): qui servono a evitare un salto interno al
       dialog mentre l'immagine carica, e a calcolare "adatta allo schermo"
       senza attendere il caricamento.  */
    'width' => null,
    'height' => null,

    /* Variante del trigger. Oggi è supportata solo 'badge' (icona discreta
       in un angolo, pensata per un contenitore già position:relative come
       .article-premium__hero): il prop esiste perché l'interfaccia resti
       stabile quando in futuro si aggiungerà una variante che avvolge un
       elemento del chiamante (es. una figure nel corpo di un articolo),
       senza dover cambiare il modo in cui il componente viene invocato. */
    'variant' => 'badge',

    /* Etichetta del trigger: testo accessibile e, in hover/focus, anche
       visivo (vedi CSS). */
    'triggerLabel' => 'Visualizza immagine completa',

    /* Zoom opzionale (pulsanti +/−/Adatta). Se disattivato, il dialog mostra
       comunque l'immagine per intero, senza ritaglio: lo zoom è un
       arricchimento, non una condizione per vedere l'immagine intera. */
    'enableZoom' => true,

    /* Override esplicito, utile nei test per un id deterministico. Se
       assente, generato qui: garantisce id/data-attribute univoci anche con
       più istanze del componente nella stessa pagina. */
    'id' => null,
])

@php
    $viewerId = $id ?: 'media-viewer-' . \Illuminate\Support\Str::random(10);
    $titleId = $viewerId . '-title';

    $accessibleTitle = filled($title) ? $title : $alt;

    $hasSecondaryMeta = filled($caption) || filled($credit) || filled($source) || filled($license);

    $validSourceUrl = filled($sourceUrl) && filter_var($sourceUrl, FILTER_VALIDATE_URL)
        ? $sourceUrl
        : null;

    /* Deduzione dimensioni intrinseche da file locale, solo se il chiamante
       non le ha già fornite e $src risolve a un percorso su questo stesso
       filesystem (mai una richiesta di rete: niente getimagesize() su URL
       esterni, per non rallentare il render su una risorsa non nostra). */
    $intrinsicWidth = $width;
    $intrinsicHeight = $height;

    if (blank($intrinsicWidth) || blank($intrinsicHeight)) {
        $localPath = parse_url($src, PHP_URL_PATH);

        if ($localPath) {
            $resolvedPath = public_path(ltrim($localPath, '/'));

            if (is_file($resolvedPath)) {
                $dimensions = @getimagesize($resolvedPath);

                if ($dimensions) {
                    $intrinsicWidth = $intrinsicWidth ?: $dimensions[0];
                    $intrinsicHeight = $intrinsicHeight ?: $dimensions[1];
                }
            }
        }
    }
@endphp

@if($variant === 'badge')
<a
    href="{{ $src }}"
    class="media-viewer__trigger media-viewer__trigger--badge"
    data-media-viewer-target="{{ $viewerId }}"
    aria-haspopup="dialog"
>
    <span class="media-viewer__trigger-icon" aria-hidden="true">
        <svg viewBox="0 0 20 20" fill="none" focusable="false">
            <path d="M7.5 3.5h-4v4M12.5 3.5h4v4M7.5 16.5h-4v-4M12.5 16.5h4v-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </span>
    <span class="media-viewer__trigger-label">{{ $triggerLabel }}</span>
</a>
@endif

<div
    class="media-viewer"
    id="{{ $viewerId }}"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $titleId }}"
    hidden
>
    <div class="media-viewer__overlay" data-media-viewer-overlay></div>

    <div class="media-viewer__box">
        <button type="button" class="media-viewer__close" data-media-viewer-close aria-label="Chiudi">
            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false">
                <path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
        </button>

        <div class="media-viewer__stage">
            @if($enableZoom)
            <div class="media-viewer__zoom" role="group" aria-label="Zoom immagine">
                <button type="button" data-media-viewer-zoom-out aria-label="Riduci">
                    <svg viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false"><path d="M3 8h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                </button>
                <button type="button" data-media-viewer-zoom-fit aria-label="Adatta allo schermo">
                    <svg viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false"><rect x="2.5" y="2.5" width="11" height="11" rx="1.5" stroke="currentColor" stroke-width="1.3"/></svg>
                </button>
                <button type="button" data-media-viewer-zoom-in aria-label="Ingrandisci">
                    <svg viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                </button>
            </div>
            @endif

            <div class="media-viewer__frame" data-media-viewer-frame>
                <img
                    src="{{ $src }}"
                    alt="{{ $alt }}"
                    data-media-viewer-image
                    @if($intrinsicWidth) width="{{ $intrinsicWidth }}" @endif
                    @if($intrinsicHeight) height="{{ $intrinsicHeight }}" @endif
                    loading="lazy"
                    decoding="async"
                >
            </div>
        </div>

        <div class="media-viewer__meta">
            <h2 id="{{ $titleId }}" class="media-viewer__title">{{ $accessibleTitle }}</h2>

            @if($hasSecondaryMeta)
            <div class="media-viewer__facts">
                @if($caption)
                <p class="media-viewer__caption">{{ $caption }}</p>
                @endif

                @if($credit || $source || $license)
                <dl class="media-viewer__dl">
                    @if($credit)
                    <div class="media-viewer__fact">
                        <dt>Credito</dt>
                        <dd>{{ $credit }}</dd>
                    </div>
                    @endif

                    @if($source)
                    <div class="media-viewer__fact">
                        <dt>Fonte</dt>
                        <dd>
                            @if($validSourceUrl)
                            <a href="{{ $validSourceUrl }}" target="_blank" rel="noopener noreferrer">{{ $source }}</a>
                            @else
                            {{ $source }}
                            @endif
                        </dd>
                    </div>
                    @endif

                    @if($license)
                    <div class="media-viewer__fact">
                        <dt>Licenza</dt>
                        <dd>{{ $license }}</dd>
                    </div>
                    @endif
                </dl>
                @endif
            </div>
            @endif
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script src="{{ asset('js/media-viewer.js') }}" defer></script>
    @endpush
@endonce
