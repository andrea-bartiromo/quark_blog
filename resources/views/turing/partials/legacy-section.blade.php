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
              <div>
                @if(!empty($item['image']))<img src="{{ $img($item['image']) }}" alt="{{ $item['alt'] ?? $item['title'] ?? '' }}">@endif
                <strong>{{ $item['title'] ?? 'Idea chiave' }}</strong><span>{{ $item['text'] ?? '' }}</span>
              </div>
            @endforeach
          </div>
        @endif

        <div class="turing-actions">
          <a href="{{ route('turing.legacy') }}">Approfondisci l’eredità di Turing</a>
        </div>
      </div>
    </div>
  </div>
</section>
