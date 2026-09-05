{{--
    PROTOTIPO NON PUBBLICO — Atlante visuale Kairus, singola tavola
    (missione B-62). Non instradato, non deve esserlo in questo commit.

    Nessun JavaScript, nessuna canvas interattiva — solo HTML/CSS minimo,
    ispirato al pattern accessibile già esistente in
    components/special/hotspot-diagram.blade.php (radio+label, nessun JS,
    dimensioni immagine risolte da file reale) senza replicarne
    l'interattività, non richiesta per una tavola statica V1.

    $article qui sotto è un segnaposto (nessuna query reale in questo
    prototipo): in un pilot reale sarebbe l'Article esistente a cui la
    tavola è collegata — mai una tavola senza un articolo sorgente.
--}}
@extends('layouts.app')
@section('title', '[PROTOTIPO] Atlante visuale — Kairus')
@section('robots', 'noindex,nofollow')

@section('content')
<div class="public-page">
  <div class="container premium-static">

    <section class="public-hero public-hero--light public-hero--compact">
      <span class="public-hero__kicker">Atlante visuale</span>
      <h1>[Esempio] Come si forma un buco nero</h1>
      <p>Una domanda visiva sola per tavola — non una collezione, non un atlante generico.</p>
    </section>

    <section class="premium-static-section premium-copy-card">
      <figure style="margin:0;">
        {{-- In un pilot reale: <x-responsive-image> con width/height reali, mai un placeholder senza dimensioni note. --}}
        <div style="aspect-ratio:16/10;background:#e2e8f0;border-radius:16px;display:flex;align-items:center;justify-content:center;color:#64748b;">
          [Segnaposto tavola visiva — nessuna immagine reale in questo prototipo]
        </div>
        <figcaption style="margin-top:.75rem;font-size:.85rem;color:var(--ink-soft);">
          [Descrizione visiva breve.] Fonte: [attribuzione di esempio] · Versione: v1, [data di esempio]
        </figcaption>
      </figure>
    </section>

    <section class="premium-static-section">
      <p>
        Tavola collegata all'articolo: <a href="#">[titolo articolo sorgente di esempio]</a>.
        Concept collegato (se pertinente): [nome Concept reale del Content Graph].
      </p>
    </section>

  </div>
</div>
@endsection
