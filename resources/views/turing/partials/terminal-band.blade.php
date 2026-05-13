<section class="turing-terminal-band">
  <div class="container container--wide">
    <div class="turing-terminal-card">
      <span>{{ $hero['terminal_title'] ?? 'TURING ARCHIVE' }}</span>

      @foreach($terminalLines as $line)
        <code>{{ $line }}</code>
      @endforeach
    </div>
  </div>
</section>
