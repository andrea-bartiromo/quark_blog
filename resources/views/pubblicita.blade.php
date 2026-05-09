@extends('layouts.app')
@section('title', 'Pubblicità e collaborazioni — Quark')
@section('description', 'Collabora con Quark. Opportunità pubblicitarie e partnership per raggiungere un pubblico scientifico qualificato.')

@section('content')
<div class="public-page public-page--advertising">
  <div class="container premium-static">

    <section class="public-hero public-hero--light public-hero--compact">
      <span class="public-hero__kicker">Collabora con Quark</span>
      <h1>Pubblicità e collaborazioni</h1>
      <p>
        Quark raggiunge un pubblico curioso, giovane e interessato a scienza, tecnologia,
        spazio e innovazione. Collaboriamo solo con progetti coerenti con la nostra linea editoriale.
      </p>
      <div class="public-hero__meta">
        <span>Audience scientifica</span>
        <span>Brand safety</span>
        <span>Contenuti verificati</span>
      </div>
    </section>

    <section class="premium-static-section">
      <div class="public-section-head">
        <div>
          <span>Quark in numeri</span>
          <h2>Un pubblico verticale e qualificato</h2>
        </div>
      </div>

      <div class="premium-principles-grid">
        @foreach([
          ['30+', 'Articoli pubblicati', 'Contenuti editoriali originali dedicati alla divulgazione scientifica.'],
          ['6', 'Categorie scientifiche', 'Fisica, biologia, tecnologia, spazio, ambiente e medicina.'],
          ['100%', 'Contenuti verificati', 'Ogni articolo segue un processo di revisione e verifica delle fonti.'],
        ] as [$num, $title, $desc])
          <article class="premium-principle">
            <div class="premium-principle__icon">{{ $num }}</div>
            <h3>{{ $title }}</h3>
            <p>{{ $desc }}</p>
          </article>
        @endforeach
      </div>
    </section>

    <section class="premium-static-section premium-copy-card">
      <h2>Opportunità disponibili</h2>

      <div class="premium-legal-list">
        @foreach([
          ['Banner display', 'Posizionamenti premium in homepage, sidebar e pagine articolo.'],
          ['Contenuti sponsorizzati', 'Approfondimenti editoriali sempre etichettati come sponsorizzati.'],
          ['Newsletter sponsorship', 'Presenza dedicata all’interno della newsletter di Quark.'],
          ['Partnership editoriali', 'Collaborazioni con università, startup, enti di ricerca e aziende tech.'],
        ] as [$title, $desc])
          <article class="premium-legal-item">
            <h3>{{ $title }}</h3>
            <p>{{ $desc }}</p>
          </article>
        @endforeach
      </div>
    </section>

    <section class="premium-static-section premium-copy-card">
      <h2>La nostra policy pubblicitaria</h2>

      <div class="premium-info-list">
        @foreach([
          'Accettiamo solo campagne coerenti con divulgazione, ricerca, tecnologia e innovazione.',
          'Ogni contenuto sponsorizzato è chiaramente identificato.',
          'Non accettiamo pubblicità pseudoscientifica o fuorviante.',
          'La linea editoriale rimane completamente indipendente dagli inserzionisti.',
        ] as $item)
          <div class="premium-info-row">
            <span>✓</span>
            <span>{{ $item }}</span>
          </div>
        @endforeach
      </div>
    </section>

    <section class="premium-static-section premium-cta-band">
      <h2>Vuoi collaborare con Quark?</h2>
      <p>
        Scrivici raccontandoci il tuo progetto, il tipo di partnership che hai in mente
        e gli obiettivi della collaborazione.
      </p>
      <a class="premium-button" href="{{ route('contatti') }}">Contattaci</a>
    </section>

  </div>
</div>
@endsection
