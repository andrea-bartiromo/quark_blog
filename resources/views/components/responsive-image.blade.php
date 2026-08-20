@props([
    /* disk_name della Libreria media (es. $article->cover_image, mai un
       URL): quando presente, il componente risolve src/srcset/width/height
       tramite ResponsiveImageVariantService, la stessa risoluzione usata
       ovunque nella missione S2. */
    'diskName' => null,

    /* URL gia' risolto, alternativo a $diskName: usato per i fallback SVG
       data-uri generati in home.blade.php (nessun file su disco, quindi
       nessuna variante possibile ne' sensata) o per qualunque sorgente non
       gestita dalla Libreria media. Se sono presenti entrambi, $diskName
       vince (e' l'unico caso in cui esistono varianti da servire). */
    'src' => null,

    'alt' => '',

    /* Attributo sizes: obbligatorio solo quando esiste davvero un srcset
       (altrimenti il browser lo ignora comunque) — il chiamante lo
       calibra sulla reale occupazione CSS della superficie (card, hero,
       ...), mai un valore generico uguale ovunque. */
    'sizes' => null,

    /* 'lazy' per tutto cio' che e' sotto la piega (default, coerente col
       markup preesistente in tutte le superfici pubbliche). Solo il vero
       candidato LCP di una pagina deve passare 'eager'. */
    'loading' => 'lazy',

    /* MAI impostato di default: solo il chiamante che ha davvero
       identificato QUESTA immagine come candidato LCP della pagina lo
       passa esplicitamente (vedi FASE 7 della missione — non va mai messo
       su ogni immagine). */
    'fetchpriority' => null,

    'decoding' => 'async',

    /* Override espliciti. Se assenti e $diskName risolve a un file
       leggibile, dedotti automaticamente dalle dimensioni reali
       dell'originale (stessa tecnica di components/media/image-viewer.blade.php). */
    'width' => null,
    'height' => null,

    'class' => null,

    /* Sorgente di fallback per onerror, stesso pattern gia' in uso in
       tutte le superfici pubbliche esistenti (placeholder-1.svg,
       hero-placeholder.svg, o l'SVG generato inline in home.blade.php):
       preservato qui, non sostituito. */
    'onerrorSrc' => null,
])

@php
    if (filled($diskName)) {
        $resolved = app(\App\Services\ResponsiveImageVariantService::class)->resolveForMarkup($diskName);
        $resolvedSrc = $resolved['src'];
        $resolvedSrcset = $resolved['srcset'];
        $resolvedWidth = $width ?? $resolved['width'];
        $resolvedHeight = $height ?? $resolved['height'];
    } else {
        $resolvedSrc = $src;
        $resolvedSrcset = '';
        $resolvedWidth = $width;
        $resolvedHeight = $height;
    }

    // sizes senza srcset non ha alcun effetto sul browser, ma emetterlo
    // comunque sarebbe rumore fuorviante nel markup: reso condizionale.
    $hasSrcset = filled($resolvedSrcset);
@endphp
<img src="{{ $resolvedSrc }}"
    @if($hasSrcset) srcset="{{ $resolvedSrcset }}" @endif
    @if($hasSrcset && filled($sizes)) sizes="{{ $sizes }}" @endif
    alt="{{ $alt }}"
    loading="{{ $loading }}"
    decoding="{{ $decoding }}"
    @if(filled($fetchpriority)) fetchpriority="{{ $fetchpriority }}" @endif
    @if($resolvedWidth) width="{{ $resolvedWidth }}" @endif
    @if($resolvedHeight) height="{{ $resolvedHeight }}" @endif
    @if(filled($class)) class="{{ $class }}" @endif
    @if(filled($onerrorSrc)) onerror="this.onerror=null;this.src='{{ $onerrorSrc }}';" @endif
    {{ $attributes }}
>
