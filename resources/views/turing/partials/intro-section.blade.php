<section class="turing-section {{ !empty($introBackgroundImage) ? 'has-bg' : '' }}" style="{{ $bg($introBackgroundImage) }}">
  <div class="container container--wide">
    <div class="turing-section__head">
      <p class="turing-kicker">{{ $intro['kicker'] ?? 'Il filo rosso' }}</p>
      <h2>{{ $intro['title'] ?? 'Dalla crittografia alla coscienza artificiale' }}</h2>
      <p>{{ $intro['text'] ?? 'Questa area speciale racconta Turing come ponte tra tre mondi: il segreto militare, la nascita del computer e la domanda più difficile sull’intelligenza delle macchine.' }}</p>
    </div>

    @include('turing.partials.route-grid')
  </div>
</section>
