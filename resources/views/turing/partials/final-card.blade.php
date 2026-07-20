<section class="turing-section turing-section--final">
  <div class="container container--wide">
    <div class="turing-final-card {{ !empty($finalBackgroundImage) ? 'has-bg' : '' }}" style="{{ $bg($finalBackgroundImage) }}">
      <x-special.section-header
        variant="final"
        align="center"
        :kicker="$final['kicker'] ?? 'Prossima lettura'"
        :title="$final['title'] ?? 'Scegli da dove iniziare'"
        :text="$final['text'] ?? 'Vuoi partire dalla guerra dei codici o dalla domanda sull’intelligenza artificiale?'"
      />
      <div class="turing-actions turing-actions--center">
        <a href="{{ route('turing.enigma') }}">Enigma e Bletchley Park</a>
        <a href="{{ route('turing.ai') }}">Turing e IA moderna</a>
      </div>
    </div>
  </div>
</section>
