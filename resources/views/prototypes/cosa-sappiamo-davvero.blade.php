{{--
    PROTOTIPO NON PUBBLICO — "Cosa sappiamo davvero" (missione B-42).

    Questo file NON è instradato da nessuna route e non deve esserlo in
    questo commit: serve solo a validare il layout del template descritto
    in docs/TRUST_LAYER_COSA_SAPPIAMO_DAVVERO_PILOT.md (missioni B-40/B-41)
    prima di qualunque decisione di pilot reale (B-45).

    Prompt 121 (150-prompt program, riesame gate): la dipendenza originale
    (feat/public-article-sources-v1) è stata riconciliata su main da allora
    (PR #532) — il componente reale <x-article.primary-sources> esiste ora
    ed è quello effettivamente usato qui sotto, non più una riproduzione
    statica duplicata. $protoSources sotto ha la stessa forma che
    ArticlePrimarySourcesParser::parse() produce davvero (vedi
    app/Services/ArticlePrimarySourcesParser.php), cosi' questo prototipo
    non può silenziosamente divergere dal contratto reale del componente.

    Contenuto di esempio, non editoriale definitivo: nessuna affermazione
    scientifica qui è verificata, è solo segnaposto per il layout.
--}}
@extends('layouts.app')
@section('title', '[PROTOTIPO] Cosa sappiamo davvero — Kairus')
@section('robots', 'noindex,nofollow')

@section('content')
<div class="public-page">
  <div class="container premium-static">

    <section class="public-hero public-hero--light public-hero--compact">
      <span class="public-hero__kicker">Cosa sappiamo davvero</span>
      <h1>[Esempio] I vaccini a mRNA alterano il DNA umano?</h1>
      <p>Domanda reale posta dai lettori, non un titolo-articolo. Risposta basata su fonti primarie, con esplicita distinzione tra consenso e incertezza.</p>
    </section>

    <section class="premium-static-section premium-copy-card">
      <h2>Cosa sappiamo con ragionevole certezza</h2>
      <p>[Testo di esempio: sintesi del consenso scientifico, con richiamo a fonte primaria numerata.]</p>
    </section>

    <section class="premium-static-section premium-copy-card">
      <h2>Cosa resta incerto o dibattuto</h2>
      <p>[Testo di esempio, se applicabile a questa domanda — mai forzato se non pertinente.]</p>
    </section>

    <section class="premium-static-section premium-copy-card">
      <h2>Cosa manca / limiti di questa risposta</h2>
      <p>[Dichiarazione esplicita dei limiti — mai omessa.]</p>
    </section>

    @php
        // Stessa forma prodotta da ArticlePrimarySourcesParser::parse() —
        // vedi la nota in cima al file: mai duplicare il contratto del
        // componente reale in un formato diverso qui.
        $protoSources = [
            ['type' => 'link', 'text' => '[URL fonte di esempio]', 'url' => '#'],
            ['type' => 'text', 'text' => '[Fonte testuale di esempio]', 'url' => null],
        ];
    @endphp
    <x-article.primary-sources :sources="$protoSources" />

    <section class="premium-static-section">
      <p><strong>Ultimo controllo:</strong> [data di esempio, mai updated_at tecnico]</p>
      <p><strong>Concept collegato:</strong> [nome Concept reale del Content Graph]</p>
    </section>

    <section class="premium-static-section premium-cta-band">
      <h2>Approfondisci</h2>
      <p>[CTA di esempio verso un Percorso o un articolo correlato reale.]</p>
    </section>

  </div>
</div>
@endsection
