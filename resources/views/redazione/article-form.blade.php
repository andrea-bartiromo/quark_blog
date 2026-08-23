@extends('layouts.redazione')
@section('title', isset($article) ? 'Modifica articolo' : 'Nuovo articolo')

@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">{{ isset($article) ? 'Modifica articolo' : 'Nuovo articolo' }}</h1>
  <a href="{{ route('redazione.articles') }}" class="btn btn--outline">← I miei articoli</a>
</div>

{{-- Avviso revisione --}}
<div style="background:#f0fdfa;border:1px solid #99f6e4;border-radius:8px;
            padding:.75rem 1rem;margin-bottom:1.25rem;font-size:.82rem;color:#0f766e;">
  ℹ️ Quando salvi, l'articolo viene inviato automaticamente in <strong>revisione all'editor</strong>.
  Riceverai una email quando verrà approvato o se richiede modifiche.
</div>

@if($errors->any())
<div style="background:#fef0f0;border:1px solid #fcd0cc;border-radius:6px;
            padding:.75rem 1rem;margin-bottom:1rem;color:#991b1b;font-size:.85rem;">
  @foreach($errors->all() as $e) <p>{{ $e }}</p> @endforeach
</div>
@endif

@include('partials.article-autosave-banner')

<form method="POST"
      action="{{ isset($article) ? route('redazione.articles.update', $article) : route('redazione.articles.store') }}"
      enctype="multipart/form-data"
      class="article-form-grid"
      style="display:grid;gap:1.5rem;align-items:start;"
      data-editor-autosave-form
      data-editor-surface="redazione"
      data-editor-context="{{ $article->id ?? 'new' }}"
      data-editor-server-updated-at="{{ (isset($article) ? $article : null)?->updated_at?->timestamp }}">
  @csrf
  @if(isset($article)) @method('PUT') @endif

  {{-- Colonna principale --}}
  <div style="display:flex;flex-direction:column;gap:1rem;">
    <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1.5rem;">

      <div class="form-group">
        <label class="form-label">Titolo *</label>
        <input class="form-input" type="text" id="title" name="title"
               value="{{ old('title', $article->title ?? '') }}" required>
      </div>

      <div class="form-group">
        <label class="form-label">Sommario (max 300 caratteri)</label>
        <textarea class="form-textarea" id="excerpt" name="excerpt"
                  style="min-height:80px;" maxlength="300">{{ old('excerpt', $article->excerpt ?? '') }}</textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Testo articolo *</label>
        <textarea class="form-textarea" id="body" name="body"
                  style="min-height:400px;" required>{{ old('body', $article->body ?? '') }}</textarea>
        <small style="font-size:.72rem;color:#6b7280;">
          Usa la barra degli strumenti per formattare. Inserisci le fonti alla fine del testo dopo "---".
        </small>
      </div>
    </div>
  </div>

  {{-- Colonna opzioni --}}
  <div style="display:flex;flex-direction:column;gap:1rem;">

    <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1.25rem;">
      <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;
                  letter-spacing:.1em;margin-bottom:1rem;">Invia</div>
      <button type="submit" class="btn btn--primary btn--full">
        {{ isset($article) ? '📤 Aggiorna e invia in revisione' : '📤 Invia in revisione' }}
      </button>
      <p style="font-size:.7rem;color:#6b7280;margin-top:.5rem;text-align:center;">
        L'editor riceverà una notifica
      </p>
    </div>

    @include('partials.article-link-suggestions', ['article' => $article ?? null, 'linkSuggestions' => $linkSuggestions ?? collect(), 'linkSuggestionRoutePrefix' => 'redazione'])

    <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1.25rem;">
      <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;
                  letter-spacing:.1em;margin-bottom:1rem;">Categoria *</div>
      <select class="form-select" name="category" required>
        <option value="">Seleziona categoria…</option>
        @foreach($categories as $slug => $label)
          <option value="{{ $slug }}"
                  {{ old('category', $article->category ?? '') === $slug ? 'selected' : '' }}>
            {{ $label }}
          </option>
        @endforeach
      </select>
    </div>

    <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1.25rem;">
      <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;
                  letter-spacing:.1em;margin-bottom:1rem;">Immagine copertina</div>

      @if(!empty($article?->cover_image))
      <div style="margin-bottom:.75rem;">
        <img src="{{ asset('assets/img/'.$article->cover_image) }}"
             alt="Copertina attuale"
             style="width:100%;max-height:160px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb;"
             onerror="this.style.display='none'">
        <div style="font-size:.65rem;color:#6b7280;margin-top:.25rem;">{{ $article->cover_image }}</div>
      </div>
      @endif

      <input type="file" name="cover_image_upload"
             accept="image/jpeg,image/png,image/webp"
             style="font-size:.78rem;padding:.4rem;border:1px solid #e5e7eb;
                    border-radius:6px;background:#fff;width:100%;margin-bottom:.5rem;">
      <div style="font-size:.68rem;color:#6b7280;margin-bottom:.75rem;">Max 5 MB — JPEG, PNG, WebP</div>

      <div class="form-group">
        <label class="form-label" for="cover_alt">Testo alternativo</label>
        <input class="form-input" type="text" id="cover_alt" name="cover_alt"
               maxlength="255"
               value="{{ old('cover_alt', $article->cover_alt ?? '') }}"
               style="font-size:.82rem;">
        <small style="font-size:.68rem;color:#6b7280;">
          Descrizione dell'immagine per l'accessibilità. Se vuoto, viene usato il titolo dell'articolo.
        </small>
      </div>

      <div class="form-group">
        <label class="form-label" for="cover_caption">Didascalia</label>
        <textarea class="form-textarea" id="cover_caption" name="cover_caption"
                  maxlength="1000" style="min-height:60px;font-size:.82rem;">{{ old('cover_caption', $article->cover_caption ?? '') }}</textarea>
      </div>

      <div class="form-group">
        <label class="form-label" for="cover_credit">Credito immagine</label>
        <input class="form-input" type="text" id="cover_credit" name="cover_credit"
               maxlength="255"
               value="{{ old('cover_credit', $article->cover_credit ?? '') }}"
               style="font-size:.82rem;">
      </div>

      <div class="form-group">
        <label class="form-label" for="cover_source">Fonte</label>
        <input class="form-input" type="text" id="cover_source" name="cover_source"
               maxlength="255"
               value="{{ old('cover_source', $article->cover_source ?? '') }}"
               style="font-size:.82rem;">
      </div>

      <div class="form-group">
        <label class="form-label" for="cover_source_url">URL fonte</label>
        <input class="form-input" type="url" id="cover_source_url" name="cover_source_url"
               maxlength="2048"
               value="{{ old('cover_source_url', $article->cover_source_url ?? '') }}"
               style="font-size:.82rem;">
      </div>

      <div class="form-group">
        <label class="form-label" for="cover_license">Licenza</label>
        <input class="form-input" type="text" id="cover_license" name="cover_license"
               maxlength="255"
               value="{{ old('cover_license', $article->cover_license ?? '') }}"
               style="font-size:.82rem;">
      </div>
    </div>

    <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1.25rem;">
      <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;
                  letter-spacing:.1em;margin-bottom:1rem;">SEO</div>

      <div class="form-group">
        <label class="form-label" for="seo_title">
          SEO title
          <span class="js-char-counter" data-target="seo_title" data-recommended="60"></span>
        </label>
        <input class="form-input" type="text" id="seo_title" name="seo_title" maxlength="70"
               value="{{ old('seo_title', $article->seo_title ?? '') }}" style="font-size:.82rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin-top:.35rem;">
          <small class="form-hint" style="margin:0;">
            Se vuoto, viene usato il titolo dell'articolo (vedi anteprima nel campo). Consigliati fino a 60 caratteri.
          </small>
          <button type="button" class="action-btn" data-reset-for="seo_title" hidden>↺ Automatico</button>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="seo_description">
          SEO description
          <span class="js-char-counter" data-target="seo_description" data-recommended="160"></span>
        </label>
        <textarea class="form-textarea" id="seo_description" name="seo_description" maxlength="200"
                  style="min-height:70px;font-size:.82rem;">{{ old('seo_description', $article->seo_description ?? '') }}</textarea>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin-top:.35rem;">
          <small class="form-hint" style="margin:0;">
            Se vuoto, viene usato il sommario, altrimenti le prime righe del testo (vedi anteprima). Consigliati fino a 160 caratteri.
          </small>
          <button type="button" class="action-btn" data-reset-for="seo_description" hidden>↺ Automatico</button>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="canonical_url">URL canonico (opzionale)</label>
        <input class="form-input" type="url" id="canonical_url" name="canonical_url" maxlength="2048"
               value="{{ old('canonical_url', $article->canonical_url ?? '') }}"
               @if(isset($article) && $article->exists) placeholder="{{ route('articolo', $article->slug) }}" @endif
               style="font-size:.82rem;">
        <small class="form-hint">
          Da usare solo se questo contenuto è pubblicato anche altrove. Se vuoto, viene usato l'URL naturale dell'articolo.
        </small>
      </div>

      <div class="form-group">
        <label class="form-label" for="robots">Robots</label>
        @php $currentRobots = old('robots', $article->robots ?? ''); @endphp
        <select class="form-select" id="robots" name="robots" style="font-size:.82rem;">
          <option value="" {{ $currentRobots === '' ? 'selected' : '' }}>Predefinito (index, follow)</option>
          <option value="index,follow" {{ $currentRobots === 'index,follow' ? 'selected' : '' }}>index, follow</option>
          <option value="noindex,follow" {{ $currentRobots === 'noindex,follow' ? 'selected' : '' }}>noindex, follow</option>
          <option value="index,nofollow" {{ $currentRobots === 'index,nofollow' ? 'selected' : '' }}>index, nofollow</option>
          <option value="noindex,nofollow" {{ $currentRobots === 'noindex,nofollow' ? 'selected' : '' }}>noindex, nofollow</option>
        </select>
        <small class="form-hint">Usa "noindex" per escludere l'articolo dai motori di ricerca.</small>
      </div>

      <div style="border-top:1px solid #e5e7eb;margin:1rem 0 .85rem;padding-top:.85rem;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;">
        Open Graph (Facebook, LinkedIn)
      </div>

      <div class="form-group">
        <label class="form-label" for="og_title">OG title</label>
        <input class="form-input" type="text" id="og_title" name="og_title" maxlength="70"
               value="{{ old('og_title', $article->og_title ?? '') }}" style="font-size:.82rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin-top:.35rem;">
          <small class="form-hint" style="margin:0;">Se vuoto, viene usato il SEO title (o il titolo dell'articolo).</small>
          <button type="button" class="action-btn" data-reset-for="og_title" hidden>↺ Automatico</button>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="og_description">OG description</label>
        <textarea class="form-textarea" id="og_description" name="og_description" maxlength="200"
                  style="min-height:60px;font-size:.82rem;">{{ old('og_description', $article->og_description ?? '') }}</textarea>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin-top:.35rem;">
          <small class="form-hint" style="margin:0;">Se vuoto, viene usata la SEO description (con la stessa catena di fallback).</small>
          <button type="button" class="action-btn" data-reset-for="og_description" hidden>↺ Automatico</button>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="og_image">OG image</label>
        <input class="form-input" type="text" id="og_image" name="og_image" maxlength="255"
               placeholder="nome file dalla libreria media"
               value="{{ old('og_image', $article->og_image ?? '') }}" style="font-size:.82rem;">
        <small class="form-hint">Se vuoto, viene usata l'immagine di copertina.</small>
      </div>

      <div style="border-top:1px solid #e5e7eb;margin:1rem 0 .85rem;padding-top:.85rem;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;">
        Twitter Card
      </div>

      <div class="form-group">
        <label class="form-label" for="twitter_title">Twitter title</label>
        <input class="form-input" type="text" id="twitter_title" name="twitter_title" maxlength="70"
               value="{{ old('twitter_title', $article->twitter_title ?? '') }}" style="font-size:.82rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin-top:.35rem;">
          <small class="form-hint" style="margin:0;">Se vuoto, viene usato il SEO title (o il titolo dell'articolo).</small>
          <button type="button" class="action-btn" data-reset-for="twitter_title" hidden>↺ Automatico</button>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="twitter_description">Twitter description</label>
        <textarea class="form-textarea" id="twitter_description" name="twitter_description" maxlength="200"
                  style="min-height:60px;font-size:.82rem;">{{ old('twitter_description', $article->twitter_description ?? '') }}</textarea>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin-top:.35rem;">
          <small class="form-hint" style="margin:0;">Se vuoto, viene usata la SEO description (con la stessa catena di fallback).</small>
          <button type="button" class="action-btn" data-reset-for="twitter_description" hidden>↺ Automatico</button>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="twitter_image">Twitter image</label>
        <input class="form-input" type="text" id="twitter_image" name="twitter_image" maxlength="255"
               placeholder="nome file dalla libreria media"
               value="{{ old('twitter_image', $article->twitter_image ?? '') }}" style="font-size:.82rem;">
        <small class="form-hint">Se vuoto, viene usata l'immagine di copertina.</small>
      </div>
    </div>

    <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1.25rem;">
      <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;
                  letter-spacing:.1em;margin-bottom:.75rem;">Linee guida editoriali</div>
      @foreach([
        'Verifica ogni dato sulla fonte primaria',
        'Separa le fonti con --- alla fine del testo',
        'Usa titoli H2 e H3 per strutturare',
        'Aggiungi sempre un sommario chiaro',
        'Evita linguaggio sensazionalistico',
      ] as $guideline)
      <div style="display:flex;gap:.4rem;font-size:.72rem;color:#374151;margin-bottom:.3rem;">
        <span style="color:#0d9488;flex-shrink:0;">✓</span> {{ $guideline }}
      </div>
      @endforeach
    </div>

  </div>
</form>

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>
<script>
tinymce.init({
  selector: '#body',
  height: 500,
  menubar: false,
  language: 'it',
  promotion: false,
  branding: false,
  plugins: ['anchor','autolink','lists','link','searchreplace','wordcount','fullscreen','preview'],
  toolbar: 'undo redo | blocks | bold italic | bullist numlist | link | removeformat | fullscreen preview',
  block_formats: 'Paragrafo=p; Titolo 2=h2; Titolo 3=h3; Citazione=blockquote',
  content_style: `
    body { font-family:'Plus Jakarta Sans',system-ui,sans-serif; font-size:16px;
           line-height:1.8; color:#374151; max-width:720px; margin:1rem auto; }
    h2 { font-size:1.4rem; color:#111827; margin:1.5rem 0 .5rem; }
    h3 { font-size:1.1rem; color:#111827; margin:1rem 0 .4rem; }
    blockquote { border-left:3px solid #0d9488; margin:1rem 0;
                 padding:.75rem 1rem; background:#f0fdfa; font-style:italic; }
    a { color:#0d9488; text-decoration:underline; }
    a:hover { color:#0f766e; }
    a:focus-visible { outline:2px solid #0f766e; outline-offset:1px; }
  `,
  setup: function(editor) {
    editor.on('change input', function() {
      editor.save();
      if (typeof window.kairusRefreshSeoFallbackPreview === 'function') {
        window.kairusRefreshSeoFallbackPreview();
      }
      if (typeof window.kairusNotifyArticleFormChanged === 'function') {
        window.kairusNotifyArticleFormChanged();
      }
    });
  }
});
</script>

@include('partials.char-counter-script')
@include('partials.article-seo-fallback-script')
@include('partials.article-autosave-script')
@endsection