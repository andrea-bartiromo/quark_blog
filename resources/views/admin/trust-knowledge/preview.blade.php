{{--
    Cantiere 40 (programma "100 cantieri Kairus"): anteprima di sola
    lettura di una voce "Cosa sappiamo davvero", riservata allo staff
    (dentro il gruppo di rotte auth+editor) — MAI una route pubblica.

    Decisione di scope esplicita (portata all'utente e confermata,
    vedi l'addendum "Cantiere 40" in
    docs/TRUST_LAYER_COSA_SAPPIAMO_DAVVERO_PILOT.md): il NO-GO B-45 per
    "il pilot manuale" (pubblicazione reale a utenti reali) resta in
    vigore — nessuna delle sue condizioni mancanti (owner editoriale
    assegnato, contenuto reale approvato) è soddisfatta da questo
    cantiere. Questa vista è quindi raggiungibile SOLO da
    TrustKnowledgeStatementController::preview(), mai da una route
    pubblica — stesso pattern già in uso in
    Admin\CategoryController::preview() (Cantiere 11): riusa il layout
    pubblico reale (per non far divergere anteprima e pagina reale nel
    tempo se/quando un pilot verrà approvato), con un banner di
    anteprima e `noindex,nofollow` come difesa in profondità.

    Struttura riadattata dal prototipo statico originale (missione B-42,
    resources/views/prototypes/cosa-sappiamo-davvero.blade.php) con dati
    reali al posto dei segnaposto. Fonti/CTA/metrica primaria restano
    omessi: non sono campi che TrustKnowledgeStatement salva oggi (si
    legga il suo docblock) — aggiungerli resta esplicitamente fuori da
    questo cantiere.

    Blocco consenso/incertezza/cosa_manca estratto nel Cantiere 41 in
    x-kairus.trust-knowledge-summary (componente riusabile e testato in
    isolamento) — qui resta solo il resto dell'impaginazione specifica
    della preview (banner, titolo, riepilogo ultimo controllo/collegamenti).
--}}
@extends('layouts.app')
@section('title', '[Anteprima] '.$statement->domanda.' — '.config('laboratorio.name'))
@section('robots', 'noindex,nofollow')

@section('content')
<div style="background:#fef3c7;color:#78350f;padding:.85rem 1rem;text-align:center;font-weight:700;font-size:.88rem;">
  Anteprima amministrativa — questo contenuto "Cosa sappiamo davvero" non è pubblico, non esiste nessuna route pubblica per questo modello.
</div>
<div class="public-page">
  <div class="container premium-static">

    <section class="public-hero public-hero--light public-hero--compact">
      <span class="public-hero__kicker">Cosa sappiamo davvero</span>
      <h1>{{ $statement->domanda }}</h1>
    </section>

    <x-kairus.trust-knowledge-summary
      :consenso="$statement->consenso"
      :incertezza="$statement->incertezza"
      :cosa-manca="$statement->cosa_manca"
    />

    <section class="premium-static-section">
      <p>
        <strong>Ultimo controllo:</strong>
        @if($statement->hasBeenChecked())
          {{ $statement->last_checked_at->format('d/m/Y') }}
          @if($statement->last_checked_by)
            ({{ $statement->last_checked_by }})
          @endif
        @else
          Mai controllato
        @endif
      </p>
      @if($statement->concept)
        <p><strong>Concept collegato:</strong> {{ $statement->concept->name }}</p>
      @endif
      @if($statement->contentCluster)
        <p><strong>Percorso collegato:</strong> {{ $statement->contentCluster->name }}</p>
      @endif
    </section>

  </div>
</div>
@endsection
