@extends('layouts.app')

@section('title', 'Alan Turing — Quark')
@section('description', 'Una sezione speciale di Quark dedicata ad Alan Turing, alla crittografia, alla Seconda guerra mondiale e all’intelligenza artificiale moderna.')

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
@endsection