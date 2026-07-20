@props([
  /* Collezione di card. Ogni card: label, title, text, url|link_url, image, alt, style (accento cosmetico opzionale) */
  'cards' => [],
  /* Etichetta del gruppo per l'accessibilita' (facoltativa: se assente, aria-label viene omesso) */
  'label' => null,
  /* id del gruppo, usato per l'ancora di navigazione */
  'id' => null,
])

@php
  /* Stesso resolver auto-contenuto usato da <x-special.timeline> e
     <x-special.chapter-opener>: nessuna dipendenza dallo scope della pagina
     che lo include, cosi' il componente resta riutilizzabile da qualsiasi
     Special Project. */
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

  $items = collect($cards)
      ->map(fn ($card) => is_array($card) ? $card : (array) $card)
      ->filter(fn ($card) => filled($card['label'] ?? null)
          || filled($card['title'] ?? null)
          || filled($card['text'] ?? null))
      ->values();
@endphp

@if($items->isNotEmpty())
  <ul
    {{ $attributes->merge(['class' => 'sp-feature-cards']) }}
    @if($id) id="{{ $id }}" @endif
    @if(filled($label)) aria-label="{{ $label }}" @endif
  >
    @foreach($items as $card)
      @php
        $cardUrl = trim((string) ($card['url'] ?? $card['link_url'] ?? ''));
        $hasUrl = $cardUrl !== '' && $cardUrl !== '#';
        $cardTag = $hasUrl ? 'a' : 'div';
        $media = $resolveMedia($card['image'] ?? null);
        $variant = preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($card['style'] ?? '')));
      @endphp
      <li class="sp-feature-cards__item">
        <{{ $cardTag }}
          class="sp-feature-card {{ $hasUrl ? 'sp-feature-card--link' : 'sp-feature-card--static' }} {{ $variant !== '' ? 'sp-feature-card--'.$variant : '' }}"
          @if($hasUrl) href="{{ $cardUrl }}" @else aria-disabled="true" @endif
        >
          @if($media)
            <span class="sp-feature-card__media" style="background-image:url('{{ $media }}')" aria-hidden="true"></span>
          @endif
          <span class="sp-feature-card__body">
            @if(filled($card['label'] ?? null))
              <span class="sp-feature-card__label">{{ $card['label'] }}</span>
            @endif
            @if(filled($card['title'] ?? null))
              <h3 class="sp-feature-card__title">{{ $card['title'] }}</h3>
            @endif
            @if(filled($card['text'] ?? null))
              <p class="sp-feature-card__text">{{ $card['text'] }}</p>
            @endif
          </span>
        </{{ $cardTag }}>
      </li>
    @endforeach
  </ul>
@endif
