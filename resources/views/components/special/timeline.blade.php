@props([
  /* Collezione/array di eventi. Ogni evento:
       - year, title, text: dati minimi della card sul filo del tempo.
       - image, alt: immagine (card se l'evento non apre una modale,
         altrimenti spostata dentro la modale).
       - url|link_url, link_label: link semplice (esterno o verso un'altra
         pagina), reso come <a> quando l'evento non apre anche una modale.
       - details: approfondimento editoriale più lungo (paragrafi separati
         da riga vuota), mostrato nel corpo della modale.
       - curiosity: breve nota "curiosità", mostrata come riquadro isolato
         dentro la modale — non un secondo blocco di prosa, un aneddoto
         puntuale che integra il resto senza allungarlo.
       - documents: array di {label, url} — fonti/documenti collegati
         all'evento, mostrati come elenco di link dentro la modale.
       - related_links: array di {label, url} — collegamenti interni ad
         altri capitoli dello Speciale pertinenti a questo evento
         specifico, distinti dal link event-level (url/link_url) sopra.
     Un evento apre una modale se possiede almeno uno fra details,
     curiosity, documents, related_links: la sola presenza di year/title/
     text non basta a giustificarne una (vedi $hasModal sotto). */
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
            $mediaDimensions = \App\Support\PublicImageDimensions::forUrl($media);
            $curiosity = $event['curiosity'] ?? null;
            $documents = collect($event['documents'] ?? [])
                ->filter(fn ($doc) => filled($doc['url'] ?? null) && filled($doc['label'] ?? null))
                ->values();
            $relatedLinks = collect($event['related_links'] ?? [])
                ->filter(fn ($link) => filled($link['url'] ?? null) && filled($link['label'] ?? null))
                ->values();

            /* Un evento apre una modale se ha un contenuto reale da mostrarci
               dentro: solo year/title/text non bastano (resterebbe una
               modale vuota). Questo, non $hasDetails da solo, decide se la
               card diventa un trigger di modale — cosi' un evento puo' avere
               una modale anche solo per una curiosita' o dei collegamenti
               correlati, senza dover per forza scrivere un 'details' lungo. */
            $hasModal = $hasDetails || filled($curiosity) || $documents->isNotEmpty() || $relatedLinks->isNotEmpty();

            /* Id deterministico e univoco: radice dalla sezione (già univoca
               per invocazione, vedi il prop 'id' sopra) più la posizione
               dell'evento nella propria collezione. Mai derivato da titolo o
               anno, cosi' due eventi con lo stesso titolo o anno non possono
               mai collidere, e resta stabile fra un render e l'altro. */
            $modalId = $id . '-event-' . $index;

            /* Un evento con una modale la apre tramite un <button>: la card
               non può quindi restare un <a> quando url e modale sono
               entrambi presenti, per non annidare un elemento interattivo
               dentro un altro. Senza modale il comportamento resta esattamente
               quello di sempre (card intera come link). */
            $cardTag = ($hasUrl && ! $hasModal) ? 'a' : 'div';

            $cardClasses = collect(['sp-timeline__card'])
                ->when($hasUrl && ! $hasModal, fn ($classes) => $classes->push('sp-timeline__card--link'))
                ->when($hasModal, fn ($classes) => $classes->push('sp-timeline__card--has-modal'))
                ->implode(' ');
          @endphp
          <li class="sp-timeline__item">
            <span class="sp-timeline__marker" aria-hidden="true"></span>
            <{{ $cardTag }}
              class="{{ $cardClasses }}"
              @if($hasUrl && ! $hasModal) href="{{ $eventUrl }}" @endif
            >
              @if(filled($event['year'] ?? null))
                <span class="sp-timeline__year">{{ $event['year'] }}</span>
              @endif
              <div class="sp-timeline__body">
                <h3 class="sp-timeline__event-title">{{ $event['title'] ?? 'Evento' }}</h3>
                @if(filled($event['text'] ?? null))
                  <p class="sp-timeline__text">{{ $event['text'] }}</p>
                @endif
                @if($media && ! $hasModal)
                  <img
                    class="sp-timeline__media"
                    src="{{ $media }}"
                    alt="{{ $event['alt'] ?? $event['title'] ?? '' }}"
                    @if($mediaDimensions) width="{{ $mediaDimensions[0] }}" height="{{ $mediaDimensions[1] }}" @endif
                    loading="lazy"
                    decoding="async"
                  >
                @endif

                @if($hasModal)
                  <div class="sp-timeline__actions">
                    @if($hasUrl)
                      <a class="sp-timeline__event-link" href="{{ $eventUrl }}">{{ $event['link_label'] ?? 'Scopri di più' }}</a>
                    @endif
                    {{--
                      Pattern "stretched button": il pulsante resta l'unico
                      elemento interattivo semanticamente rilevante (un solo
                      tab-stop per evento, coerente con la tastiera), ma la
                      sua area cliccabile/tappabile copre l'intera card via
                      ::before position:absolute (vedi CSS .sp-timeline__card
                      --has-modal), cosi' l'intero evento è cliccabile come
                      richiesto, non solo la piccola etichetta "Approfondisci".
                      Se è presente anche .sp-timeline__event-link, quel link
                      resta sopra lo stretched button (z-index, vedi CSS) e
                      quindi cliccabile per la propria destinazione separata.
                    --}}
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

            {{-- La modale resta dentro <li>, non come suo fratello: <ol> deve
                 contenere soltanto elementi <li> (unico contenuto valido per
                 una lista), e una modale piazzata fuori da <li> spezzava
                 l'adiacenza richiesta da .sp-timeline__item + .sp-timeline__item
                 in special-project.css, azzerando lo spazio fra le card
                 successive alla prima. La modale resta position:fixed, quindi
                 la sua posizione nel DOM non ha alcun effetto sul suo
                 posizionamento visivo. --}}
            @if($hasModal)
              @php
                $detailsParagraphs = $hasDetails
                    ? collect(preg_split('/\n{2,}/', trim((string) $event['details'])))
                        ->map(fn ($paragraph) => trim($paragraph))
                        ->filter()
                    : collect();
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
                    @if($mediaDimensions) width="{{ $mediaDimensions[0] }}" height="{{ $mediaDimensions[1] }}" @endif
                    loading="lazy"
                    decoding="async"
                  >
                @endif

                @if($detailsParagraphs->isNotEmpty())
                  <div class="sp-timeline__modal-details">
                    @foreach($detailsParagraphs as $paragraph)
                      <p>{{ $paragraph }}</p>
                    @endforeach
                  </div>
                @endif

                @if(filled($curiosity))
                  <aside class="sp-timeline__modal-curiosity">
                    <span class="sp-timeline__modal-curiosity-label">Curiosità</span>
                    <p>{{ $curiosity }}</p>
                  </aside>
                @endif

                @if($documents->isNotEmpty())
                  <div class="sp-timeline__modal-documents">
                    <span class="sp-timeline__modal-section-label">Documenti e fonti</span>
                    <ul>
                      @foreach($documents as $document)
                        <li><a href="{{ $document['url'] }}" target="_blank" rel="noopener noreferrer">{{ $document['label'] }}</a></li>
                      @endforeach
                    </ul>
                  </div>
                @endif

                @if($relatedLinks->isNotEmpty())
                  <div class="sp-timeline__modal-related">
                    <span class="sp-timeline__modal-section-label">Continua nello Speciale</span>
                    <ul>
                      @foreach($relatedLinks as $link)
                        <li><a href="{{ $link['url'] }}">{{ $link['label'] }} →</a></li>
                      @endforeach
                    </ul>
                  </div>
                @endif
              </x-special.modal>
            @endif
          </li>
        @endforeach
      </ol>
      @endif
    </div>
  </section>
@endif
