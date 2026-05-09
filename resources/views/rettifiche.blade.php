@extends('layouts.app')
@section('title', 'Rettifiche — Quark')
@section('description', 'La politica di rettifica di Quark. Come correggere gli errori in modo trasparente.')

@section('content')
<div class="public-page public-page--corrections">
  <div class="container premium-static">

    <section class="public-hero public-hero--light public-hero--compact">
      <span class="public-hero__kicker">Trasparenza</span>
      <h1>Rettifiche</h1>
      <p>
        Quark si impegna a correggere gli errori in modo rapido, visibile e tracciabile.
        La precisione scientifica è il fondamento del nostro lavoro editoriale.
      </p>
      <div class="public-hero__meta">
        <span>Correzioni esplicite</span>
        <span>Fonti verificabili</span>
        <span>Responsabilità editoriale</span>
      </div>
    </section>

    <section class="premium-static-section">
      <div class="public-section-head">
        <div>
          <span>Metodo</span>
          <h2>La nostra politica</h2>
        </div>
      </div>

      <div class="premium-principles-grid">
        @foreach([
          ['⚡', 'Rapidità', 'Gli errori fattuali vengono verificati e corretti il prima possibile dopo la segnalazione.'],
          ['👁️', 'Trasparenza', 'Le correzioni rilevanti vengono indicate nell’articolo con data e natura dell’intervento.'],
          ['📧', 'Comunicazione', 'Se l’errore è significativo, può essere segnalato anche nei canali editoriali disponibili.'],
          ['🏆', 'Riconoscimento', 'Chi segnala un errore può essere ringraziato nell’articolo corretto, se lo desidera.'],
        ] as [$icon, $title, $desc])
          <article class="premium-principle">
            <div class="premium-principle__icon">{{ $icon }}</div>
            <h3>{{ $title }}</h3>
            <p>{{ $desc }}</p>
          </article>
        @endforeach
      </div>
    </section>

    <section class="premium-static-section premium-copy-card">
      <h2>Come segnalare un errore</h2>
      <p>
        Per aiutarci a verificare rapidamente una segnalazione, includi nel messaggio le informazioni essenziali.
      </p>

      <div class="premium-legal-list">
        @foreach([
          ['Articolo', 'Indica il titolo o l’URL dell’articolo a cui ti riferisci.'],
          ['Punto da correggere', 'Spiega quale informazione ritieni errata o incompleta.'],
          ['Fonte', 'Se possibile, allega una fonte affidabile o il riferimento che ritieni corretto.'],
        ] as [$title, $desc])
          <article class="premium-legal-item">
            <h3>{{ $title }}</h3>
            <p>{{ $desc }}</p>
          </article>
        @endforeach
      </div>

      <a class="premium-button" href="{{ route('contatti') }}">Segnala un errore</a>
    </section>

    <section class="premium-static-section public-empty-state public-empty-state--soft">
      <span>✅</span>
      <h3>Storico rettifiche</h3>
      <p>Nessuna rettifica registrata al momento.</p>
    </section>

  </div>
</div>
@endsection
