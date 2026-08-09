{{--
    Gruppo collassabile della sidebar admin, basato su <details>/<summary>
    nativi: nessun JavaScript necessario per aprire/chiudere, stato
    espanso/collassato comunicato automaticamente alle tecnologie
    assistive dal browser (aggiungere aria-expanded a mano andrebbe
    disallineato dal vero stato dopo un click, perché <details> lo
    gestisce internamente senza eventi che lo script della sidebar possa
    intercettare qui).

    $open: calcolato lato server da layouts/admin.blade.php (una pagina
    appartenente al gruppo lo forza aperto), non da preferenza utente:
    è sempre corretto al primo render, prima che qualunque JS giri.
--}}
@props(['label', 'open' => false])

<details class="admin-nav__group" @if($open) open @endif>
  <summary class="admin-nav__section admin-nav__section--toggle">{{ $label }}</summary>
  {{ $slot }}
</details>
