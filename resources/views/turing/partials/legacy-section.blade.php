<section class="turing-section turing-section--split {{ !empty($whyBackgroundImage) ? 'has-bg' : '' }}" id="eredita" style="{{ $bg($whyBackgroundImage) }}">
  <div class="container container--wide">
    <div class="turing-split">
      <div class="turing-image-panel turing-image-panel--machine" style="{{ $bg($whyPanelImage) }}" aria-label="Illustrazione di macchina crittografica e calcolo"></div>
      <div class="turing-copy-panel">
        <x-special.section-header
          variant="panel"
          align="left"
          :kicker="$why['kicker'] ?? 'Perché conta ancora'"
          :title="$why['title'] ?? 'Ogni volta che parliamo di algoritmo, torniamo a Turing.'"
          :text="$why['text'] ?? 'La sua intuizione più potente non fu soltanto costruire macchine, ma immaginare un linguaggio universale per descrivere il calcolo. Oggi quella visione vive nei computer, nella crittografia, nei modelli linguistici e nelle domande etiche sull’automazione.'"
        />

        @if($whyItems->isNotEmpty())
          <div class="turing-mini-grid">
            @foreach($whyItems as $item)
              @php
                /* Cantiere 65 (programma "100 cantieri Kairus"): campo
                   CMS opzionale, nessun width/height dichiarato finora —
                   stesso meccanismo di risoluzione automatica ora usato
                   da <x-turing.article.figure>, per non lasciare al
                   browser un vuoto senza spazio riservato quando un
                   editor imposta questa immagine. */
                $whyItemImage = empty($item['image']) ? null : $img($item['image']);
                $whyItemDimensions = $whyItemImage ? \App\Support\PublicImageDimensions::forUrl($whyItemImage) : null;
              @endphp
              <div>
                @if($whyItemImage)<img src="{{ $whyItemImage }}" alt="{{ $item['alt'] ?? $item['title'] ?? '' }}" loading="lazy" decoding="async" @if($whyItemDimensions) width="{{ $whyItemDimensions[0] }}" height="{{ $whyItemDimensions[1] }}" @endif>@endif
                <strong>{{ $item['title'] ?? 'Idea chiave' }}</strong><span>{{ $item['text'] ?? '' }}</span>
              </div>
            @endforeach
          </div>
        @endif

        <div class="turing-actions">
          <a href="{{ \App\Support\TuringPreviewLink::chapter('legacy', $previewMode ?? false) }}">Approfondisci l’eredità di Turing</a>
        </div>
      </div>
    </div>
  </div>
</section>
