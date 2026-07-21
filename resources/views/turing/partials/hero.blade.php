<section class="turing-hero turing-hero--dossier" style="{{ $bg($heroBackgroundImage) }}">
  <div class="container container--wide">
    <div class="turing-hero__grid">
      <div>
        <p class="turing-kicker">{{ $hero['kicker'] ?? 'Quark Special Project' }}</p>
        <h1>{{ $hero['title'] ?? 'Alan Turing' }}</h1>
        <p class="turing-lead">{{ $hero['lead'] ?? 'Una mente che attraversa guerra, matematica, computer e intelligenza artificiale. Turing non è solo una biografia: è una chiave per capire il nostro presente digitale.' }}</p>
        <div class="turing-actions">
          <a href="{{ route('turing.enigma') }}">{{ $hero['primary_label'] ?? 'Esplora Enigma' }}</a>
          <a href="{{ route('turing.ai') }}">{{ $hero['secondary_label'] ?? 'Vai all’IA moderna' }}</a>
        </div>
      </div>

      <figure class="turing-portrait-card" aria-label="Ritratto editoriale di Alan Turing">
        <div class="turing-portrait-card__image">
          <img class="turing-portrait-card__photo"
               src="{{ asset('assets/img/turing/portraits/alan-turing-portrait.png') }}"
               alt="Alan Mathison Turing"
               loading="lazy"
               decoding="async">
          <span class="turing-portrait-years">{{ $hero['portrait_years'] ?? '1912 / 1954' }}</span>
        </div>
        <figcaption>
          <strong>{{ $hero['portrait_title'] ?? 'Alan Mathison Turing' }}</strong>
          <span>{{ $hero['portrait_text'] ?? '1912–1954 · Matematico, logico, pioniere dell’informatica' }}</span>
        </figcaption>
      </figure>
    </div>
  </div>
</section>
