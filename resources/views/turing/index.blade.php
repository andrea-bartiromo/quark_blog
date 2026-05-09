@extends('layouts.app')

@section('title', 'Alan Turing — Quark')
@section('description', 'Una sezione speciale di Quark dedicata ad Alan Turing, alla crittografia, alla Seconda guerra mondiale e all’intelligenza artificiale moderna.')

@section('head')
<link rel="stylesheet" href="{{ asset('css/turing.css') }}">
@endsection

@section('content')
<section class="turing-hero turing-hero--dossier">
  <div class="container container--wide">
    <div class="turing-hero__grid">
      <div>
        <p class="turing-kicker">Quark Special Project</p>
        <h1>Alan Turing</h1>
        <p class="turing-lead">
          Una mente che attraversa guerra, matematica, computer e intelligenza artificiale.
          Turing non è solo una biografia: è una chiave per capire il nostro presente digitale.
        </p>
        <div class="turing-actions">
          <a href="{{ route('turing.enigma') }}">Esplora Enigma</a>
          <a href="{{ route('turing.ai') }}">Vai all’IA moderna</a>
        </div>
      </div>

      <figure class="turing-portrait-card" aria-label="Ritratto editoriale di Alan Turing">
        <div class="turing-portrait-card__image"></div>
        <figcaption>
          <strong>Alan Mathison Turing</strong>
          <span>1912–1954 · Matematico, logico, pioniere dell’informatica</span>
        </figcaption>
      </figure>
    </div>
  </div>
</section>

<section class="turing-section">
  <div class="container container--wide">
    <div class="turing-section__head">
      <p class="turing-kicker">Il filo rosso</p>
      <h2>Dalla crittografia alla coscienza artificiale</h2>
      <p>
        Questa area speciale racconta Turing come ponte tra tre mondi: il segreto militare,
        la nascita del computer e la domanda più difficile sull’intelligenza delle macchine.
      </p>
    </div>

    <div class="turing-route-grid">
      <a href="{{ route('turing.enigma') }}" class="turing-route-card turing-route-card--enigma">
        <span>01 · Bletchley Park</span>
        <h3>La guerra di Enigma</h3>
        <p>Bombe, rotori, messaggi cifrati e una guerra combattuta anche con probabilità e logica.</p>
      </a>

      <a href="{{ route('turing.ai') }}" class="turing-route-card turing-route-card--ai">
        <span>02 · Macchine intelligenti</span>
        <h3>Dal Test di Turing agli LLM</h3>
        <p>La domanda “le macchine possono pensare?” riletta nell’epoca dell’IA generativa.</p>
      </a>

      <div class="turing-route-card turing-route-card--legacy">
        <span>03 · Eredità</span>
        <h3>Il genio inquieto</h3>
        <p>La persecuzione, la riabilitazione e l’impatto culturale di una figura diventata simbolo.</p>
      </div>
    </div>
  </div>
</section>

<section class="turing-section turing-section--split">
  <div class="container container--wide">
    <div class="turing-split">
      <div class="turing-image-panel turing-image-panel--machine" aria-label="Illustrazione di macchina crittografica e calcolo"></div>
      <div class="turing-copy-panel">
        <p class="turing-kicker">Perché conta ancora</p>
        <h2>Ogni volta che parliamo di algoritmo, torniamo a Turing.</h2>
        <p>
          La sua intuizione più potente non fu soltanto costruire macchine, ma immaginare un linguaggio
          universale per descrivere il calcolo. Oggi quella visione vive nei computer, nella crittografia,
          nei modelli linguistici e nelle domande etiche sull’automazione.
        </p>
        <div class="turing-mini-grid">
          <div><strong>Calcolo</strong><span>la macchina universale</span></div>
          <div><strong>Sicurezza</strong><span>codici, cifrari, decrittazione</span></div>
          <div><strong>IA</strong><span>imitazione, linguaggio, giudizio</span></div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="turing-section turing-section--dark">
  <div class="container container--wide">
    <div class="turing-section__head">
      <p class="turing-kicker">Timeline</p>
      <h2>Una vita che attraversa il Novecento</h2>
      <p>Dalla matematica pura alla guerra crittografica, fino alla domanda che ancora oggi definisce il confine tra calcolo e intelligenza.</p>
    </div>

    <div class="turing-timeline">
      <div class="turing-timeline__item"><div class="turing-timeline__year">1912</div><div><h3>Nasce Alan Mathison Turing</h3><p>Un talento fuori dagli schemi, attratto presto dai numeri, dalla logica e dalla ricerca della verità.</p></div></div>
      <div class="turing-timeline__item"><div class="turing-timeline__year">1936</div><div><h3>La macchina universale</h3><p>Con <em>On Computable Numbers</em> immagina il principio teorico del computer moderno.</p></div></div>
      <div class="turing-timeline__item"><div class="turing-timeline__year">1939</div><div><h3>Bletchley Park</h3><p>La matematica entra nel cuore della guerra: decifrare Enigma diventa una questione di sopravvivenza.</p></div></div>
      <div class="turing-timeline__item"><div class="turing-timeline__year">1950</div><div><h3>Computing Machinery and Intelligence</h3><p>La domanda cambia forma: non che cosa sia una macchina, ma se possa apparire intelligente.</p></div></div>
      <div class="turing-timeline__item"><div class="turing-timeline__year">Oggi</div><div><h3>L’era degli algoritmi</h3><p>LLM, IA generativa, cybersecurity e automazione riportano Turing al centro del presente.</p></div></div>
    </div>
  </div>
</section>

<section class="turing-section turing-section--final">
  <div class="container container--wide">
    <div class="turing-final-card">
      <p class="turing-kicker">Prossima lettura</p>
      <h2>Scegli da dove iniziare</h2>
      <p>Vuoi partire dalla guerra dei codici o dalla domanda sull’intelligenza artificiale?</p>
      <div class="turing-actions turing-actions--center">
        <a href="{{ route('turing.enigma') }}">Enigma e Bletchley Park</a>
        <a href="{{ route('turing.ai') }}">Turing e IA moderna</a>
      </div>
    </div>
  </div>
</section>
@endsection
