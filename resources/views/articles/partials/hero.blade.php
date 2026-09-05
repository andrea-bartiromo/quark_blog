{{--
    Cantiere E — Article Visual Adoption (Prompt 108-113). L'hero resta la
    sua struttura legacy (immagine di sfondo assoluta + overlay, testo
    sovrapposto): x-kairus.page-header è una superficie piatta a colore
    pieno, senza slot per un'immagine di sfondo — adottarla qui vorrebbe
    dire eliminare la cover, mai chiesto. x-kairus.image-frame inquadra
    un'immagine in un rapporto fisso con didascalia SOTTO, pensato per
    contenuto in flusso (Percorsi, corpo articolo) — non per testo
    sovrapposto a un'immagine a piena larghezza. Nessuno dei due
    componenti si adatta senza perdere contenuto o capovolgere il layout:
    qui solo rifinitura via token (.kairus-tone-navy per il contesto
    scuro, riuso dello stesso sistema di toni di page-header/section-heading;
    .kairus-article-hero__title per il bilanciamento del titolo).

    x-kairus.article-meta copre autore/data/tempo di lettura ma non ha uno
    slot per le visualizzazioni (dato reale, da preservare integralmente):
    adottato per i tre campi che copre, con le visualizzazioni aggiunte
    come elemento fratello nello stesso stile.

    Trust Layer — riconciliazione Kairus (#517). "Aggiornato il" usa il
    prop updatedAt del componente, già previsto da Missione 06 ma mai
    invocato da nessuna vista prima d'ora: $lastEditorialUpdate è null a
    meno che ArticleRevisionTransparencyService trovi una revisione
    post-pubblicazione con contenuto realmente diverso — il componente
    omette l'intero <li> quando il valore è null, mai una data
    indovinata. Mai il vecchio container .article-premium__meta.
--}}
<header class="article-premium__hero kairus-tone-navy">
  <x-responsive-image
      :diskName="$article->cover_image ?: null"
      :src="$cover"
      :alt="$article->cover_alt ?: $article->title"
      loading="eager"
      fetchpriority="high"
      :sizes="'(max-width: 900px) 100vw, 1240px'"
  />
  <div class="article-premium__overlay"></div>

  <x-media.image-viewer
      :src="$cover"
      :alt="$article->cover_alt ?: $article->title"
      :title="$article->title"
      :caption="$article->cover_caption"
      :credit="$article->cover_credit"
      :source="$article->cover_source"
      :sourceUrl="$article->cover_source_url"
      :license="$article->cover_license"
  />

  <div class="article-premium__content">
    <a href="{{ route('categoria', $article->category) }}" class="public-hero__kicker kairus-focusable" style="text-decoration:none;">
      {{ $categoryLabel }}
    </a>

    <h1 class="kairus-article-hero__title">{{ $article->title }}</h1>

    @if($article->excerpt)
    <p class="article-premium__excerpt">
      {{ $article->excerpt }}
    </p>
    @endif

    <div class="kairus-article-hero__meta">
      <x-kairus.article-meta
          :author="$article->author->name"
          :published-at="$article->published_at"
          :updated-at="$lastEditorialUpdate"
          :read-minutes="$article->read_minutes"
      />
      <span class="kairus-article-meta__item kairus-article-hero__views">{{ number_format($article->views, 0, ',', '.') }} visualizzazioni</span>
    </div>
  </div>
</header>
