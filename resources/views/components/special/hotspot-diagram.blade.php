@props([
    'image',
    'alt',
    'caption' => null,
    'hotspots' => [],
    'name' => 'sp-hotspot',
])

@php
    /* .sp-hotspot__image ha width:100% e height:auto (CSS): senza gli
       attributi width/height sull'<img>, il browser non conosce il rapporto
       d'aspetto prima del caricamento e riserva altezza 0, causando un
       layout shift quando l'immagine (lazy) carica — che a sua volta fa
       atterrare male qualsiasi ancora verso una sezione successiva calcolata
       prima dello shift. $image può arrivare da CMS (dimensioni non note a
       priori): le risolviamo qui, una sola volta, leggendo il file reale sul
       disco locale invece di presumerle, cosi' il fix regge qualunque
       immagine passata al componente, non solo quella di oggi. Se il file
       non è risolvibile localmente (es. URL davvero esterno), width/height
       restano assenti e il comportamento torna quello preesistente. */
    $hotspotImageDimensions = null;
    $hotspotImagePath = parse_url($image, PHP_URL_PATH);

    if ($hotspotImagePath) {
        $hotspotImageLocalPath = public_path(ltrim($hotspotImagePath, '/'));

        if (is_file($hotspotImageLocalPath)) {
            $hotspotImageDimensions = @getimagesize($hotspotImageLocalPath);
        }
    }
@endphp

{{--
    Hotspot interamente CSS (radio + label, nessun JavaScript): ogni marcatore
    è una <label for="..."> collegata a un <input type="radio"> nascosto ma
    focalizzabile, quindi utilizzabile via mouse, tocco e tastiera. Su
    desktop i marcatori sono posizionati sopra l'immagine; sotto ~760px
    diventano una riga di pillole numerate sopra l'immagine (stesso
    input/label, solo layout diverso via media query).
--}}
<figure class="turing-article-figure sp-hotspot-figure">
    <div class="sp-hotspot">
        @foreach ($hotspots as $i => $hotspot)
            <input
                type="radio"
                name="{{ $name }}"
                id="{{ $name }}-{{ $i }}"
                class="sp-hotspot__radio sr-only"
                @checked($i === 0)
            >
        @endforeach

        <div class="sp-hotspot__stage">
            <img
                src="{{ $image }}"
                alt="{{ $alt }}"
                loading="lazy"
                decoding="async"
                class="sp-hotspot__image"
                @if($hotspotImageDimensions)
                    width="{{ $hotspotImageDimensions[0] }}"
                    height="{{ $hotspotImageDimensions[1] }}"
                @endif
            >

            <div class="sp-hotspot__markers">
                @foreach ($hotspots as $i => $hotspot)
                    <label
                        for="{{ $name }}-{{ $i }}"
                        class="sp-hotspot__marker"
                        style="--hx:{{ $hotspot['x'] }}%; --hy:{{ $hotspot['y'] }}%;"
                    >
                        <span aria-hidden="true">{{ $i + 1 }}</span>
                        <span class="sr-only">{{ $hotspot['title'] }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="sp-hotspot__panels">
            @foreach ($hotspots as $i => $hotspot)
                <div class="sp-hotspot__panel" data-panel="{{ $i }}">
                    <span class="sp-hotspot__panel-index">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                    <h3>{{ $hotspot['title'] }}</h3>
                    <p>{{ $hotspot['text'] }}</p>
                </div>
            @endforeach
        </div>
    </div>

    @if (filled($caption))
        <figcaption>{{ $caption }}</figcaption>
    @endif
</figure>
