@extends('layouts.app')

@section('title', 'Alan Turing — Quark')
@section('description', 'Una sezione speciale di Quark dedicata ad Alan Turing, alla crittografia, alla Seconda guerra mondiale e all’intelligenza artificiale moderna.')

@section('head')
<link rel="stylesheet" href="{{ asset('css/turing.css') }}">
@endsection

@section('content')
<section class="turing-hero">
  <div class="container container--wide">
    <div class="turing-hero__grid">
      <div>
        <p class="turing-kicker">Quark Special Project</p>
        <h1>Alan Turing</h1>
        <p class="turing-lead">L’uomo che ha decifrato il futuro: dalla macchina Enigma alla nascita dell’informatica moderna, fino alle domande aperte dell’intelligenza artificiale.</p>
        <div class="turing-actions">
          <a href="{{ route('turing.enigma') }}">Enigma e guerra</a>
          <a href="{{ route('turing.ai') }}">Turing e IA</a>
        </div>
      </div>
      <div class="turing-terminal" aria-label="Terminale narrativo">
        <span>QUARK / TURING ARCHIVE</span>
        <code>
          input: encrypted_message<br>
          method: logic + probability<br>
          context: Bletchley Park<br>
          output: a new idea of machine<br><br>
          question: can machines think?<br>
          status: still open
        </code>
      </div>
    </div>
  </div>
</section>

<section class="turing-section">
  <div class="container container--wide">
    <div class="turing-section__head">
      <p class="turing-kicker">Il filo rosso</p>
      <h2>Dalla crittografia alla coscienza artificiale</h2>
      <p>Questa area speciale racconta Turing non come una semplice biografia, ma come una chiave per capire il presente: sicurezza informatica, calcolo, macchine intelligenti, potere della tecnica e rapporto tra uomo e algoritmo.</p>
    </div>

    <div class="turing-card-grid">
      <a href="{{ route('turing.enigma') }}" class="turing-card">
        <span>01</span>
        <h3>La guerra di Enigma</h3>
        <p>Bletchley Park, i codici tedeschi, la Bombe e il ruolo della matematica nella storia.</p>
      </a>

      <a href="{{ route('turing.ai') }}" class="turing-card">
        <span>02</span>
        <h3>La domanda sull’intelligenza</h3>
        <p>Dal gioco dell’imitazione ai modelli linguistici contemporanei.</p>
      </a>

      <div class="turing-card">
        <span>03</span>
        <h3>Il genio inquieto</h3>
        <p>La solitudine, la verità, la persecuzione e l’eredità umana di Turing.</p>
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
      <div class="turing-timeline__item"><div class="turing-timeline__year">1936</div><div><h3>La macchina universale</h3><p>Con On Computable Numbers immagina il principio teorico del computer moderno.</p></div></div>
      <div class="turing-timeline__item"><div class="turing-timeline__year">1939</div><div><h3>Bletchley Park</h3><p>La matematica entra nel cuore della guerra: decifrare Enigma diventa una questione di sopravvivenza.</p></div></div>
      <div class="turing-timeline__item"><div class="turing-timeline__year">1950</div><div><h3>Computing Machinery and Intelligence</h3><p>La domanda cambia forma: non che cosa sia una macchina, ma se possa apparire intelligente.</p></div></div>
      <div class="turing-timeline__item"><div class="turing-timeline__year">Oggi</div><div><h3>L’era degli algoritmi</h3><p>LLM, IA generativa, cybersecurity e automazione riportano Turing al centro del presente.</p></div></div>
    </div>
  </div>
</section>
@endsection
