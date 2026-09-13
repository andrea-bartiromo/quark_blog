{{--
    Profilo di ricerca editoriale (Cantiere 2, programma "Kairus Organic
    Discovery") — facoltativo, mai letto da alcuna pagina pubblica: serve
    solo alla redazione per esplicitare l'intento di ricerca reale di un
    articolo. Form separato dal form principale dell'articolo (azione
    dedicata verso admin.articles.search-profile.update, stesso pattern
    già in uso per il collegamento concetti più sopra): il profilo è 1:1
    con l'articolo e richiede article_id, quindi può esistere solo su un
    articolo già salvato.

    Richiede $article, $searchProfile (?App\Models\ArticleSearchProfile,
    calcolato server-side da ArticleController::edit()) e
    $searchProfileCollisions (Collection<Article>, sola lettura).
--}}
<div style="background:var(--color-white, #fff);border-radius:var(--radius, 8px);box-shadow:var(--shadow, 0 1px 3px rgba(0,0,0,.08));padding:1.25rem;">
  <div style="font-family:var(--font-ui, inherit);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.75rem;">
    Profilo di ricerca editoriale
  </div>

  <p class="form-hint" style="margin:0 0 .85rem;font-size:.75rem;">
    Come un lettore reale troverebbe questo articolo cercando su Google —
    mai visibile pubblicamente, solo per orientare titolo, struttura e
    collegamenti interni.
  </p>

  @if($searchProfileCollisions->isNotEmpty())
    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:.6rem .85rem;margin-bottom:.85rem;font-size:.78rem;color:#92400e;">
      ⚠ Stessa query primaria anche in:
      @foreach($searchProfileCollisions as $index => $other)
        <a href="{{ route('admin.articles.edit', $other) }}" style="color:inherit;text-decoration:underline;">{{ Str::limit($other->title, 60) }}</a>{{ $index < $searchProfileCollisions->count() - 1 ? ', ' : '' }}
      @endforeach
      — solo un avviso, non blocca il salvataggio.
    </div>
  @endif

  <div id="sp-suggestions-wrapper" hidden style="background:#f0fdfa;border:1px solid #99f6e4;border-radius:6px;padding:.6rem .75rem;margin-bottom:.85rem;">
    <p style="margin:0 0 .4rem;font-size:.72rem;font-weight:600;color:#0f766e;">
      Bozze ricavate da titolo/estratto/heading — mai salvate finché non clicchi "Usa"
    </p>
    <div id="sp-suggestions"></div>
  </div>

  <form method="POST" action="{{ route('admin.articles.search-profile.update', $article) }}">
    @csrf
    @method('PUT')

    <div class="form-group">
      <label class="form-label" for="sp_primary_intent">Intento primario</label>
      <input class="form-input" type="text" id="sp_primary_intent" name="primary_intent" maxlength="500"
             value="{{ old('primary_intent', $searchProfile?->primary_intent) }}"
             placeholder="Es. capire cosa sono le api e come vivono">
    </div>

    <div class="form-group">
      <label class="form-label" for="sp_primary_query">Query primaria</label>
      <input class="form-input" type="text" id="sp_primary_query" name="primary_query" maxlength="255"
             value="{{ old('primary_query', $searchProfile?->primary_query) }}"
             placeholder="Es. ape insetto">
    </div>

    <div class="form-group">
      <label class="form-label" for="sp_secondary_queries">Query secondarie (una per riga, max 10)</label>
      <textarea class="form-textarea" id="sp_secondary_queries" name="secondary_queries" rows="4"
                placeholder="differenza tra ape e vespa&#10;quanto vive un'ape">{{ old('secondary_queries', is_array($searchProfile?->secondary_queries) ? implode("\n", $searchProfile->secondary_queries) : '') }}</textarea>
    </div>

    <div class="form-group">
      <label class="form-label" for="sp_reader_questions">Domande dei lettori (una per riga, max 10)</label>
      <textarea class="form-textarea" id="sp_reader_questions" name="reader_questions" rows="4"
                placeholder="Le api pungono sempre?&#10;Perché le api sono importanti?">{{ old('reader_questions', is_array($searchProfile?->reader_questions) ? implode("\n", $searchProfile->reader_questions) : '') }}</textarea>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
      <div class="form-group">
        <label class="form-label" for="sp_content_type">Tipo contenuto</label>
        <select class="form-select" id="sp_content_type" name="content_type">
          <option value="">—</option>
          @foreach(\App\Models\ArticleSearchProfile::contentTypeOptions() as $value => $label)
            <option value="{{ $value }}" @selected(old('content_type', $searchProfile?->content_type) === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="form-group">
        <label class="form-label" for="sp_reader_level">Livello del lettore</label>
        <select class="form-select" id="sp_reader_level" name="reader_level">
          <option value="">—</option>
          @foreach(\App\Models\ArticleSearchProfile::readerLevelOptions() as $value => $label)
            <option value="{{ $value }}" @selected(old('reader_level', $searchProfile?->reader_level) === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="sp_last_editorial_review_at">Ultima revisione editoriale</label>
      <input class="form-input" type="date" id="sp_last_editorial_review_at" name="last_editorial_review_at"
             value="{{ old('last_editorial_review_at', $searchProfile?->last_editorial_review_at?->toDateString()) }}">
    </div>

    <div class="form-group">
      <label class="form-label" for="sp_freshness_note">Nota di freschezza</label>
      <textarea class="form-textarea" id="sp_freshness_note" name="freshness_note" rows="2" maxlength="1000"
                placeholder="Perché questo contenuto è ancora aggiornato, o cosa andrebbe rivisto">{{ old('freshness_note', $searchProfile?->freshness_note) }}</textarea>
    </div>

    <div class="form-group">
      <label class="form-label" for="sp_evidence_scope">Ambito e limiti delle evidenze</label>
      <textarea class="form-textarea" id="sp_evidence_scope" name="evidence_scope" rows="2" maxlength="1000"
                placeholder="Cosa copre questo articolo e cosa resta fuori">{{ old('evidence_scope', $searchProfile?->evidence_scope) }}</textarea>
    </div>

    <button type="submit" class="btn btn--secondary btn--full">Salva profilo di ricerca</button>
  </form>
</div>
