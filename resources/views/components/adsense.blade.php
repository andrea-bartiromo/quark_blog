{{--
  Componente AdSense — Quark
  Uso: @include('components.adsense', ['slot' => 'XXXXXXXXXX'])

  Posizioni disponibili:
  - 'articolo-top'    → sotto il titolo, sopra il testo
  - 'articolo-middle' → a metà dell'articolo
  - 'articolo-bottom' → dopo il testo
  - 'sidebar'         → nella sidebar
  - 'lista'           → tra gli articoli in lista

  Per attivare:
  1. Ottieni il Publisher ID da https://adsense.google.com
  2. Crea un Ad Unit per ogni posizione
  3. Sostituisci ca-pub-XXXXXXXXXXXXXXXXX e data-ad-slot
  4. Rimuovi il commento @if(false) / @endif
--}}

@if(false) {{-- Rimuovere questa riga quando AdSense è attivo --}}
<ins class="adsbygoogle"
     style="display:block;{{ $style ?? '' }}"
     data-ad-client="ca-pub-XXXXXXXXXXXXXXXXX"
     data-ad-slot="{{ $slot ?? '0000000000' }}"
     data-ad-format="{{ $format ?? 'auto' }}"
     data-full-width-responsive="true"></ins>
<script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
@endif