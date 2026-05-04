@extends('layouts.app')

@section('title', 'Chi siamo — '.config('laboratorio.name'))
@section('description', 'La storia, la missione e i valori de Il Laboratorio, giornale online di divulgazione scientifica e tecnologica.')

@section('content')
<div class="container" style="padding-block:2.5rem;max-width:780px;">

  <hr style="border:none;border-top:3px solid var(--color-ink);margin:0 0 .5rem;">
  <h1 style="font-family:var(--font-display);font-size:clamp(1.8rem,4vw,2.6rem);font-weight:900;margin-bottom:2rem;">
    Chi siamo
  </h1>

  <div style="font-family:var(--font-body);font-size:1.05rem;line-height:1.85;color:var(--color-ink-soft);">

    <p style="margin-bottom:1.4em;">
      <strong style="color:var(--color-ink);">Il Laboratorio</strong> è un giornale online di divulgazione
      scientifica e tecnologica nato con una missione precisa: raccontare come la scienza e
      l'innovazione cambiano concretamente la vita degli italiani, ogni giorno.
    </p>

    <p style="margin-bottom:1.4em;">
      Non siamo un aggregatore di notizie. Ogni articolo è scritto da giornalisti con formazione
      scientifica, verificato sulle fonti primarie — paper accademici, comunicati istituzionali,
      interviste dirette ai ricercatori — e tradotto in un linguaggio accessibile senza perdere
      la precisione che la scienza richiede.
    </p>

    <h2 style="font-family:var(--font-display);font-size:1.4rem;font-weight:700;color:var(--color-ink);
               margin:2em 0 .6em;border-top:2px solid var(--color-border);padding-top:1em;">
      La nostra missione
    </h2>

    <p style="margin-bottom:1.4em;">
      Crediamo che la divulgazione scientifica di qualità non sia un lusso per pochi appassionati,
      ma una necessità civile. Un cittadino informato su AI, cambiamenti climatici, biotecnologie
      e ricerca medica prende decisioni migliori — come elettore, come consumatore, come persona.
    </p>

    <p style="margin-bottom:1.4em;">
      Ci concentriamo sull'Italia: le università, i laboratori, le startup, i ricercatori che ogni
      giorno producono innovazione spesso invisibile al grande pubblico. Vogliamo renderla visibile.
    </p>

    <h2 style="font-family:var(--font-display);font-size:1.4rem;font-weight:700;color:var(--color-ink);
               margin:2em 0 .6em;border-top:2px solid var(--color-border);padding-top:1em;">
      I nostri principi editoriali
    </h2>

    <ul style="padding-left:1.5em;list-style:disc;margin-bottom:1.4em;">
      <li style="margin-bottom:.6em;"><strong style="color:var(--color-ink);">Accuratezza prima di tutto.</strong> Ogni informazione è verificata sulla fonte primaria. Se non siamo certi, non scriviamo.</li>
      <li style="margin-bottom:.6em;"><strong style="color:var(--color-ink);">Trasparenza sulle fonti.</strong> Citiamo sempre i paper, le istituzioni e le persone da cui provengono le informazioni.</li>
      <li style="margin-bottom:.6em;"><strong style="color:var(--color-ink);">Indipendenza editoriale.</strong> Non accettiamo contenuti sponsorizzati mascherati da articoli. La pubblicità è chiaramente segnalata.</li>
      <li style="margin-bottom:.6em;"><strong style="color:var(--color-ink);">Rettifiche immediate.</strong> Se sbagliamo, correggiamo pubblicamente e tempestivamente.</li>
    </ul>


    <h2 style="font-family:var(--font-display);font-size:1.4rem;font-weight:700;color:var(--color-ink);
               margin:2em 0 .6em;border-top:2px solid var(--color-border);padding-top:1em;">
      Politica editoriale e standard di verifica
    </h2>

    <p style="margin-bottom:1.4em;">
      Ogni notizia pubblicata su <strong style="color:var(--color-ink);">Il Laboratorio</strong> 
      rispetta un protocollo di verifica in tre passaggi:
    </p>

    <ol style="padding-left:1.5em;list-style:decimal;margin-bottom:1.4em;">
      <li style="margin-bottom:.6em;">
        <strong style="color:var(--color-ink);">Fonte primaria identificata.</strong> 
        Ogni dato numerico o fatto rilevante rimanda a una fonte primaria verificabile: 
        comunicati istituzionali (ANSA, Terna, ASI, AIRC, ESA, GSE), 
        testi di legge pubblicati in Gazzetta Ufficiale, 
        studi scientifici pubblicati su riviste peer-reviewed 
        (New England Journal of Medicine, Science, Nature, ecc.).
      </li>
      <li style="margin-bottom:.6em;">
        <strong style="color:var(--color-ink);">Nessun dato senza fonte.</strong> 
        Se non siamo in grado di identificare e verificare la fonte di un'informazione, 
        quella informazione non viene pubblicata. 
        Utilizziamo il condizionale per fatti non ancora confermati 
        o per proiezioni future.
      </li>
      <li style="margin-bottom:.6em;">
        <strong style="color:var(--color-ink);">Trasparenza sulle fonti.</strong> 
        Ogni articolo riporta in fondo la lista delle fonti utilizzate. 
        Il lettore può verificare autonomamente ogni informazione.
      </li>
    </ol>

    <div style="background:var(--color-paper-warm);border-left:4px solid var(--color-accent);
                padding:1.25rem;border-radius:0 var(--radius) var(--radius) 0;margin-bottom:1.5em;">
      <strong style="color:var(--color-ink);display:block;margin-bottom:.35rem;">
        Segnala un errore
      </strong>
      <p style="font-size:.9rem;color:var(--color-ink-soft);margin:0;line-height:1.6;">
        Se riscontri un'imprecisione o un dato non corretto in uno dei nostri articoli, 
        scrivici a 
        <a href="mailto:rettifiche@illaboratorio.it" style="color:var(--color-accent);">rettifiche@illaboratorio.it</a>. 
        Esaminiamo ogni segnalazione e, se fondata, pubblichiamo la correzione 
        entro 24 ore lavorative con una nota nell'articolo originale. 
        Consulta la nostra <a href="{{ route('rettifiche') }}" style="color:var(--color-accent);">politica sulle rettifiche</a>.
      </p>
    </div>

    <h2 style="font-family:var(--font-display);font-size:1.4rem;font-weight:700;color:var(--color-ink);
               margin:2em 0 .6em;border-top:2px solid var(--color-border);padding-top:1em;">
      Informazioni legali
    </h2>

    <p style="margin-bottom:.75em;">
      <strong style="color:var(--color-ink);">Testata:</strong> Il Laboratorio<br>
      <strong style="color:var(--color-ink);">Direttore responsabile:</strong> [Nome Cognome]<br>
      <strong style="color:var(--color-ink);">Registrazione:</strong> Tribunale di [Città], n. [Numero] del [Data]<br>
      <strong style="color:var(--color-ink);">Sede legale:</strong> [Indirizzo], [CAP] [Città]<br>
      <strong style="color:var(--color-ink);">P.IVA:</strong> IT00000000000<br>
      <strong style="color:var(--color-ink);">Email:</strong>
      <a href="mailto:redazione@illaboratorio.it" style="color:var(--color-accent);">redazione@illaboratorio.it</a>
    </p>

  </div>


    {{-- ═══ FONDATORE ═══ --}}
    <h2 id="fondatore"
        style="font-family:var(--font-display);font-size:1.4rem;font-weight:700;
               color:var(--color-ink);margin:2em 0 .6em;
               border-top:2px solid var(--color-border);padding-top:1em;">
      Chi siamo
    </h2>

    <div style="display:flex;gap:1.5rem;align-items:flex-start;
                flex-wrap:wrap;margin-bottom:1.5em;">

      {{-- Iniziale come avatar --}}
      <div style="flex-shrink:0;width:72px;height:72px;border-radius:50%;
                  background:var(--color-ink);display:flex;align-items:center;
                  justify-content:center;font-family:var(--font-display);
                  font-size:1.8rem;font-weight:900;color:var(--color-white);">
        AB
      </div>

      <div style="flex:1;min-width:220px;">
        <div style="font-family:var(--font-ui);font-size:.7rem;font-weight:700;
                    text-transform:uppercase;letter-spacing:.1em;
                    color:var(--color-accent);margin-bottom:.2rem;">
          Fondatore e Direttore Responsabile
        </div>
        <div style="font-family:var(--font-display);font-size:1.25rem;font-weight:900;
                    color:var(--color-ink);margin-bottom:.5rem;">
          Andrea Bartiromo
        </div>
        <p style="font-family:var(--font-body);font-size:.92rem;color:var(--color-ink-soft);
                  line-height:1.7;margin:0;">
          <em>Il Laboratorio</em> è un progetto editoriale nato dalla convinzione
          che la scienza e la tecnologia meritino una narrazione rigorosa, accessibile
          e radicata nella realtà italiana. Ogni articolo pubblicato risponde a un
          principio semplice: ogni informazione deve essere verificabile sulla sua
          fonte primaria.
        </p>
        <div style="margin-top:.75rem;">
          <a href="mailto:redazione@illaboratorio.it"
             style="font-family:var(--font-ui);font-size:.78rem;font-weight:600;
                    color:var(--color-accent);text-decoration:none;">
            redazione@illaboratorio.it
          </a>
        </div>
      </div>
    </div>

    {{-- ═══ SEZIONE FIRMA PROGETTO ═══ --}}
    <section id="progetto"
             style="margin-top:2.5em;padding:2em;
                    background:var(--color-ink);color:var(--color-white);
                    border-radius:var(--radius);position:relative;overflow:hidden;">

      {{-- Decorazione di sfondo --}}
      <div style="position:absolute;top:-30px;right:-30px;width:180px;height:180px;
                  border-radius:50%;background:rgba(201,24,74,.15);pointer-events:none;"></div>
      <div style="position:absolute;bottom:-40px;left:-20px;width:140px;height:140px;
                  border-radius:50%;background:rgba(201,24,74,.08);pointer-events:none;"></div>

      <div style="position:relative;z-index:1;">
        <div style="font-family:var(--font-ui);font-size:.68rem;font-weight:700;
                    text-transform:uppercase;letter-spacing:.12em;
                    color:var(--color-accent);margin-bottom:.6rem;">
          Il Progetto
        </div>

        <h2 style="font-family:var(--font-display);font-size:1.5rem;font-weight:900;
                   color:var(--color-white);margin:0 0 1rem;line-height:1.2;">
          Il Laboratorio — nato da un'idea, costruito con cura
        </h2>

        <p style="font-family:var(--font-body);font-size:.95rem;color:rgba(255,255,255,.75);
                  line-height:1.75;margin:0 0 1rem;">
          <em>Il Laboratorio</em> nasce dalla convinzione che la divulgazione scientifica in Italia
          meriti una voce editoriale forte, rigorosa e accessibile. Un luogo dove la ricerca,
          l'innovazione e la tecnologia vengono raccontate senza scorciatoie, con fonti verificate
          e un linguaggio che rispetta l'intelligenza del lettore.
        </p>

        <p style="font-family:var(--font-body);font-size:.9rem;color:rgba(255,255,255,.6);
                  line-height:1.7;margin:0 0 1.5rem;">
          Il sito è stato progettato e sviluppato interamente in Italia su
          <strong style="color:rgba(255,255,255,.85);">Laravel {{ app()->version() }}</strong>
          con PHP {{ PHP_MAJOR_VERSION }}.{{ PHP_MINOR_VERSION }},
          database SQLite per la semplicità di deployment, e un sistema editoriale
          costruito attorno al principio che ogni informazione pubblicata deve
          essere verificabile sulla sua fonte primaria.
        </p>

        {{-- Stack tecnico --}}
        <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1.5rem;">
          @foreach(['Laravel 13','PHP 8.3','SQLite','Blade','RSS feeds','Schema.org','Claude AI'] as $tech)
          <span style="font-family:var(--font-ui);font-size:.68rem;font-weight:700;
                       background:rgba(255,255,255,.08);color:rgba(255,255,255,.7);
                       padding:.25rem .65rem;border-radius:20px;border:1px solid rgba(255,255,255,.12);">
            {{ $tech }}
          </span>
          @endforeach
        </div>

        {{-- Firma --}}
        <div style="border-top:1px solid rgba(255,255,255,.12);padding-top:1.25rem;
                    display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
          <div style="flex:1;min-width:200px;">
            <div style="font-family:var(--font-display);font-size:1.1rem;font-weight:700;
                        color:var(--color-white);margin-bottom:.15rem;">
              {{ config('laboratorio.name') }}
            </div>
            <div style="font-family:var(--font-ui);font-size:.75rem;color:rgba(255,255,255,.45);">
              Fondato nel {{ date('Y') }} &nbsp;·&nbsp; Italia
            </div>
          </div>
          <div style="display:flex;gap:.75rem;">
            <a href="{{ route('feed') }}"
               style="font-family:var(--font-ui);font-size:.72rem;font-weight:600;
                      color:var(--color-accent);text-decoration:none;
                      border:1px solid rgba(201,24,74,.4);padding:.3rem .75rem;
                      border-radius:20px;">
              RSS Feed
            </a>
            <a href="{{ route('sitemap') }}"
               style="font-family:var(--font-ui);font-size:.72rem;font-weight:600;
                      color:rgba(255,255,255,.5);text-decoration:none;
                      border:1px solid rgba(255,255,255,.15);padding:.3rem .75rem;
                      border-radius:20px;">
              Sitemap
            </a>
          </div>
        </div>
      </div>
    </section>

</div>
@endsection
