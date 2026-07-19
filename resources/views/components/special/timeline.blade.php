@props([
  /* Collezione/array di eventi. Ogni evento: year, title, text, image, alt, url|link_url */
  'events' => [],
  /* Etichetta e titolo della cover di capitolo */
  'kicker' => 'Timeline',
  'title' => null,
  /* Immagine fotografica della cover (filename in assets/img, path assoluto o URL http) */
  'background' => null,
  /* id della sezione, usato per l'ancora di navigazione */
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
@endphp

@if($items->isNotEmpty())
  <section
    {{ $attributes->merge(['class' => 'sp-timeline']) }}
    id="{{ $id }}"
    @if(filled($title)) aria-labelledby="{{ $titleId }}" @endif
  >
    <div class="container container--wide">
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

      <ol class="sp-timeline__list">
        @foreach($items as $event)
          @php
            $eventUrl = $event['url'] ?? $event['link_url'] ?? null;
            $hasUrl = filled($eventUrl);
            $cardTag = $hasUrl ? 'a' : 'div';
            $media = $resolveMedia($event['image'] ?? null);
          @endphp
          <li class="sp-timeline__item">
            <span class="sp-timeline__marker" aria-hidden="true"></span>
            <{{ $cardTag }}
              class="sp-timeline__card {{ $hasUrl ? 'sp-timeline__card--link' : '' }}"
              @if($hasUrl) href="{{ $eventUrl }}" @endif
            >
              @if(filled($event['year'] ?? null))
                <span class="sp-timeline__year">{{ $event['year'] }}</span>
              @endif
              <div class="sp-timeline__body">
                <h3 class="sp-timeline__event-title">{{ $event['title'] ?? 'Evento' }}</h3>
                @if(filled($event['text'] ?? null))
                  <p class="sp-timeline__text">{{ $event['text'] }}</p>
                @endif
                @if($media)
                  <img
                    class="sp-timeline__media"
                    src="{{ $media }}"
                    alt="{{ $event['alt'] ?? $event['title'] ?? '' }}"
                    loading="lazy"
                    decoding="async"
                  >
                @endif
              </div>
            </{{ $cardTag }}>
          </li>
        @endforeach
      </ol>
    </div>
  </section>
@endif
