@extends('layouts.admin')
@section('title', $cluster ? 'Modifica percorso' : 'Nuovo percorso')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">{{ $cluster ? 'Modifica percorso' : 'Nuovo percorso' }}</h1>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
    <a class="action-btn" href="{{ route('admin.content-cluster-suggestions.index') }}">Suggerimenti</a>
    <a class="action-btn" href="{{ route('admin.content-clusters.index') }}">Torna ai percorsi</a>
  </div>
</div>

@if($errors->any())
<div class="admin-alert admin-alert--danger" role="alert">
  @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
</div>
@endif

@if($cluster && ($completeWithHiddenRemainder ?? false))
<section class="admin-alert admin-alert--danger" aria-labelledby="cluster-hidden-remainder-warning" role="alert">
  <h2 id="cluster-hidden-remainder-warning" style="font-size:1rem;margin:0 0 .5rem;">Percorso completo con nuove tappe non pubbliche</h2>
  <p style="margin:0;">Questo Percorso è marcato come "concluso", ma la sequenza pubblica si ferma prima dell'ultima tappa configurata: sono state aggiunte tappe non ancora pubbliche dopo la conclusione. Il Percorso NON viene riaperto automaticamente — una decisione editoriale esplicita (marcarlo di nuovo "in aggiornamento", o pubblicare/rimuovere le tappe nascoste) resta a te.</p>
</section>
@endif

@if($cluster && !empty($health['findings']))
<section class="admin-alert" aria-labelledby="cluster-health-warnings" role="status">
  <h2 id="cluster-health-warnings" style="font-size:1rem;margin:0 0 .5rem;">Stato editoriale del percorso</h2>
  <p style="margin:0;">{{ implode(' · ', $health['findings']) }}</p>
  @if(!$health['pillar_public'] && $health['pillar_present'])<p>Pillar non pubblico.</p>@endif
  @if($health['scheduled_count'] > 0 && $health['article_count_published'] === 0)<p>Il percorso contiene solo contenuti non ancora pubblici.</p>@endif
  <small>Warning informativi: non bloccano il salvataggio.</small>
</section>
@endif

@php
  $selectedCover = old('cover_image', $cluster?->cover_image ?? '');
  $selectedCoverUrl = $selectedCover !== '' ? asset('assets/img/'.ltrim($selectedCover, '/')) : '';
  $takeaways = array_pad(array_slice(old('takeaways', $cluster?->takeaways ?? []), 0, 4), 4, '');
  $guidingQuestions = array_pad(array_slice(old('guiding_questions', $cluster?->guiding_questions ?? []), 0, 4), 4, '');
@endphp

