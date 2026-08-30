<div class="article-premium__panel">
  <h3>Autore</h3>
  <div style="display:flex;gap:.8rem;align-items:center;">
    <div class="author-avatar">
      @if($article->author->photo)
        {{--
            Missione 23 — audit eseguito, NESSUNA modifica applicata qui
            di proposito: questo src usa la radice storage/, mentre i
            controller di upload odierni scrivono sotto assets/img/. Una
            mission precedente (docs/MISSION_75_USER_PHOTO_PRODUCTION_PREFLIGHT.md,
            docs/MISSION_77_RESPONSIVE_IMAGES_INVENTORY_V2.md, PR #249) ha
            verificato che la radice corretta per QUESTO call site non è
            accertabile senza interrogare il database di produzione — foto
            storiche potrebbero davvero vivere sotto storage/app/public — e
            ha esplicitamente vietato di "normalizzare alla cieca" senza
            quel dato. Questo agente non ha accesso a dati di produzione
            (vietato dalle regole operative), quindi lascia il markup
            invariato: cambiarlo ora rischierebbe di rompere silenziosamente
            foto autore reali già funzionanti, esattamente il rischio che
            quella mission ha già documentato ed evitato.
        --}}
        <img src="{{ asset('storage/'.$article->author->photo) }}" alt="{{ $article->author->name }}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" loading="lazy" decoding="async">
      @else
        {{ mb_substr($article->author->name, 0, 2) }}
      @endif
    </div>
    <div>
      <strong>{{ $article->author->name }}</strong><br>
      <a href="{{ route('autore', $article->author) }}" style="font-size:.85rem;color:#0f766e;text-decoration:none;font-weight:800;">Profilo autore</a>
    </div>
  </div>

  @if($article->hasRecordedEditorialReview())
    {{--
        Missione 25 — distinzione fra autore e revisore editoriale,
        mostrata solo quando esiste davvero una verifica registrata
        (mai un'etichetta "non verificato" pubblica — vedi
        Article::hasRecordedEditorialReview()). Nessuna qualifica di
        "revisore scientifico": Kairus non ha un sistema di
        accreditamento scientifico dei revisori, quindi quella
        distinzione non viene dichiarata da nessuna parte.
    --}}
    <p style="margin:.75rem 0 0;padding-top:.75rem;border-top:1px solid var(--border);font-size:.8rem;color:var(--ink-muted);line-height:1.5;">
      @if($article->hasDistinctEditorialReviewer())
        Verifica editoriale a cura di <strong style="color:var(--ink);">{{ $article->verified_by }}</strong>
      @else
        Verifica editoriale confermata
      @endif
      @if($article->verified_at)
        <span aria-hidden="true"> · </span>
        <time datetime="{{ $article->verified_at->toDateString() }}">{{ $article->verified_at->locale('it')->isoFormat('D MMMM YYYY') }}</time>
      @endif
    </p>
  @endif
</div>
