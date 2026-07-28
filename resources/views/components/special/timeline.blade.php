@props([
  /* Collezione/array di eventi. Ogni evento: year, title, text, image, alt,
     url|link_url, e opzionalmente details (approfondimento editoriale più
     lungo, mostrato in una modale dedicata quando valorizzato). */
  'events' => [],
  /* Etichetta e titolo della cover di capitolo */
  'kicker' => 'Timeline',
  'title' => null,
  /* Immagine fotografica della cover (filename in assets/img, path assoluto o URL http) */
  'background' => null,
  /* id della sezione, usato per l'ancora di navigazione. Radice anche degli
     id di modale/trigger di ogni evento (vedi sotto): deve restare univoco
     per invocazione del componente, esattamente come già richiesto oggi per
     l'id della sezione stessa. */
  'id' => 'timeline',
])

@php
  /* Risoluzione immagini interna al componente: nessuna dipendenza dallo scope
     della pagina che lo include, cosi' e' realmente riutilizzabile da qualsiasi
     Special Project. Coerente con il resolver usato dai componenti articolo. */
  $resolveMedia = static function ($value) {
      if (blank($value)) {
          return null;
      }

      $value = trim((string) $value);

      if ($value === '') {
          return null;
      }

      if (str_starts_with($value, 'http') || str_starts_with($value, '/')) {
          return $value;
      }

      $value = str_replace('\\', '/', $value);
      $value = preg_replace('#^.*?/public/assets/img/#', '', $value);
      $value = preg_replace('#^/?public/assets/img/#', '', $value);
      $value = preg_replace('#^/?assets/img/#', '', $value);
      $value = ltrim($value, '/');

      return $value === '' ? null : asset('assets/img/' . $value);
  };

  $items = collect($events)
      ->map(fn ($event) => is_array($event) ? $event : (array) $event)
      ->filter(fn ($event) => filled($event['year'] ?? null)
          || filled($event['title'] ?? null)
          || filled($event['text'] ?? null))
      ->values();

  $cover = $resolveMedia($background);
  $titleId = $id . '-title';

  /* Ogni blocco (cover, lista eventi) e' indipendente: cosi' lo stesso
     componente puo' essere invocato "solo cover" (apertura di sezione, senza
     eventi) oppure "solo eventi" (capitolo successivo alla propria apertura
     narrativa), senza duplicarne il markup altrove. */
  $hasCover = filled($title) || $cover;
@endphp

@if($items->isNotEmpty() || $hasCover)
  <section
    {{ $attributes->merge(['class' => 'sp-timeline']) }}
    id="{{ $id }}"
    @if(filled($title)) aria-labelledby="{{ $titleId }}" @endif
  >
    <div class="container container--wide">
      @if($hasCover)
        <header class="sp-timeline__cover" @if($cover) style="background-image:url('{{ $cover }}')" @endif>
          <div class="sp-timeline__cover-inner">
            @if(filled($kicker))
              <p class="sp-timeline__kicker">{{ $kicker }}</p>
            @endif
            @if(filled($title))
              <h2 id="{{ $titleId }}" class="sp-timeline__title">{{ $title }}</h2>
            @endif
          </div>
        </header>
      @endif

      @if($items->isNotEmpty())
      <ol class="sp-timeline__list">
        @foreach($items as $index => $event)
          @php
            $eventUrl = $event['url'] ?? $event['link_url'] ?? null;
            $hasUrl = filled($eventUrl);
            $hasDetails = filled($event['details'] ?? null);
            $media = $resolveMedia($event['image'] ?? null);

            /* Id deterministico e univoco: radice dalla sezione (già univoca
               per invocazione, vedi il prop 'id' sopra) più la posizione
               dell'evento nella propria collezione. Mai derivato da titolo o
               anno, cosi' due eventi con lo stesso titolo o anno non possono
               mai collidere, e resta stabile fra un render e l'altro. */
            $modalId = $id . '-event-' . $index;

            /* Un evento con 'details' apre una modale tramite un <button>: la
               card non può quindi restare un <a> quando entrambi url e
               details sono presenti, per non annidare un elemento
               interattivo dentro un altro. Senza 'details' il comportamento
               resta esattamente quello di sempre. */
            $cardTag = ($hasUrl && ! $hasDetails) ? 'a' : 'div';
          @endphp
          <li class="sp-timeline__item">
            <span class="sp-timeline__marker" aria-hidden="true"></span>
            <{{ $cardTag }}
              class="sp-timeline__card {{ ($hasUrl && ! $hasDetails) ? 'sp-timeline__card--link' : '' }}"
              @if($hasUrl && ! $hasDetails) href="{{ $eventUrl }}" @endif
            >
              @if(filled($event['year'] ?? null))
                <span class="sp-timeline__year">{{ $event['year'] }}</span>
              @endif
              <div class="sp-timeline__body">
                <h3 class="sp-timeline__event-title">{{ $event['title'] ?? 'Evento' }}</h3>
                @if(filled($event['text'] ?? null))
                  <p class="sp-timeline__text">{{ $event['text'] }}</p>
                @endif
                @if($media && ! $hasDetails)
                  <img
                    class="sp-timeline__media"
                    src="{{ $media }}"
                    alt="{{ $event['alt'] ?? $event['title'] ?? '' }}"
                    loading="lazy"
                    decoding="async"
                  >
                @endif

                @if($hasDetails)
                  <div class="sp-timeline__actions">
                    @if($hasUrl)
                      <a class="sp-timeline__event-link" href="{{ $eventUrl }}">{{ $event['link_label'] ?? 'Scopri di più' }}</a>
                    @endif
                    <button
                      type="button"
                      class="sp-timeline__details-trigger"
                      data-sp-modal-target="{{ $modalId }}"
                      aria-haspopup="dialog"
                      aria-label="Approfondisci: {{ $event['title'] ?? 'evento' }}"
                    >Approfondisci</button>
                  </div>
                @endif
              </div>
            </{{ $cardTag }}>
          </li>

          @if($hasDetails)
            @php
              $detailsParagraphs = collect(preg_split('/\n{2,}/', trim((string) $event['details'])))
                  ->map(fn ($paragraph) => trim($paragraph))
                  ->filter();
            @endphp
            <x-special.modal :id="$modalId" :title="$event['title'] ?? null" size="md" class="sp-timeline__modal">
              @if(filled($event['year'] ?? null))
                <p class="sp-timeline__modal-year">{{ $event['year'] }}</p>
              @endif

              @if($media)
                <img
                  class="sp-timeline__modal-image"
                  src="{{ $media }}"
                  alt="{{ $event['alt'] ?? $event['title'] ?? '' }}"
                  loading="lazy"
                  decoding="async"
                >
              @endif

              <div class="sp-timeline__modal-details">
                @foreach($detailsParagraphs as $paragraph)
                  <p>{{ $paragraph }}</p>
                @endforeach
              </div>
            </x-special.modal>
          @endif
        @endforeach
      </ol>
      @endif
    </div>
  </section>
@endif