<form method="POST" action="{{ $cluster ? route('admin.content-clusters.update', $cluster) : route('admin.content-clusters.store') }}" id="content-cluster-form">
  @csrf
  @if($cluster) @method('PUT') @endif
  <div class="admin-card" style="max-width:980px;display:grid;gap:1rem;">
    <div class="form-group"><label class="form-label" for="name">Nome</label><input id="name" class="form-input" name="name" required maxlength="160" value="{{ old('name', $cluster?->name) }}"></div>
    <div class="form-group"><label class="form-label" for="slug">Slug</label><input id="slug" class="form-input" name="slug" maxlength="180" value="{{ old('slug', $cluster?->slug) }}" placeholder="auto-generato dal nome se vuoto"></div>
    <div class="form-group"><label class="form-label" for="short_description">Descrizione breve</label><textarea id="short_description" class="form-textarea" name="short_description" maxlength="320">{{ old('short_description', $cluster?->short_description) }}</textarea></div>
    <div class="form-group"><label class="form-label" for="description">Descrizione</label><textarea id="description" class="form-textarea" name="description" style="min-height:120px;">{{ old('description', $cluster?->description) }}</textarea></div>

    <section class="form-group" aria-labelledby="cluster-narrative-title" style="padding:1rem;border:1px solid #e5e7eb;border-radius:10px;display:grid;gap:1rem;">
      <div>
        <div class="form-label" id="cluster-narrative-title">Narrazione del Percorso</div>
        <small style="color:#6b7280;">Campi opzionali: lascia vuoto ciò che non serve. L’ordine delle voci segue l’ordine dei campi.</small>
      </div>
      <div class="form-group">
        <label class="form-label">Cosa capirai</label>
        @foreach($takeaways as $index => $value)
          <input class="form-input" name="takeaways[]" maxlength="320" value="{{ $value }}" placeholder="Voce {{ $index + 1 }}" style="margin-bottom:.45rem;">
        @endforeach
      </div>
      <div class="form-group">
        <label class="form-label">Le domande che ci guideranno</label>
        @foreach($guidingQuestions as $index => $value)
          <input class="form-input" name="guiding_questions[]" maxlength="320" value="{{ $value }}" placeholder="Domanda {{ $index + 1 }}" style="margin-bottom:.45rem;">
        @endforeach
      </div>
      <div class="form-group"><label class="form-label" for="closing_title">Titolo conclusivo</label><input id="closing_title" class="form-input" name="closing_title" maxlength="255" value="{{ old('closing_title', $cluster?->closing_title) }}"></div>
      <div class="form-group"><label class="form-label" for="closing_text">Testo conclusivo</label><textarea id="closing_text" class="form-textarea" name="closing_text" maxlength="2000">{{ old('closing_text', $cluster?->closing_text) }}</textarea></div>
      <div class="form-group">
        <label class="form-label" for="curator_note">Nota del curatore</label>
        <textarea id="curator_note" class="form-textarea" name="curator_note" maxlength="2000">{{ old('curator_note', $cluster?->curator_note) }}</textarea>
        <small style="color:#6b7280;">Opzionale, in prima persona: perché questo percorso esiste o perché questo ordine ha senso. Compare sulla pagina pubblica solo se compilata.</small>
      </div>
    </section>

    <section class="form-group" aria-labelledby="cluster-cover-title" data-cluster-cover-picker>
      <div class="form-label" id="cluster-cover-title">Cover</div>
      <input type="hidden" id="cover_image" name="cover_image" value="{{ $selectedCover }}">

      <div id="cluster-cover-preview" style="{{ $selectedCover ? '' : 'display:none;' }}margin-bottom:.85rem;max-width:560px;">
        <img id="cluster-cover-preview-image" src="{{ $selectedCoverUrl }}" alt="Anteprima cover Percorso" style="display:block;width:100%;aspect-ratio:16/9;object-fit:cover;border:1px solid #d1d5db;border-radius:10px;background:#f3f4f6;" onerror="this.style.display='none';document.getElementById('cluster-cover-missing').style.display='block';">
        <div id="cluster-cover-missing" style="display:none;padding:.75rem;border:1px dashed #f59e0b;border-radius:8px;margin-top:.5rem;color:#92400e;background:#fffbeb;">Il file selezionato non è disponibile nella posizione attesa. Puoi cambiarlo o rimuovere la cover.</div>
        <div id="cluster-cover-filename" style="font-size:.72rem;color:#6b7280;margin-top:.35rem;overflow-wrap:anywhere;">{{ $selectedCover ? basename($selectedCover) : '' }}</div>
      </div>

      <div style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;">
        <button type="button" class="btn btn--secondary" id="cluster-cover-library">Scegli dalla libreria</button>
        <button type="button" class="btn btn--secondary" id="cluster-cover-upload-button">Carica immagine</button>
        <input type="file" id="cluster-cover-upload" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
        <button type="button" class="action-btn" id="cluster-cover-change" style="{{ $selectedCover ? '' : 'display:none;' }}">Cambia immagine</button>
        <button type="button" class="action-btn" id="cluster-cover-remove" style="{{ $selectedCover ? '' : 'display:none;' }}color:#b91c1c;">Rimuovi cover</button>
      </div>
      <div id="cluster-cover-status" role="status" aria-live="polite" style="font-size:.75rem;color:#6b7280;margin-top:.5rem;"></div>
      <small style="display:block;margin-top:.5rem;color:#6b7280;">Le immagini caricate qui entrano nella normale Libreria media Kairus. JPEG, PNG, WebP o GIF, max 5 MB.</small>

      <details style="margin-top:.8rem;">
        <summary style="cursor:pointer;font-size:.78rem;color:#4b5563;">Avanzate</summary>
        <div style="margin-top:.5rem;">
          <label class="form-label" for="cover_image_advanced">Cover media path</label>
          <input id="cover_image_advanced" class="form-input" maxlength="2048" value="{{ $selectedCover }}" autocomplete="off">
          <small style="color:#6b7280;">Compatibilità/debug: normalmente non serve modificare questo percorso manualmente.</small>
        </div>
      </details>
    </section>

    <div class="form-group"><label class="form-label" for="seo_title">SEO title</label><input id="seo_title" class="form-input" name="seo_title" maxlength="255" value="{{ old('seo_title', $cluster?->seo_title) }}"></div>
    <div class="form-group"><label class="form-label" for="seo_description">SEO description</label><textarea id="seo_description" class="form-textarea" name="seo_description" maxlength="320">{{ old('seo_description', $cluster?->seo_description) }}</textarea></div>
    <div class="form-group"><label class="form-label" for="sort_order">Ordine percorso</label><input id="sort_order" class="form-input" type="number" min="0" name="sort_order" value="{{ old('sort_order', $cluster?->sort_order ?? 0) }}"></div>

    <section class="form-group" aria-labelledby="cluster-publication-title" style="padding:1rem;border:1px solid #e5e7eb;border-radius:10px;display:grid;gap:.75rem;">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
        <div class="form-label" id="cluster-publication-title" style="margin:0;">Pubblicazione</div>
        @if($cluster)
          @php $visibilityLabel = $cluster->publicVisibilityLabel(); @endphp
          <span class="status {{ $visibilityLabel === 'Pubblico' ? 'status--published' : 'status--draft' }}">
            {{ $visibilityLabel }}
            @if($visibilityLabel === 'Programmato' && $cluster->publishAtForEditors())
              — {{ $cluster->publishAtForEditors()->format('d/m/Y H:i') }}
            @endif
          </span>
        @endif
      </div>

      <label class="form-checkbox" style="display:flex;gap:.5rem;align-items:center;"><input type="checkbox" name="is_active" value="1" {{ old('is_active', $cluster?->is_active ?? false) ? 'checked' : '' }}> Percorso attivo</label>

      <div>
        <label class="form-label" for="publish_date">Data pubblicazione (Europe/Rome)</label>
        <input class="form-input" type="date" id="publish_date" name="publish_date"
               style="{{ $errors->has('publish_date') ? 'border-color:#b91c1c;' : '' }}"
               value="{{ old('publish_date', optional($cluster?->publishAtForEditors())->format('Y-m-d')) }}">
        @error('publish_date')<small style="display:block;color:#b91c1c;margin-top:.25rem;">{{ $message }}</small>@enderror

        <label class="form-label" for="publish_time" style="margin-top:.5rem;">Ora pubblicazione (Europe/Rome)</label>
        <input class="form-input" type="time" id="publish_time" name="publish_time"
               style="{{ $errors->has('publish_time') ? 'border-color:#b91c1c;' : '' }}"
               value="{{ old('publish_time', optional($cluster?->publishAtForEditors())->format('H:i')) }}">
        @error('publish_time')<small style="display:block;color:#b91c1c;margin-top:.25rem;">{{ $message }}</small>@enderror

        <small style="color:#6b7280;display:block;margin-top:.35rem;">Lascia vuoto per pubblicare subito quando il Percorso è attivo. Una data/ora già passata rende il Percorso pubblico immediatamente (se attivo); una data/ora futura lo programma. Data e ora vanno compilate insieme.</small>
      </div>
    </section>

    <div class="form-group">
      <label class="form-label" for="lifecycle_status">Stato del Percorso</label>
      <select id="lifecycle_status" class="form-input" name="lifecycle_status">
        <option value="updating" {{ old('lifecycle_status', $cluster?->lifecycle_status ?? 'complete') === 'updating' ? 'selected' : '' }}>In aggiornamento</option>
        <option value="complete" {{ old('lifecycle_status', $cluster?->lifecycle_status ?? 'complete') === 'complete' ? 'selected' : '' }}>Concluso</option>
      </select>
      <small style="color:#6b7280;">Decisione editoriale esplicita: non viene dedotta dalla presenza di bozze o articoli programmati. Eccezione: "In aggiornamento" verrà concluso automaticamente (mai il contrario) quando tutte le tappe configurate saranno entrate nel prefisso pubblico continuo — puoi comunque impostare "Concluso" manualmente in qualsiasi momento.</small>
    </div>
    <button class="btn btn--primary" type="submit">{{ $cluster ? 'Salva metadati' : 'Crea percorso' }}</button>
    @if(!$cluster)<small>Dopo la creazione potrai aggiungere articoli dal catalogo ricercabile e paginato.</small>@endif
  </div>
