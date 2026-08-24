@extends('layouts.admin')
@section('title', $article ? 'Modifica articolo' : 'Nuovo articolo')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">{{ $article ? 'Modifica articolo' : 'Nuovo articolo' }}</h1>
  <a href="{{ route('admin.articles') }}" class="btn btn--outline">← Torna alla lista</a>
</div>

@if($errors->any())
<div style="background:#fef0f0;border:1px solid #fcd0cc;border-radius:6px;padding:.75rem 1rem;margin-bottom:1rem;font-family:var(--font-ui);font-size:.85rem;color:var(--color-accent);">
  @foreach($errors->all() as $e) <p>{{ $e }}</p> @endforeach
</div>
@endif

<form method="POST"
      action="{{ $article ? route('admin.articles.update',$article) : route('admin.articles.store') }}"
      enctype="multipart/form-data"
      class="article-form-grid"
      style="display:grid;gap:1.5rem;align-items:start;">
  @csrf
  @if($article) @method('PUT') @endif

  <div style="display:flex;flex-direction:column;gap:1rem;">
    <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.5rem;">

      <div class="form-group">
        <label class="form-label" for="title">Titolo *</label>
        <input class="form-input" type="text" id="title" name="title"
               value="{{ old('title', $article->title ?? '') }}" required>
      </div>

      <div class="form-group">
        <label class="form-label" for="excerpt">Sommario (max 300 caratteri)</label>
        <textarea class="form-textarea" id="excerpt" name="excerpt"
                  style="min-height:80px;" maxlength="300">{{ old('excerpt', $article->excerpt ?? '') }}</textarea>
      </div>

      <div class="form-group">
        <label class="form-label" for="body">Testo articolo *</label>
        <textarea class="form-textarea" id="body" name="body"
                  style="min-height:400px;" required>{{ old('body', $article->body ?? '') }}</textarea>
        <small style="font-size:.72rem;color:#6b7280;">
          Usa la barra degli strumenti per formattare il testo.
        </small>
      </div>

    </div>
  </div>

  <div style="display:flex;flex-direction:column;gap:1rem;">

    <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.25rem;">
      <div style="font-family:var(--font-ui);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:1rem;">Pubblica</div>

      @if($article && $article->isScheduled() && $article->published_at)
      <div class="schedule-note" id="schedule-note">
        <svg class="schedule-note__icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
          <circle cx="10" cy="10" r="7.25" stroke="currentColor" stroke-width="1.5"/>
          <path d="M10 6v4l2.5 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="schedule-note__text">
          Pubblicazione programmata per <strong>{{ $article->publishedAtForEditors()->translatedFormat('d F Y') }} alle {{ $article->publishedAtForEditors()->format('H:i') }}</strong>
        </span>
      </div>
      @endif

      @php $currentStatus = old('status', $article->status ?? ''); @endphp
      <div class="form-group">
        <label class="form-label" for="status">Stato</label>
        <select class="form-select" id="status" name="status">
          @foreach(\App\Models\Article::statusOptions() as $value => $label)
            <option value="{{ $value }}" {{ $currentStatus === $value ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="form-group" id="schedule-fields" @if($currentStatus !== 'scheduled') hidden @endif>
        <label class="form-label" for="published_date">Data pubblicazione</label>
        <input class="form-input" type="date" id="published_date" name="published_date"
               value="{{ old('published_date', optional($article?->publishedAtForEditors())->format('Y-m-d')) }}">

        <label class="form-label" for="published_time" style="margin-top:.5rem;">Ora pubblicazione (Europe/Rome)</label>
        <input class="form-input" type="time" id="published_time" name="published_time"
               value="{{ old('published_time', optional($article?->publishedAtForEditors())->format('H:i')) }}">
      </div>

      <div class="form-group">
        <label class="form-checkbox">
          <input type="checkbox" name="featured" value="1"
                 {{ old('featured', $article->featured ?? false) ? 'checked' : '' }}>
          Articolo in evidenza (hero homepage)
        </label>
      </div>

      <button type="submit" class="btn btn--primary btn--full">
        {{ $article ? 'Salva modifiche' : 'Crea articolo' }}
      </button>
    </div>

    @include('partials.article-link-suggestions', ['article' => $article, 'linkSuggestions' => $linkSuggestions ?? collect(), 'linkSuggestionRoutePrefix' => 'admin'])

    @if($article && isset($qualityReport))
      @include('partials.editorial-quality-gate', ['qualityReport' => $qualityReport])
    @endif

    @if($article)
    <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.25rem;">
      <div style="font-family:var(--font-ui);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:1rem;">Concetti collegati (Content Graph)</div>

      @if($conceptLinks->isEmpty())
      <p style="font-size:.8rem;color:#6b7280;margin:0 0 1rem;">Nessun concetto collegato a questo articolo.</p>
      @else
      <ul style="list-style:none;padding:0;margin:0 0 1rem;display:flex;flex-direction:column;gap:.5rem;">
        @foreach($conceptLinks as $link)
        <li style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;border:1px solid #e5e7eb;border-radius:6px;padding:.5rem .65rem;">
          <div>
            <a href="{{ route('admin.concepts.edit', $link->concept_id) }}" style="font-weight:600;font-size:.82rem;color:#111827;">{{ $link->concept->name ?? '—' }}</a>
            <div style="font-size:.68rem;color:#6b7280;">
              {{ $link->relation_type === \App\Models\ArticleConcept::RELATION_PRIMARY ? 'Primario' : 'Di supporto' }} · peso {{ $link->weight }}
            </div>
          </div>
          <form method="POST" action="{{ route('admin.articles.concepts.unlink', [$article, $link->concept_id]) }}" onsubmit="return confirm('Rimuovere questo collegamento?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="action-btn" style="color:var(--color-accent);">Rimuovi</button>
          </form>
        </li>
        @endforeach
      </ul>
      @endif

      <details style="margin-top:.5rem;">
        <summary style="cursor:pointer;font-size:.78rem;font-weight:600;color:#0d9488;">Collega un nuovo concetto…</summary>

        <form method="GET" action="{{ route('admin.articles.edit', $article) }}" style="display:flex;gap:.5rem;align-items:end;margin:.75rem 0;">
          <div class="form-group" style="margin:0;flex:1;">
            <label class="form-label" for="concept_q">Cerca concetto</label>
            <input class="form-input" id="concept_q" name="concept_q" maxlength="120" value="{{ $conceptSearch }}" style="font-size:.82rem;">
          </div>
          <button type="submit" class="action-btn">Filtra</button>
        </form>

        @if($availableConcepts->isEmpty())
        <p style="font-size:.78rem;color:#6b7280;">Nessun concetto disponibile da collegare.</p>
        @else
        <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.5rem;">
          @foreach($availableConcepts as $concept)
          <li style="border:1px solid #e5e7eb;border-radius:6px;padding:.5rem .65rem;">
            <form method="POST" action="{{ route('admin.articles.concepts.link', [$article, $concept]) }}" style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap;">
              @csrf
              <span style="font-size:.8rem;font-weight:600;flex:1;min-width:120px;">{{ $concept->name }}</span>
              <select name="relation_type" class="form-select" style="width:auto;font-size:.78rem;">
                <option value="supporting">Di supporto</option>
                <option value="primary">Primario</option>
              </select>
              <input type="number" name="weight" min="0" max="255" value="50" class="form-input" style="width:5rem;font-size:.78rem;">
              <button class="action-btn" type="submit">Collega</button>
            </form>
          </li>
          @endforeach
        </ul>
        <div style="margin-top:.5rem;">{{ $availableConcepts->onEachSide(1)->links() }}</div>
        @endif
      </details>
    </div>
    @endif

    <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.25rem;">
      <div style="font-family:var(--font-ui);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:1rem;">Categoria principale *</div>
      <select class="form-select" name="category" required>
        <option value="">Seleziona categoria…</option>
        @foreach($categories as $slug => $label)
          <option value="{{ $slug }}"
                  {{ old('category', $article->category ?? '') === $slug ? 'selected' : '' }}>
            {{ $label }}
          </option>
        @endforeach
      </select>

      @include('admin.partials.article-secondary-categories')
    </div>

    <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.25rem;">
      <div style="font-family:var(--font-ui);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:1rem;">Media</div>

      <div class="form-group">
        <label class="form-label">Immagine copertina</label>

        @if(!empty($article?->cover_image))
        <div style="margin-bottom:.75rem;">
          <img src="{{ asset('assets/img/'.$article->cover_image) }}"
               alt="Copertina attuale"
               style="width:100%;max-height:160px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb;"
               onerror="this.style.display='none'">
          <div style="font-size:.65rem;color:#6b7280;margin-top:.25rem;">
            Attuale: {{ $article->cover_image }}
          </div>
        </div>
        @endif

        <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:1rem;margin-bottom:.5rem;">
          <div style="font-size:.72rem;font-weight:600;color:#111827;margin-bottom:.5rem;">
            Carica nuova immagine
          </div>
          <div style="display:grid;grid-template-columns:1fr auto;gap:.5rem;align-items:center;">
            <input type="file" name="cover_image_upload"
                   accept="image/jpeg,image/png,image/webp"
                   style="font-size:.82rem;padding:.4rem;border:1px solid #e5e7eb;border-radius:6px;background:#fff;width:100%;">
            <div style="font-size:.7rem;color:#6b7280;white-space:nowrap;">
              max 5 MB
            </div>
          </div>
          <div style="font-size:.68rem;color:#6b7280;margin-top:.35rem;">
            Seleziona un file per sostituire l'immagine attuale. Il salvataggio avviene cliccando "Salva modifiche".
          </div>
        </div>

        <input class="form-input" type="text" id="cover_image" name="cover_image"
               placeholder="oppure inserisci il nome del file dalla libreria media"
               value="{{ old('cover_image', $article->cover_image ?? '') }}"
               style="font-size:.82rem;">

        <div style="margin-top:.4rem;">
          <a href="{{ route('admin.media') }}" target="_blank"
             style="font-size:.72rem;color:#0d9488;">
            📁 Libreria media →
          </a>
        </div>
      </div>

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

      <div class="form-group">
        <span class="form-label">Tempo di lettura</span>
        <div id="read-minutes-preview" style="font-weight:600;font-size:.92rem;">
          {{ $article->read_minutes ?? 1 }} min
        </div>
        <small class="form-hint">
          Calcolato automaticamente dal testo dell'articolo (200 parole al minuto) al salvataggio — non modificabile.
        </small>
      </div>
    </div>

    <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.25rem;">
      <div style="font-family:var(--font-ui);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:1rem;">SEO</div>

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

  </div>
</form>

@if($article && $article->projects->isNotEmpty())
  <div class="admin-card" style="margin-top:1.5rem;">
    <h3 style="margin-top:0;font-size:.95rem;">Progetti collegati</h3>
    <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.5rem;">
      @foreach($article->projects as $project)
        <li>
          <a href="{{ route('admin.progettazione.projects.show', $project) }}" style="font-weight:600;">{{ $project->title }}</a>
          <span class="status status--project-{{ $project->operational_status }}" style="margin-left:.5rem;">{{ \App\Models\Project::statusOptions()[$project->operational_status] ?? $project->operational_status }}</span>
        </li>
      @endforeach
    </ul>
  </div>
@endif

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof tinymce === 'undefined') {
    console.error('TinyMCE non caricato: controlla CSP o CDN.');
    return;
  }

  tinymce.init({
    selector: '#body',
    height: 650,
    menubar: 'file edit view insert format tools table help',
    branding: false,
    promotion: false,
    resize: true,
    plugins: 'advlist autolink lists link image media table code preview fullscreen searchreplace visualblocks wordcount charmap anchor codesample',
    toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table | removeformat | code preview fullscreen',
    block_formats: 'Paragrafo=p; Titolo 1=h1; Titolo 2=h2; Titolo 3=h3; Citazione=blockquote',
    font_size_formats: '12px 14px 16px 18px 20px 24px 28px 32px',
    convert_urls: false,
    relative_urls: false,
    remove_script_host: false,
    setup: function (editor) {
      editor.on('change keyup', function () {
        editor.save();
        if (typeof window.kairusUpdateReadMinutesPreview === 'function') {
          window.kairusUpdateReadMinutesPreview();
        }
        if (typeof window.kairusRefreshSeoFallbackPreview === 'function') {
          window.kairusRefreshSeoFallbackPreview();
        }
      });
    },
    content_style: `
      body {
        font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
        font-size: 16px;
        line-height: 1.8;
        color: #374151;
        padding: 1rem;
      }

      h1, h2, h3 {
        color: #111827;
        font-family: Georgia, serif;
      }

      img {
        max-width: 100%;
        height: auto;
        border-radius: 8px;
      }

      blockquote {
        border-left: 3px solid #0d9488;
        margin: 1.5rem 0;
        padding: 0.75rem 1.25rem;
        background: #f0fdfa;
        font-style: italic;
      }

      a {
        color: #0d9488;
        text-decoration: underline;
      }

      a:hover {
        color: #0f766e;
      }

      a:focus-visible {
        outline: 2px solid #0f766e;
        outline-offset: 1px;
      }

      table {
        border-collapse: collapse;
        width: 100%;
      }

      table td,
      table th {
        border: 1px solid #e5e7eb;
        padding: 0.5rem 0.75rem;
      }

      table th {
        background: #f9fafb;
        font-weight: 600;
      }
    `
  });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const coverImageInput = document.getElementById('cover_image');
  if (! coverImageInput) {
    return;
  }

  const metadataFields = {
    alt_text: document.getElementById('cover_alt'),
    caption: document.getElementById('cover_caption'),
    credit: document.getElementById('cover_credit'),
    source: document.getElementById('cover_source'),
    source_url: document.getElementById('cover_source_url'),
    license: document.getElementById('cover_license'),
  };

  coverImageInput.addEventListener('change', function () {
    const diskName = coverImageInput.value.trim();
    if (! diskName) {
      return;
    }

    fetch('{{ route('admin.media.lookup') }}?disk_name=' + encodeURIComponent(diskName), {
      headers: { 'Accept': 'application/json' },
    })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (! data.found) {
          return;
        }

        Object.keys(metadataFields).forEach(function (key) {
          const field = metadataFields[key];
          if (field && ! field.value.trim() && data[key]) {
            field.value = data[key];
          }
        });
      })
      .catch(function () {
        // Silenzioso: il prefill e' un aiuto, non un requisito per salvare l'articolo.
      });
  });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const statusSelect = document.getElementById('status');
  const scheduleFields = document.getElementById('schedule-fields');
  const scheduleNote = document.getElementById('schedule-note');
  if (! statusSelect || ! scheduleFields) {
    return;
  }

  statusSelect.addEventListener('change', function () {
    const isScheduled = statusSelect.value === 'scheduled';
    scheduleFields.hidden = ! isScheduled;

    if (scheduleNote) {
      scheduleNote.hidden = ! isScheduled;
    }
  });
});
</script>

@include('partials.char-counter-script')
@include('partials.article-read-minutes-script')
@include('partials.article-seo-fallback-script')
@endsection
