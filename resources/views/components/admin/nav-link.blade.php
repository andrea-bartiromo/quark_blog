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
  <span class="icon" aria-hidden="true">{{ $icon }}</span>
  <span class="admin-nav__label">{{ $slot }}</span>
</a>