</form>

<dialog id="cluster-media-dialog" aria-labelledby="cluster-media-title" style="width:min(920px,calc(100vw - 2rem));max-height:85vh;border:0;border-radius:14px;padding:0;box-shadow:0 24px 70px rgba(15,23,42,.25);">
  <div style="padding:1rem 1rem .75rem;border-bottom:1px solid #e5e7eb;display:flex;gap:1rem;justify-content:space-between;align-items:center;">
    <div><strong id="cluster-media-title">Scegli dalla libreria</strong><div style="font-size:.75rem;color:#6b7280;">Solo immagini della Libreria media Kairus</div></div>
    <button type="button" class="action-btn" id="cluster-media-close" aria-label="Chiudi selettore">Chiudi</button>
  </div>
  <div style="padding:1rem;">
    <form id="cluster-media-search-form" style="display:flex;gap:.5rem;margin-bottom:1rem;">
      <input id="cluster-media-search" class="form-input" type="search" maxlength="100" aria-label="Cerca nella Libreria media" placeholder="Cerca nome file o testo alternativo…">
      <button class="btn btn--secondary" type="submit">Cerca</button>
    </form>
    <div id="cluster-media-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.75rem;max-height:55vh;overflow:auto;"></div>
    <div id="cluster-media-empty" style="display:none;padding:1rem;color:#6b7280;">Nessuna immagine trovata.</div>
    <div style="display:flex;justify-content:space-between;gap:.5rem;align-items:center;margin-top:1rem;">
      <button type="button" class="action-btn" id="cluster-media-prev">← Precedenti</button>
      <span id="cluster-media-page" style="font-size:.75rem;color:#6b7280;"></span>
      <button type="button" class="action-btn" id="cluster-media-next">Successive →</button>
    </div>
  </div>
