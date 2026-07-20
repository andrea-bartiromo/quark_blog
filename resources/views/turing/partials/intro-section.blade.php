<section class="turing-section {{ !empty($introBackgroundImage) ? 'has-bg' : '' }}" style="{{ $bg($introBackgroundImage) }}">
  <div class="container container--wide">
    <x-special.section-header
      class="turing-section__head"
      variant="section"
      align="center"
      :kicker="$intro['kicker'] ?? 'Il filo rosso'"
      :title="$intro['title'] ?? 'Dalla crittografia alla coscienza artificiale'"
      :text="$intro['text'] ?? 'Questa area speciale racconta Turing come ponte tra tre mondi: il segreto militare, la nascita del computer e la domanda più difficile sull’intelligenza delle macchine.'"
    />

    <x-special.feature-cards :cards="$cards" label="Percorsi di approfondimento" />
  </div>
</section>
