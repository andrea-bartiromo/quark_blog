{{--
    Cantiere D — Home + Percorsi Visual Adoption (Prompt 51-55). Nessun
    componente "special-banner" esiste nelle fondamenta V1 (solo 10
    componenti, nessuno chiamato così) — per istruzione esplicita del
    Prompt 52, classi namespaced .kairus-special-banner* invece di un
    undicesimo componente Blade. Stesso URL (route('turing')), stesso
    contenuto, stessi dati ($turingHome, invariato) — solo gli stili
    inline diventano classi. Il background resta inline: è l'unica parte
    realmente dinamica per record (immagine di sfondo del teaser).
--}}
<div class="container container--wide kairus-page-shell">
  <section class="kairus-special-banner" style="{{ $turingHome['style'] }}">
    <div class="kairus-special-banner__grid">
      <div class="kairus-special-banner__content">
        <span class="kairus-special-banner__eyebrow">{{ $turingHome['kicker'] }}</span>

        <h2 class="kairus-special-banner__title">{{ $turingHome['title'] }}</h2>

        <p class="kairus-special-banner__lead">{{ $turingHome['lead'] }}</p>

        <a href="{{ route('turing') }}" class="kairus-special-banner__cta kairus-focusable">
          {{ $turingHome['cta'] }} →
        </a>
      </div>

      {{--
          backdrop-filter qui non è una regola nuova introdotta da questo
          cantiere: è lo stesso effetto già presente nell'inline style
          originale, solo spostato in una classe — non un secondo blur
          aggiunto al sistema.
      --}}
      <div class="kairus-special-banner__terminal">
        {{ $turingHome['terminalTitle'] }}<br>

        @foreach($turingHome['terminalLines'] as $line)
          {{ $line }}<br>
        @endforeach
      </div>
    </div>
  </section>
</div>
