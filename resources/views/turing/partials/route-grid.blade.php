@if($cards->isNotEmpty())
  <div class="turing-route-grid">
    @foreach($cards as $card)
      @php $cardUrl = $card['url'] ?? '#'; @endphp
      <a href="{{ $cardUrl }}" class="turing-route-card turing-route-card--{{ $card['style'] ?? 'enigma' }}" style="{{ $bg($card['image'] ?? null) }}">
        <span>{{ $card['label'] ?? 'Percorso' }}</span>
        <h3>{{ $card['title'] ?? 'Approfondimento Turing' }}</h3>
        <p>{{ $card['text'] ?? '' }}</p>
      </a>
    @endforeach
  </div>
@else
  <div class="turing-route-grid">
    <a href="{{ route('turing.enigma') }}" class="turing-route-card turing-route-card--enigma"><span>01 · Bletchley Park</span><h3>La guerra di Enigma</h3><p>Bombe, rotori, messaggi cifrati e una guerra combattuta anche con probabilità e logica.</p></a>
    <a href="{{ route('turing.ai') }}" class="turing-route-card turing-route-card--ai"><span>02 · Macchine intelligenti</span><h3>Dal Test di Turing agli LLM</h3><p>La domanda “le macchine possono pensare?” riletta nell’epoca dell’IA generativa.</p></a>
    <div class="turing-route-card turing-route-card--legacy"><span>03 · Eredità</span><h3>Il genio inquieto</h3><p>La persecuzione, la riabilitazione e l’impatto culturale di una figura diventata simbolo.</p></div>
  </div>
@endif
