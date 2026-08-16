{{--
    Voce singola della sidebar admin. L'icona è sempre aria-hidden (il testo
    dello slot è già l'etichetta accessibile, l'emoji non va annunciata due
    volte). L'etichetta resta sempre nel markup — anche in modalità sidebar
    compatta, dove è solo nascosta visivamente via CSS (mai display:none),
    così resta sempre raggiungibile da tecnologie assistive indipendentemente
    dallo stato JS della sidebar.
--}}
@props(['route', 'active' => false, 'icon' => '', 'external' => false])

<a href="{{ $route }}"
   @class(['active' => $active])
   @if($active) aria-current="page" @endif
   @if($external) target="_blank" rel="noopener" @endif>
  {{--
      title sull'icona, non sul link: un tooltip nativo al passaggio del
      mouse, utile soprattutto in modalità sidebar compatta dove
      l'etichetta è visibile solo alle tecnologie assistive (vedi sopra)
      ma non a schermo — senza questo, un'icona da sola in modalità
      compatta non comunica nulla a un utente vedente che non stia
      usando uno screen reader. Sull'icona invece che sull'intero <a>
      cosi' da non alterare l'attributo esatto atteso dai test esistenti
      sull'evidenziazione del link attivo (AdminNavigationTest). trim()
      perché lo slot può includere spazi bianchi introdotti dalla
      formattazione del template chiamante.
  --}}
  <span class="icon" aria-hidden="true" title="{{ trim((string) $slot) }}">{{ $icon }}</span>
  <span class="admin-nav__label">{{ $slot }}</span>
</a>