</dialog>

@if($cluster)
  @php
    $existing = $cluster->articles->keyBy('id');
    $selectedIds = collect(old('membership_ids', $existing->keys()->all()))->map(fn ($id) => (int) $id);
    $selected = $existing->filter(fn ($article) => $selectedIds->contains((int) $article->id));
  @endphp

  @php
    $orderHealthLabels = [
      'missing_position' => ['Posizione mancante', '#b91c1c'],
      'non_positive_position' => ['Posizione non valida', '#b91c1c'],
      'duplicate_position' => ['Posizione duplicata', '#b91c1c'],
      'published_beyond_gap' => ['Pubblicato oltre il gap', '#b91c1c'],
      'chronological_inversion' => ['Fuori ordine cronologico', '#b45309'],
      'scheduled_out_of_order' => ['Programmazione fuori ordine', '#b45309'],
      'dangling_transition' => ['Raccordo senza tappa successiva', '#b45309'],
    ];
  @endphp

  @if($selected->isNotEmpty())
    <section class="admin-card" style="max-width:1100px;margin-top:1.25rem;" aria-labelledby="publication-timeline-title">
      <h2 id="publication-timeline-title" style="font-size:1rem;">Timeline di pubblicazione</h2>
      <p><small>Ogni tappa nell'ordine editoriale (posizione), con data di pubblicazione e segnali dalla Editorial Order Health. I segnali rossi indicano un problema strutturale o di pubblico raggiungimento; quelli ambra sono avvisi editoriali, mai bloccanti.</small></p>
      <div style="overflow-x:auto;">
        <ol style="display:flex;gap:.75rem;list-style:none;padding:0;margin:.75rem 0 0;min-width:min-content;">
          @foreach($selected as $article)
            @php
              $articleFlags = $orderHealthFlagsByArticleId[$article->id] ?? [];
            @endphp
            <li style="flex:0 0 200px;border:1px solid {{ $articleFlags ? '#f3d9b1' : '#e5e7eb' }};border-radius:8px;padding:.6rem;background:{{ $articleFlags ? '#fffbeb' : '#fff' }};">
              <div style="font-size:.68rem;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">Posizione {{ $article->pivot?->position ?? '—' }}</div>
              <div style="font-size:.82rem;font-weight:600;margin:.2rem 0;overflow-wrap:anywhere;">{{ $article->title }}</div>
              <div style="font-size:.75rem;color:#6b7280;">
                {{ $article->status }}
                @if($article->published_at)
                  — {{ $article->published_at->timezone('Europe/Rome')->format('d/m/Y H:i') }}
                @endif
              </div>
              @foreach($articleFlags as $flagCode)
                @php
                  $flagInfo = $orderHealthLabels[$flagCode] ?? [$flagCode, '#6b7280'];
                @endphp
                <div style="font-size:.68rem;color:{{ $flagInfo[1] }};margin-top:.3rem;">● {{ $flagInfo[0] }}</div>
              @endforeach
            </li>
          @endforeach
        </ol>
      </div>
    </section>
  @endif

  <section class="admin-card" style="max-width:1100px;margin-top:1.25rem;" aria-labelledby="selected-memberships-title">
    <h2 id="selected-memberships-title">Membership selezionate</h2>
    <p>Questa form invia soltanto le membership del Percorso corrente. La dimensione della request non dipende dal catalogo totale.</p>
    <form method="POST" action="{{ route('admin.content-clusters.memberships.update', $cluster) }}">
      @csrf
      @method('PUT')
      <div style="overflow-x:auto;">
        <table class="admin-table">
          <thead><tr><th>Articolo</th><th>Stato</th><th>Posizione</th><th>Primary</th><th>Raccordo editoriale</th><th>Azione</th></tr></thead>
          <tbody>
          @forelse($selected as $article)
            @php
              $position = old("memberships.{$article->id}.position", $article->pivot?->position);
              $primary = old("memberships.{$article->id}.is_primary", $article->pivot?->is_primary ?? false);
              $transition = old("memberships.{$article->id}.transition_text", $article->pivot?->transition_text);
            @endphp
            <tr>
              <td>{{ $article->title }}<input type="hidden" name="membership_ids[]" value="{{ $article->id }}"></td>
              <td>{{ $article->status }}</td>
              <td><input aria-label="Posizione {{ $article->title }}" class="form-input" style="min-width:90px;" type="number" min="0" name="memberships[{{ $article->id }}][position]" value="{{ $position }}"></td>
              <td><input aria-label="Primary {{ $article->title }}" type="checkbox" name="memberships[{{ $article->id }}][is_primary]" value="1" {{ $primary ? 'checked' : '' }}></td>
              <td>
                <textarea aria-label="Raccordo editoriale {{ $article->title }}" class="form-textarea" style="min-width:220px;min-height:70px;{{ in_array($article->id, $missingTransitionArticleIds ?? []) ? 'border-color:#b45309;' : '' }}" maxlength="1000" name="memberships[{{ $article->id }}][transition_text]">{{ $transition }}</textarea>
                @if(in_array($article->id, $missingTransitionArticleIds ?? []))
                  <small style="display:block;color:#b45309;margin-top:.25rem;">Manca il raccordo verso la tappa successiva.</small>
                @endif
              </td>
              <td><button class="action-btn" type="submit" name="remove_article_id" value="{{ $article->id }}">Rimuovi</button></td>
            </tr>
          @empty
            <tr><td colspan="6">Nessuna membership. Usa il catalogo qui sotto.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      <div class="form-group" style="margin-top:1rem;">
        <label class="form-label" for="pillar_article_id">Pillar</label>
        <select id="pillar_article_id" class="form-input" name="pillar_article_id">
          <option value="">Nessun pillar</option>
          @foreach($selected as $article)
            <option value="{{ $article->id }}" {{ (string) old('pillar_article_id', $cluster->pillar_article_id) === (string) $article->id ? 'selected' : '' }}>{{ $article->title }} — {{ $article->status }}</option>
          @endforeach
        </select>
        <small>Per rimuovere l’attuale pillar, seleziona prima “Nessun pillar” e salva.</small>
      </div>
      <button class="btn btn--primary" type="submit">Salva membership</button>
    </form>
  </section>

  <section class="admin-card" style="max-width:1100px;margin-top:1.25rem;" aria-labelledby="catalog-title">
    <h2 id="catalog-title">Catalogo articoli</h2>
    <form method="GET" action="{{ route('admin.content-clusters.edit', $cluster) }}" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:.75rem;align-items:end;">
      <div class="form-group"><label class="form-label" for="q">Cerca titolo</label><input id="q" class="form-input" name="q" maxlength="120" value="{{ request('q') }}"></div>
      <div class="form-group"><label class="form-label" for="status">Stato</label><select id="status" class="form-input" name="status"><option value="">Tutti</option>@foreach(\App\Models\Article::statusOptions() as $value => $label)<option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></div>
      <div class="form-group"><label class="form-label" for="category">Categoria</label><select id="category" class="form-input" name="category"><option value="">Tutte</option>@foreach($categories as $category)<option value="{{ $category }}" {{ request('category') === $category ? 'selected' : '' }}>{{ $category }}</option>@endforeach</select></div>
      <button class="btn btn--primary" type="submit">Filtra</button>
    </form>
    <div style="overflow-x:auto;margin-top:1rem;">
      <table class="admin-table">
        <thead><tr><th>Articolo</th><th>Stato</th><th>Categoria</th><th>Azione</th></tr></thead>
        <tbody>
        @forelse($catalog as $article)
          <tr><td>{{ $article->title }}</td><td>{{ $article->status }}</td><td>{{ $article->category }}</td><td><form method="POST" action="{{ route('admin.content-clusters.memberships.add', [$cluster, $article]) }}">@csrf<button class="action-btn" type="submit">Aggiungi</button></form></td></tr>
        @empty
          <tr><td colspan="4">Nessun articolo corrisponde ai filtri correnti.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
    {{ $catalog->links() }}
  </section>
