<section class="turing-section turing-section--final">
  <div class="container container--wide">
    <div class="turing-final-card {{ !empty($finalBackgroundImage) ? 'has-bg' : '' }}" style="{{ $bg($finalBackgroundImage) }}">
      <p class="turing-kicker">{{ $final['kicker'] ?? 'Prossima lettura' }}</p>
      <h2>{{ $final['title'] ?? 'Scegli da dove iniziare' }}</h2>
      <p>{{ $final['text'] ?? 'Vuoi partire dalla guerra dei codici o dalla domanda sull’intelligenza artificiale?' }}</p>
      <div class="turing-actions turing-actions--center">
        <a href="{{ route('turing.enigma') }}">Enigma e Bletchley Park</a>
        <a href="{{ route('turing.ai') }}">Turing e IA moderna</a>
      </div>
    </div>
  </div>
</section>
