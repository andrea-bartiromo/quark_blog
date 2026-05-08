@extends('layouts.app')

@section('title', 'Enigma e Bletchley Park — Quark')
@section('description', 'La guerra invisibile dei codici: Alan Turing, Bletchley Park e la macchina Enigma.')

@section('head')
<link rel="stylesheet" href="{{ asset('css/turing.css') }}">
@endsection

@section('content')
<div class="turing-page">
  <article class="turing-article">
    <p class="turing-article__eyebrow">Turing Experience</p>
    <h1>Enigma, Ultra e la guerra invisibile.</h1>
    <p class="turing-article__lead">
      Durante la Seconda guerra mondiale, Alan Turing contribuì al lavoro di Bletchley Park per decifrare i messaggi tedeschi prodotti dalla macchina Enigma. Quel lavoro accelerò la nascita del calcolo automatico moderno e cambiò per sempre il rapporto tra matematica, sicurezza e tecnologia.
    </p>

    <div class="turing-code-panel">
      ENIGMA ROTOR ANALYSIS...<br>
      ULTRA PRIORITY ACTIVE...<br>
      PATTERN DETECTED...<br>
      PROBABILISTIC MODEL UPDATED...<br>
      MESSAGE DECRYPTED.
    </div>

    <div class="turing-note-grid">
      <div class="turing-note">
        <h3>La macchina Enigma</h3>
        <p>Un sistema elettromeccanico basato su rotori, configurazioni giornaliere e combinazioni difficilissime da anticipare.</p>
      </div>
      <div class="turing-note">
        <h3>La Bombe</h3>
        <p>Il contributo di Turing fu decisivo nel trasformare la logica matematica in una macchina capace di restringere lo spazio delle soluzioni.</p>
      </div>
      <div class="turing-note">
        <h3>La nascita del moderno</h3>
        <p>La guerra crittografica anticipa cybersecurity, calcolo automatico e intelligenza artificiale.</p>
      </div>
    </div>
  </article>
</div>
@endsection
