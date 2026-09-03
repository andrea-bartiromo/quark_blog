@if($featured)
<section class="home-premium-hero">
  <div class="container container--wide kairus-page-shell">
    <div class="home-premium-hero__grid kairus-hero-grid">
      <article class="home-lead-story kairus-hero-story">
        {{--
            Cantiere D (Prompt 18): il testo precede l'immagine nel DOM
            (titolo raggiunto per primo da tastiera/screen reader/lettura
            lineare), mentre .kairus-hero-story__media riceve un "order"
            dedicato cosi' la resa visiva a due colonne (immagine a
            sinistra) definita da .home-lead-story (CSS Grid legacy,
            invariato) resta identica a prima — nessun cambiamento visivo,
            solo di ordine nel markup.
        --}}
        <div class="home-lead-story__content kairus-hero-story__content">
          <span class="magazine-kicker kairus-eyebrow">{{ $categoryLabel($featured) }}</span>
          <h1 class="kairus-hero-story__title"><a href="{{ route('articolo', $featured->slug) }}" class="kairus-focusable">{!! nl2br(e($featured->title)) !!}</a></h1>
          <p class="kairus-hero-story__lead">{{ Str::limit($featured->excerpt, 230) }}</p>
          <x-kairus.article-meta
            :author="$featured->author->name"
            :published-at="$featured->published_at"
            :read-minutes="$featured->read_minutes"
          />
        </div>

        {{--
            Cantiere D (Prompt 16): x-kairus.image-frame incornicia la
            stessa <x-responsive-image> di prima — width/height/srcset/
            loading/fetchpriority/sizes invariati (Prompt 17) — non
            article-card, il cui titolo è sempre un h3: qui il titolo
            resta l'unico h1 della home (Prompt 21: un solo link, l'altro
            link gemello sull'immagine è preesistente, mai annidato).
        --}}
        <a href="{{ route('articolo', $featured->slug) }}" class="home-lead-story__media kairus-hero-story__media kairus-focusable">
          <x-kairus.image-frame ratio="hero" class="kairus-hero-story__frame">
            <x-responsive-image
              :diskName="$featured->cover_image ?: null"
              :src="$visualFor($featured)"
              :onerrorSrc="$visualFor($featured)"
              :alt="$featured->title"
              :sizes="'(max-width: 900px) 100vw, 62vw'"
              class="home-lead-story__hero-image"
              loading="eager"
              fetchpriority="high"
            />
          </x-kairus.image-frame>

          <span>In evidenza</span>
        </a>
      </article>

      {{--
          Cantiere D (Prompt 23): l'etichetta riflette il dato reale — se non
          esiste alcuna visualizzazione misurata nelle ultime 24h,
          $fallbackTrending (già calcolato in home.blade.php, invariato) è
          in realtà solo "gli ultimi pubblicati": l'etichetta lo dice, invece
          di dichiarare un "trending" mai misurato. Nessuna logica o
          tracking cambiati, solo la label visibile.
      --}}
      <aside class="home-trending-panel kairus-trending-panel">
        <div class="home-trending-panel__head kairus-trending-panel__head">
          <span>{{ $trending->isNotEmpty() ? 'Più lette nelle ultime 24 ore' : 'Appena pubblicati' }}</span>
          @if($trending->isNotEmpty())<small>24h</small>@endif
        </div>

        @foreach($fallbackTrending->take(5) as $item)
          <a href="{{ route('articolo', $item->slug) }}" class="home-trending-item kairus-focusable">
            <span>{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
            <div>
              <small>{{ $categoryLabel($item) }}</small>
              <strong>{{ Str::limit($item->title, 84) }}</strong>
              <em>{{ $item->read_minutes }} min di lettura</em>
            </div>
          </a>
        @endforeach
      </aside>
    </div>
  </div>
</section>
@endif
