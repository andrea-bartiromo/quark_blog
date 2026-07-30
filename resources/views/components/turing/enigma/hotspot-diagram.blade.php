@props([
    'image',
    'alt',
    'caption' => null,
    'hotspots' => [],
    'name' => 'enigma-hotspot',
])

{{--
    Hotspot interamente CSS (radio + label, nessun JavaScript): ogni marcatore
    è una <label for="..."> collegata a un <input type="radio"> nascosto ma
    focalizzabile, quindi utilizzabile via mouse, tocco e tastiera. Su
    desktop i marcatori sono posizionati sopra l'immagine; sotto ~760px
    diventano una riga di pillole numerate sopra l'immagine (stesso
    input/label, solo layout diverso via media query).
--}}
<figure class="turing-article-figure enigma-hotspot-figure">
    <div class="enigma-hotspot">
        @foreach ($hotspots as $i => $hotspot)
            <input
                type="radio"
                name="{{ $name }}"
                id="{{ $name }}-{{ $i }}"
                class="enigma-hotspot__radio sr-only"
                @checked($i === 0)
            >
        @endforeach

        <div class="enigma-hotspot__stage">
            <img
                src="{{ $image }}"
                alt="{{ $alt }}"
                loading="lazy"
                decoding="async"
                class="enigma-hotspot__image"
            >

            <div class="enigma-hotspot__markers">
                @foreach ($hotspots as $i => $hotspot)
                    <label
                        for="{{ $name }}-{{ $i }}"
                        class="enigma-hotspot__marker"
                        style="--hx:{{ $hotspot['x'] }}%; --hy:{{ $hotspot['y'] }}%;"
                    >
                        <span aria-hidden="true">{{ $i + 1 }}</span>
                        <span class="sr-only">{{ $hotspot['title'] }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="enigma-hotspot__panels">
            @foreach ($hotspots as $i => $hotspot)
                <div class="enigma-hotspot__panel" data-panel="{{ $i }}">
                    <span class="enigma-hotspot__panel-index">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
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
