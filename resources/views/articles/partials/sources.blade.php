{{--
    EDITORIAL TRUST (Missione 28) — sezione fonti pubblica.

    Regole non negoziabili applicate qui:
      - Nessuna qualifica inventata: l'etichetta del tipo compare SOLO se la
        redazione l'ha dichiarata (typeLabelOrNull() è null per 'unknown').
      - Nessun link non validato: publicUrl() restituisce null quando non
        c'è un riferimento sicuro, e in quel caso la fonte resta testo.
      - Nessuna falsa precisione sulla consultazione: si stampa la sola data
        (giorno), mai un orario, e solo se la redazione l'ha registrata.
      - Zero fonti: la sezione non viene renderizzata affatto — nessun
        blocco vuoto "Fonti (0)".

    Convive con il blocco "Fonti" legacy ricavato dal body dopo il primo
    '---' (articles/partials/body.blade.php), che NON viene rimosso: vedi
    docs/EDITORIAL_SOURCES_V1.md §"Convivenza con il legacy".
--}}
@php
    $articleSources = $article->relationLoaded('sources')
        ? $article->sources
        : $article->sources()->get();
@endphp

@if($articleSources->isNotEmpty())
<section class="article-sources" id="fonti" aria-labelledby="article-sources-heading">
  <h2 id="article-sources-heading" class="article-sources__title">Fonti</h2>

  <p class="article-sources__intro">
    I riferimenti su cui si basa questo articolo. I collegamenti portano a siti esterni,
    non gestiti da {{ config('laboratorio.name') }}.
  </p>

  <ol class="article-sources__list">
    @foreach($articleSources as $source)
      @php
        $sourceUrl = $source->publicUrl();
        $sourceDoi = $source->normalizedDoi();
        $sourceHost = $source->displayHost();
        $sourceTypeLabel = $source->typeLabelOrNull();
      @endphp
      <li class="article-sources__item">
        <p class="article-sources__heading">
          @if($sourceUrl)
            {{-- rel="noopener noreferrer": stesso pattern già usato per
                 cover_source_url e SocialPublication::safeRemoteUrl(). --}}
            <a class="article-sources__link"
               href="{{ $sourceUrl }}"
               target="_blank"
               rel="noopener noreferrer">{{ $source->title }}<span class="sr-only"> (si apre in una nuova scheda)</span></a>
          @else
            <span class="article-sources__link article-sources__link--plain">{{ $source->title }}</span>
          @endif
        </p>

        <p class="article-sources__meta">
          @if($source->author_or_org)
            <span class="article-sources__who">{{ $source->author_or_org }}</span>
          @endif

          @if($sourceTypeLabel)
            <span class="article-sources__type">{{ $sourceTypeLabel }}</span>
          @endif

          @if($source->published_on)
            <span class="article-sources__date">
              Pubblicata il
              <time datetime="{{ $source->published_on->toDateString() }}">{{ $source->published_on->locale('it')->isoFormat('D MMMM YYYY') }}</time>
            </span>
          @endif

          @if($sourceDoi)
            <span class="article-sources__ref">DOI {{ $sourceDoi }}</span>
          @elseif($sourceHost)
            <span class="article-sources__ref">{{ $sourceHost }}</span>
          @endif
        </p>

        @if($source->editorial_note)
          <p class="article-sources__note">{{ $source->editorial_note }}</p>
        @endif

        @if($source->accessed_on)
          {{-- "Consultata il <data>" e non "verificata": Kairus dichiara di
               aver aperto la fonte quel giorno, non che il suo contenuto sia
               stato certificato — e non che sia ancora identica oggi. --}}
          <p class="article-sources__accessed">
            Consultata il
            <time datetime="{{ $source->accessed_on->toDateString() }}">{{ $source->accessed_on->locale('it')->isoFormat('D MMMM YYYY') }}</time>
          </p>
        @endif
      </li>
    @endforeach
  </ol>
</section>
@endif
