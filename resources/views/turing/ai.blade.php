@extends('layouts.app')

@section('title', 'Turing e l’IA moderna — Quark')
@section('description', 'Dal Test di Turing ai moderni modelli linguistici e all’intelligenza artificiale generativa.')

@section('head')
<link rel="stylesheet" href="{{ asset('css/turing.css') }}">
@endsection

@section('content')
<div class="turing-page">
  <article class="turing-article">
    <p class="turing-article__eyebrow">Turing Experience</p>
    <h1>Le macchine possono pensare?</h1>
    <p class="turing-article__lead">
      Nel 1950 Alan Turing pose una domanda destinata a cambiare il futuro della tecnologia. Oggi, nell’epoca dei modelli linguistici, delle reti neurali e dell’intelligenza artificiale generativa, quella domanda è più viva che mai.
    </p>

    <div class="turing-note-grid">
      <div class="turing-note">
        <h3>Test di Turing</h3>
        <p>Il gioco dell’imitazione come primo grande esperimento filosofico sull’intelligenza artificiale.</p>
      </div>

      <div class="turing-note">
        <h3>LLM e ChatGPT</h3>
        <p>I modelli linguistici contemporanei rappresentano una nuova fase dell’idea di macchina simbolica immaginata da Turing.</p>
      </div>

      <div class="turing-note">
        <h3>Etica e società</h3>
        <p>Algoritmi, potere, sorveglianza, lavoro e automazione ridefiniscono il rapporto uomo-macchina.</p>
      </div>
    </div>

    <div class="turing-code-panel">
      MACHINE LEARNING INITIALIZED...<br>
      LANGUAGE MODEL LOADED...<br>
      TOKEN PREDICTION ACTIVE...<br>
      HUMAN / MACHINE BOUNDARY UNCERTAIN.
    </div>
  </article>
</div>
@endsection