@endif

<script>
(() => {
  const form = document.getElementById('content-cluster-form');
  const hidden = document.getElementById('cover_image');
  const advanced = document.getElementById('cover_image_advanced');
  const preview = document.getElementById('cluster-cover-preview');
  const image = document.getElementById('cluster-cover-preview-image');
  const missing = document.getElementById('cluster-cover-missing');
  const filename = document.getElementById('cluster-cover-filename');
  const libraryButton = document.getElementById('cluster-cover-library');
  const uploadButton = document.getElementById('cluster-cover-upload-button');
  const changeButton = document.getElementById('cluster-cover-change');
  const removeButton = document.getElementById('cluster-cover-remove');
  const uploadInput = document.getElementById('cluster-cover-upload');
  const status = document.getElementById('cluster-cover-status');
  const dialog = document.getElementById('cluster-media-dialog');
  const grid = document.getElementById('cluster-media-grid');
  const empty = document.getElementById('cluster-media-empty');
  const searchForm = document.getElementById('cluster-media-search-form');
  const searchInput = document.getElementById('cluster-media-search');
  const prev = document.getElementById('cluster-media-prev');
  const next = document.getElementById('cluster-media-next');
  const pageLabel = document.getElementById('cluster-media-page');
  let page = 1;
  let lastPage = 1;

  const selectCover = (diskName, url, label) => {
    hidden.value = diskName || '';
    advanced.value = diskName || '';
    if (!diskName) {
      preview.style.display = 'none';
      changeButton.style.display = 'none';
      removeButton.style.display = 'none';
      image.removeAttribute('src');
      filename.textContent = '';
      status.textContent = 'Cover rimossa. Salva il Percorso per confermare.';
      return;
    }
    missing.style.display = 'none';
    image.style.display = 'block';
    image.src = url || `{{ asset('assets/img') }}/${diskName.replace(/^\/+/, '')}`;
    filename.textContent = label || diskName.split('/').pop();
    preview.style.display = 'block';
    changeButton.style.display = '';
    removeButton.style.display = '';
  };

  advanced.addEventListener('input', () => selectCover(advanced.value.trim(), '', advanced.value.trim().split('/').pop()));
  removeButton.addEventListener('click', () => selectCover('', '', ''));
  changeButton.addEventListener('click', () => libraryButton.click());
  uploadButton.addEventListener('click', () => uploadInput.click());

  const renderMedia = (items) => {
    grid.innerHTML = '';
    empty.style.display = items.length ? 'none' : 'block';
    items.forEach((item) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.style.cssText = 'text-align:left;border:1px solid #e5e7eb;border-radius:10px;padding:.5rem;background:#fff;cursor:pointer;min-width:0;';
      const img = document.createElement('img');
      img.src = item.url;
      img.alt = item.alt_text || '';
      img.loading = 'lazy';
      img.style.cssText = 'display:block;width:100%;aspect-ratio:1;object-fit:cover;border-radius:7px;background:#f3f4f6;';
      const text = document.createElement('span');
      text.textContent = item.filename || item.disk_name;
      text.style.cssText = 'display:block;margin-top:.4rem;font-size:.7rem;overflow-wrap:anywhere;';
      button.append(img, text);
      button.addEventListener('click', () => {
        selectCover(item.disk_name, item.url, item.filename || item.disk_name);
        dialog.close();
        status.textContent = 'Cover selezionata dalla Libreria media.';
      });
      grid.appendChild(button);
    });
  };

  const loadMedia = async (targetPage = 1) => {
    status.textContent = '';
    const params = new URLSearchParams({page: String(targetPage)});
    if (searchInput.value.trim()) params.set('q', searchInput.value.trim());
    try {
      const response = await fetch(`{{ route('admin.content-clusters.media-picker') }}?${params.toString()}`, {headers: {'Accept': 'application/json'}});
      if (!response.ok) throw new Error('Impossibile caricare la Libreria media.');
      const payload = await response.json();
      page = payload.current_page || 1;
      lastPage = payload.last_page || 1;
      renderMedia(payload.data || []);
      prev.disabled = page <= 1;
      next.disabled = page >= lastPage;
      pageLabel.textContent = `Pagina ${page} di ${lastPage}`;
    } catch (error) {
      grid.innerHTML = '';
      empty.style.display = 'block';
      empty.textContent = error.message;
    }
  };

  libraryButton.addEventListener('click', () => { dialog.showModal(); loadMedia(1); });
  document.getElementById('cluster-media-close').addEventListener('click', () => dialog.close());
  searchForm.addEventListener('submit', (event) => { event.preventDefault(); loadMedia(1); });
  prev.addEventListener('click', () => { if (page > 1) loadMedia(page - 1); });
  next.addEventListener('click', () => { if (page < lastPage) loadMedia(page + 1); });

  uploadInput.addEventListener('change', async () => {
    const file = uploadInput.files && uploadInput.files[0];
    if (!file) return;
    status.textContent = 'Caricamento in corso…';
    const data = new FormData();
    data.append('image', file);
    data.append('_token', form.querySelector('input[name="_token"]').value);
    try {
      const response = await fetch(`{{ route('admin.media.upload') }}`, {
        method: 'POST',
        headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
        body: data,
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.ok) {
        const validation = payload.errors ? Object.values(payload.errors).flat().join(' ') : '';
        throw new Error(validation || payload.error || 'Upload non riuscito. Verifica formato e dimensione del file.');
      }
      selectCover(payload.filename, payload.url, file.name);
      status.textContent = 'Immagine caricata nella Libreria media e selezionata come cover.';
    } catch (error) {
      status.textContent = error.message;
    } finally {
      uploadInput.value = '';
    }
  });
})();
</script>
@endsection