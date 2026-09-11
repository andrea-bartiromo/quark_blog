{{--
    Cantiere E (Prompt 122): struttura/dati base (nome, foto o iniziali,
    link al profilo) — solo token, nessuna bio/social ancora mostrati.

    Cantiere H (Prompt 186-187, Trust Layer pubblico): bio e social
    dell'autore sono dati reali già raccolti (User::bio/twitter/linkedin,
    stesso $article->author già eager-loaded — nessuna query nuova) ma
    mai renderizzati su questa vista prima d'ora. Aggiunti qui solo se
    valorizzati (mai un placeholder "scrive di scienza per Kairus" se il
    campo è vuoto — vedi PUBLIC_TRUST_LAYER_INVARIANTS.md). Twitter è
    salvato come handle ("@nome"), stessa trasformazione in URL già
    usata in autore.blade.php (ltrim del "@", mai duplicata come nuova
    convenzione); LinkedIn è già un URL completo. Nessun campo
    User::role qui: è un permesso interno, mai un titolo professionale
    pubblico.

    Prompt 283-286 (fix): la foto autore era servita con
    asset('storage/'.$photo), un path che presuppone il meccanismo di
    storage di Laravel (storage/app/public + symlink public/storage) mai
    usato in questo repository — il symlink non esiste qui. Entrambi i
    controller di upload attuali (Admin\ProfileController e
    Redazione\ProfileController::updatePhoto) salvano invece User::photo
    come disk_name "nudo" (nessuno slash) relativo a public/assets/img,
    la stessa convenzione già gestita da <x-responsive-image
    diskName="...">, usato con lo stesso dato in autore.blade.php.

    Fix applicato SOLO a questa forma nota-e-verificata (nessuno slash),
    quindi provabilmente scritta dal codice attuale: rende attraverso il
    componente responsive. Un valore con slash (path esplicito) non viene
    toccato e resta sul vecchio comportamento asset('storage/'...): come
    documentato in docs/MISSION_75_USER_PHOTO_PRODUCTION_PREFLIGHT.md,
    non è provato che ogni riga di produzione sia stata scritta dal
    codice attuale, e quella mission vieta esplicitamente di normalizzare
    ogni call site senza i fatti di produzione lì richiesti — qui ci si
    limita al caso già dimostrato rotto, senza rischiare di cambiare
    comportamento per un eventuale valore legacy ancora funzionante.
--}}
<div class="article-premium__panel kairus-author-card">
  <h3>Autore</h3>
  <div class="kairus-author-card__row">
    <div class="author-avatar kairus-author-card__avatar">
      @if($article->author->photo)
        @if(str_contains($article->author->photo, '/'))
          <img src="{{ asset('storage/'.$article->author->photo) }}" alt="{{ $article->author->name }}" class="kairus-author-card__photo" loading="lazy" decoding="async">
        @else
          <x-responsive-image
              :disk-name="$article->author->photo"
              :alt="$article->author->name"
              class="kairus-author-card__photo"
              loading="lazy" />
        @endif
      @else
        {{ mb_substr($article->author->name, 0, 2) }}
      @endif
    </div>
    <div class="kairus-author-card__info">
      <strong>{{ $article->author->name }}</strong><br>
      <a href="{{ route('autore', $article->author) }}" class="kairus-author-card__link kairus-focusable">Profilo autore</a>
    </div>
  </div>

  @if(filled($article->author->bio))
    <p class="kairus-author-card__bio">{{ $article->author->bio }}</p>
  @endif

  @if(filled($article->author->twitter) || filled($article->author->linkedin))
    <div class="kairus-author-card__social">
      @if(filled($article->author->twitter))
        <a href="https://twitter.com/{{ ltrim($article->author->twitter, '@') }}" target="_blank" rel="noopener noreferrer" class="kairus-author-card__link kairus-focusable">X (Twitter)</a>
      @endif
      @if(filled($article->author->linkedin))
        <a href="{{ $article->author->linkedin }}" target="_blank" rel="noopener noreferrer" class="kairus-author-card__link kairus-focusable">LinkedIn</a>
      @endif
    </div>
  @endif
</div>
