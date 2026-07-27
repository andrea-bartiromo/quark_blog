@extends('layouts.admin')
@section('title', 'Libreria media')

@section('content')

@php
    $formatLabels = [
        'image/jpeg' => 'JPEG',
        'image/jpg' => 'JPEG',
        'image/png' => 'PNG',
        'image/webp' => 'WEBP',
        'image/gif' => 'GIF',
    ];
    $typeLabels = ['jpeg' => 'JPEG', 'png' => 'PNG', 'webp' => 'WebP', 'gif' => 'GIF'];
    $categoryLabels = ['images' => 'Immagini', 'documents' => 'Documenti', 'others' => 'Altro'];
    $sortLabels = [
        'oldest' => 'Più vecchie',
        'name_asc' => 'Nome A–Z',
        'name_desc' => 'Nome Z–A',
        'size_desc' => 'Dimensione maggiore',
        'size_asc' => 'Dimensione minore',
    ];
    $folderQuery = $currentFolder ? ['folder' => $currentFolder->id] : [];
    $uploadHasErrors = $errors->has('image') || $errors->has('alt_text');
    $advancedFiltersActive = $type !== null || $category !== null || $sort !== 'newest' || $errors->has('type') || $errors->has('category') || $errors->has('sort');
@endphp

<header class="media-header">
  <div class="media-header__top">
    <div>
      <h1 class="admin-page-title">Libreria media</h1>
      <p class="media-header__description">Cerca, filtra e organizza in cartelle le immagini usate nel sito.</p>
    </div>
    <div class="media-header__actions">
      <a href="#nuova-cartella" class="btn btn--secondary"><span aria-hidden="true">＋</span> Nuova cartella</a>
      <a href="#carica-immagine" class="btn btn--primary"><span aria-hidden="true">⬆</span> Carica immagine</a>
    </div>
  </div>

  {{-- Breadcrumb --}}
  <nav aria-label="Percorso cartella" class="media-breadcrumb">
    <a href="{{ route('admin.media') }}">Libreria media</a>
    @foreach($breadcrumb as $ancestor)
      <span class="media-breadcrumb__sep" aria-hidden="true">/</span>
      <a href="{{ route('admin.media', ['folder' => $ancestor->id]) }}">{{ $ancestor->name }}</a>
    @endforeach
    @if($currentFolder)
      <span class="media-breadcrumb__sep" aria-hidden="true">/</span>
      <strong aria-current="page">{{ $currentFolder->name }}</strong>
    @endif
  </nav>

  {{-- Cartella corrente --}}
  <div class="media-current-folder">
    <span class="media-current-folder__icon" aria-hidden="true">{{ $currentFolder?->icon ?: '📁' }}</span>
    <div class="media-current-folder__info">
      <span class="media-current-folder__label">Sei in</span>
      <strong class="media-current-folder__name">{{ $currentFolder?->hierarchicalLabel($foldersById) ?? 'Cartella radice' }}</strong>
      <span class="media-current-folder__count">{{ $files->total() }} {{ $files->total() === 1 ? 'elemento' : 'elementi' }}</span>
    </div>
    @if($currentFolder)
      <a href="{{ $currentFolder->parent_id ? route('admin.media', ['folder' => $currentFolder->parent_id]) : route('admin.media') }}"
         class="media-current-folder__back">
        <span aria-hidden="true">←</span> Cartella superiore
      </a>
    @endif
  </div>
</header>

<dl class="admin-grid admin-grid--stats media-stats" aria-label="Statistiche della libreria media">
  <div class="stat-card">
    <dt class="stat-card__label">File totali</dt>
    <dd class="stat-card__value">{{ number_format($stats['total_files']) }}</dd>
  </div>
  <div class="stat-card">
    <dt class="stat-card__label">Spazio utilizzato</dt>
    <dd class="stat-card__value">{{ $stats['total_size_human'] }}</dd>
  </div>
  <div class="stat-card">
    <dt class="stat-card__label">Immagini</dt>
    <dd class="stat-card__value">{{ number_format($stats['image_count']) }}</dd>
  </div>
  <div class="stat-card">
    <dt class="stat-card__label">Documenti</dt>
    <dd class="stat-card__value">{{ number_format($stats['document_count']) }}</dd>
  </div>
</dl>

@if(session('success'))
<div class="admin-alert admin-alert--success" role="status">
  <span aria-hidden="true">✅</span> {{ session('success') }}
</div>
@endif

@if(session('error'))
<div class="admin-alert admin-alert--danger" role="alert">
  <span aria-hidden="true">❌</span> {{ session('error') }}
</div>
@endif

@if($errors->any())
<div class="admin-alert admin-alert--danger" role="alert">
  <span aria-hidden="true">❌</span> {{ $errors->first() }}
</div>
@endif

{{-- Creazione cartella --}}
<details id="nuova-cartella" class="media-panel admin-card" @if($errors->has('name') || $errors->has('parent_id')) open @endif>
  <summary class="media-panel__summary">Nuova cartella</summary>
  <div class="media-panel__body">
    <form method="POST" action="{{ route('admin.media-folders.store') }}">
      @csrf
      <div class="media-panel__grid">
        <div>
          <label class="form-label">Nome</label>
          <input class="form-input" name="name" value="{{ old('name') }}" maxlength="100" required>
        </div>
        <div>
          <label class="form-label">Cartella padre</label>
          <select class="form-select" name="parent_id">
            <option value="">Radice</option>
            @foreach($allFolders as $folder)
              <option value="{{ $folder->id }}" @selected((string) old('parent_id', $currentFolder?->id) === (string) $folder->id)>
                {{ str_repeat('— ', $folder->depth() - 1) }}{{ $folder->name }}
              </option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="form-label">Descrizione (opzionale)</label>
          <input class="form-input" name="description" value="{{ old('description') }}" maxlength="500">
        </div>
        <div>
          <label class="form-label">Icona (opzionale)</label>
          <input class="form-input" name="icon" value="{{ old('icon') }}" maxlength="50" placeholder="es. 📁">
        </div>
      </div>
      <button type="submit" class="btn btn--primary media-panel__submit">Crea cartella</button>
    </form>
  </div>
</details>

{{-- Upload --}}
<details id="carica-immagine" class="media-panel admin-card" @if($uploadHasErrors) open @endif>
  <summary class="media-panel__summary">Carica nuova immagine</summary>
  <div class="media-panel__body">
    <form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data">
      @csrf
      <div class="media-panel__grid media-panel__grid--upload">
        <div>
          <label class="form-label">Immagine (JPEG, PNG, WebP, GIF — max 5MB)</label>
          <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" required class="form-input">
        </div>
        <div>
          <label class="form-label">Cartella di destinazione</label>
          <select class="form-select" name="media_folder_id">
            <option value="">Radice</option>
            @foreach($allFolders as $folder)
              <option value="{{ $folder->id }}" @selected((string) old('media_folder_id', $defaultFolder?->id) === (string) $folder->id)>
                {{ str_repeat('— ', $folder->depth() - 1) }}{{ $folder->name }}
              </option>
            @endforeach
          </select>
        </div>
        <button type="submit" class="btn btn--primary media-panel__upload-btn">⬆ Carica</button>
      </div>
      <div class="media-panel__alt-text">
        <label class="form-label">Testo alternativo (opzionale)</label>
        <input type="text" name="alt_text" value="{{ old('alt_text') }}" maxlength="200" class="form-input" placeholder="Descrivi l'immagine...">
      </div>
    </form>
  </div>
</details>

{{-- Sottocartelle --}}
@if($folders->isNotEmpty())
<h2 class="media-section-heading">Sottocartelle</h2>
<div class="media-folder-grid">
  @foreach($folders as $folder)
  @php
    $directCount = $folderCounts[$folder->path] ?? 0;
  @endphp
  <div class="media-folder-card">
    <a href="{{ route('admin.media', ['folder' => $folder->id]) }}" class="media-folder-card__link">
      <span class="media-folder-card__icon" aria-hidden="true">{{ $folder->icon ?: '📁' }}</span>
      <span class="media-folder-card__info">
        <strong class="media-folder-card__name">{{ $folder->name }}</strong>
        <span class="media-folder-card__count">{{ $directCount }} {{ $directCount === 1 ? 'immagine' : 'immagini' }}</span>
      </span>
    </a>
    @if($folder->description)
      <p class="media-folder-card__desc">{{ $folder->description }}</p>
    @endif
    <div class="media-folder-card__footer">
      @if($folder->is_protected)
        <span class="media-folder-card__protected"><span aria-hidden="true">🔒</span> Protetta</span>
      @else
        <span></span>
        @if($directCount === 0 && $folder->children_count === 0)
        <form method="POST" action="{{ route('admin.media-folders.destroy', $folder) }}" onsubmit="return confirm('Eliminare questa cartella vuota?')">
          @csrf @method('DELETE')
          <button type="submit" class="media-folder-card__delete">Elimina</button>
        </form>
        @endif
      @endif
    </div>
  </div>
  @endforeach
</div>
@endif

{{-- Toolbar: ricerca, filtri avanzati, ordinamento --}}
<h2 class="media-section-heading">Immagini in questa cartella</h2>

<form method="GET" action="{{ route('admin.media') }}" class="media-toolbar" role="search" aria-label="Ricerca e filtri nella libreria media">
  @foreach($folderQuery as $key => $value)
    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
  @endforeach

  <div class="media-toolbar__search-row">
    <div class="media-toolbar__field media-toolbar__field--search">
      <label class="form-label" for="media-search">Cerca</label>
      <input type="search" id="media-search" name="q" value="{{ $search }}" maxlength="100"
             class="form-input" placeholder="Nome file, percorso o testo alternativo…"
             aria-label="Cerca per nome file, percorso o testo alternativo">
    </div>
    <button type="submit" class="btn btn--primary">Filtra</button>
  </div>

  <details class="media-filters" @if($advancedFiltersActive) open @endif>
    <summary class="media-filters__summary">
      Filtri avanzati
      @if($advancedFiltersActive)<span class="badge badge--active">Attivi</span>@endif
    </summary>
    <div class="media-filters__body">
      <div class="media-toolbar__field">
        <label class="form-label" for="media-category">Tipo</label>
        <select id="media-category" name="category" class="form-select" aria-label="Filtra per tipo di file">
          <option value="">Tutti i tipi</option>
          <option value="images" @selected($category === 'images')>Immagini</option>
          <option value="documents" @selected($category === 'documents')>Documenti</option>
          <option value="others" @selected($category === 'others')>Altro</option>
        </select>
      </div>

      <div class="media-toolbar__field">
        <label class="form-label" for="media-type">Formato</label>
        <select id="media-type" name="type" class="form-select" aria-label="Filtra per formato immagine">
          <option value="">Tutti i formati</option>
          <option value="jpeg" @selected($type === 'jpeg')>JPEG</option>
          <option value="png" @selected($type === 'png')>PNG</option>
          <option value="webp" @selected($type === 'webp')>WebP</option>
          <option value="gif" @selected($type === 'gif')>GIF</option>
        </select>
      </div>

      <div class="media-toolbar__field">
        <label class="form-label" for="media-sort">Ordina per</label>
        <select id="media-sort" name="sort" class="form-select" aria-label="Ordina i risultati">
          <option value="newest" @selected($sort === 'newest')>Più recenti</option>
          <option value="oldest" @selected($sort === 'oldest')>Più vecchie</option>
          <option value="name_asc" @selected($sort === 'name_asc')>Nome A–Z</option>
          <option value="name_desc" @selected($sort === 'name_desc')>Nome Z–A</option>
          <option value="size_desc" @selected($sort === 'size_desc')>Dimensione maggiore</option>
          <option value="size_asc" @selected($sort === 'size_asc')>Dimensione minore</option>
        </select>
      </div>
    </div>
  </details>

  <div class="media-toolbar__footer">
    @if($hasActiveFilters)
      <a href="{{ route('admin.media', $folderQuery) }}" class="btn btn--secondary">Azzera filtri</a>
    @endif
    <div class="media-toolbar__count" role="status">
      {{ $files->total() }} {{ $files->total() === 1 ? 'risultato' : 'risultati' }}
    </div>
  </div>

  @if($search !== '' || $type || $category || ($sort && $sort !== 'newest'))
  <div class="media-filter-badges" aria-label="Filtri attivi">
    @if($search !== '')<span class="badge badge--filter">Ricerca: “{{ $search }}”</span>@endif
    @if($category)<span class="badge badge--filter">Tipo: {{ $categoryLabels[$category] }}</span>@endif
    @if($type)<span class="badge badge--filter">Formato: {{ $typeLabels[$type] }}</span>@endif
    @if($sort && $sort !== 'newest')<span class="badge badge--filter">Ordina: {{ $sortLabels[$sort] }}</span>@endif
  </div>
  @endif
</form>

{{-- Media diretti --}}
@if($files->isEmpty())
  @if($hasActiveFilters)
  <div class="media-empty-state">
    <p class="media-empty-state__icon" aria-hidden="true">🔍</p>
    <p>Nessuna immagine corrisponde ai filtri selezionati.</p>
    <p class="media-empty-state__hint">
      <a href="{{ route('admin.media', $folderQuery) }}">Azzera filtri</a>
    </p>
  </div>
  @else
  <div class="media-empty-state">
    <p class="media-empty-state__icon" aria-hidden="true">{{ $folders->isEmpty() ? '🖼' : '📂' }}</p>
    <p>
      @if($folders->isNotEmpty())
        Questa cartella non contiene immagini dirette, ma contiene sottocartelle.
      @elseif($currentFolder)
        Questa cartella non contiene immagini dirette.
      @else
        Nessuna immagine nella radice.
      @endif
    </p>
    @if($folders->isEmpty())
      <p class="media-empty-state__hint">Puoi creare una cartella o caricare una nuova immagine.</p>
    @endif
  </div>
  @endif
@else
<ul class="media-grid">
  @foreach($files as $file)
  @php
    $inSubfolder = str_contains($file->disk_name, '/');
    $hasDistinctPath = $file->disk_name !== $file->filename;
    $formatLabel = $formatLabels[$file->mime_type] ?? strtoupper(pathinfo($file->disk_name, PATHINFO_EXTENSION));
    $usage = $usageByDiskName[$file->disk_name] ?? [];
    $usageCount = count($usage);
    $isProtected = $file->isProtected();
    $usageBadgeLabel = $usageCount === 0
        ? 'Non utilizzata'
        : ($usageCount === 1 ? 'Usata in 1 contenuto' : "Usata in {$usageCount} contenuti");
    $deleteBlockedReason = $isProtected
        ? 'File protetto: riferimento statico nel codice del sito, non eliminabile.'
        : ($usageCount === 1
            ? 'Usata in 1 contenuto: non eliminabile finché è ancora collegata.'
            : "Usata in {$usageCount} contenuti: non eliminabile finché è ancora collegata.");
    $currentFolderLabel = $inSubfolder
        ? ($foldersById->first(fn ($f) => $f->path === dirname($file->disk_name))?->name ?? dirname($file->disk_name))
        : 'Radice';
  @endphp
  <li class="media-card">
    <div class="media-card__preview">
      <img src="{{ $file->url }}" alt="{{ $file->alt_text ?? $file->filename }}" loading="lazy" class="js-media-preview">
    </div>
    <div class="media-card__body">
      <p class="media-card__name" title="{{ $file->filename }}">{{ $file->filename }}</p>
      @if($hasDistinctPath)
        <p class="media-card__path" title="{{ $file->disk_name }}">{{ $file->disk_name }}</p>
      @endif
      <p class="media-card__usage">
        <span class="badge {{ $usageCount === 0 ? 'badge--usage-none' : 'badge--usage-used' }}">{{ $usageBadgeLabel }}</span>
        @if($isProtected)
          <span class="badge badge--protected"><span aria-hidden="true">🔒</span> Protetta</span>
        @endif
      </p>
      <p class="media-card__meta">{{ $formatLabel }} · {{ $file->human_size }} · {{ $file->created_at->format('d/m/Y') }}</p>
      @if($file->user)
        <p class="media-card__meta media-card__meta--muted">Caricata da {{ $file->user->name }}</p>
      @endif
      @if($file->alt_text)
        <p class="media-card__alt">Alt: {{ $file->alt_text }}</p>
      @endif
    </div>
    <div class="media-card__actions">
      <a href="{{ $file->url }}" target="_blank" rel="noopener" class="media-card__action media-card__action--primary" aria-label="Apri {{ $file->filename }} in una nuova scheda">Apri</a>
      <button type="button" class="js-toggle-move media-card__action" data-target="sposta-{{ $file->id }}" aria-expanded="false" aria-controls="sposta-{{ $file->id }}">Sposta</button>
      <details class="media-card__more">
        <summary class="media-card__more-summary" aria-label="Altre azioni per {{ $file->filename }}">
          <span aria-hidden="true">⋯</span><span class="sr-only">Altre azioni</span>
        </summary>
        <div class="media-card__more-menu">
          <button type="button" class="js-toggle-details" data-target="dettagli-{{ $file->id }}" aria-expanded="false" aria-controls="dettagli-{{ $file->id }}" aria-label="Modifica dettagli di {{ $file->filename }}">Modifica dettagli</button>
          <button type="button" class="js-copy-path" data-path="{{ $file->disk_name }}" aria-label="Copia il percorso di {{ $file->filename }} negli appunti">Copia percorso</button>
          <button type="button" class="js-toggle-usage" data-target="utilizzi-{{ $file->id }}" aria-expanded="false" aria-controls="utilizzi-{{ $file->id }}" aria-label="Mostra utilizzi di {{ $file->filename }}">Mostra utilizzi</button>
          @if($isProtected || $usageCount > 0)
            <button type="button" class="media-card__action--danger" disabled aria-describedby="delete-note-{{ $file->id }}">Elimina</button>
            <p id="delete-note-{{ $file->id }}" class="media-card__blocked-note">{{ $deleteBlockedReason }}</p>
          @else
            <form method="POST" action="{{ route('admin.media.destroy', $file) }}" class="js-confirm-delete" data-filename="{{ $file->filename }}">
              @csrf @method('DELETE')
              <button type="submit" class="media-card__action--danger" aria-label="Elimina {{ $file->filename }}">Elimina</button>
            </form>
          @endif
        </div>
      </details>
    </div>
    <details id="dettagli-{{ $file->id }}" class="media-card__details">
      <summary style="display:none;"></summary>

      <dl class="media-card__details-readonly">
        <div class="media-card__details-row"><dt>Nome originale</dt><dd>{{ $file->filename }}</dd></div>
        <div class="media-card__details-row"><dt>Formato</dt><dd>{{ $formatLabel }} ({{ $file->mime_type }})</dd></div>
        <div class="media-card__details-row"><dt>Dimensione</dt><dd>{{ $file->human_size }}</dd></div>
        <div class="media-card__details-row"><dt>Caricato il</dt><dd>{{ $file->created_at->format('d/m/Y H:i') }}</dd></div>
        @if($file->user)
          <div class="media-card__details-row"><dt>Caricato da</dt><dd>{{ $file->user->name }}</dd></div>
        @endif
        <div class="media-card__details-row"><dt>Cartella</dt><dd>{{ $currentFolderLabel }}</dd></div>
        <div class="media-card__details-row"><dt>Utilizzo</dt><dd>{{ $usageBadgeLabel }}</dd></div>
      </dl>

      @if($usageCount > 0)
        <div class="media-card__details-usage">
          <p class="media-card__details-subheading">Utilizzato in:</p>
          @include('admin.media._usage-list', ['usage' => $usage, 'usageCount' => $usageCount])
        </div>
      @endif

      <form method="POST" action="{{ route('admin.media.update', $file) }}" class="media-card__details-form">
        @csrf
        @method('PATCH')

        <div class="form-group">
          <label class="form-label" for="alt_text-{{ $file->id }}">Testo alternativo</label>
          <input class="form-input" type="text" id="alt_text-{{ $file->id }}" name="alt_text"
                 maxlength="200" value="{{ $file->alt_text }}">
          <small class="form-hint">
            Descrizione del file per l'accessibilità: quando disponibile, è importante soprattutto per le immagini.
          </small>
        </div>

        <div class="form-group">
          <label class="form-label" for="caption-{{ $file->id }}">Didascalia</label>
          <textarea class="form-textarea" id="caption-{{ $file->id }}" name="caption"
                    maxlength="1000" style="min-height:60px;">{{ $file->caption }}</textarea>
        </div>

        <div class="form-group">
          <label class="form-label" for="credit-{{ $file->id }}">Credito</label>
          <input class="form-input" type="text" id="credit-{{ $file->id }}" name="credit" maxlength="255" value="{{ $file->credit }}">
        </div>

        <div class="form-group">
          <label class="form-label" for="source-{{ $file->id }}">Fonte</label>
          <input class="form-input" type="text" id="source-{{ $file->id }}" name="source" maxlength="255" value="{{ $file->source }}">
        </div>

        <div class="form-group">
          <label class="form-label" for="source_url-{{ $file->id }}">URL della fonte</label>
          <input class="form-input" type="url" id="source_url-{{ $file->id }}" name="source_url" maxlength="2048" value="{{ $file->source_url }}">
        </div>

        <div class="form-group">
          <label class="form-label" for="license-{{ $file->id }}">Licenza</label>
          <input class="form-input" type="text" id="license-{{ $file->id }}" name="license" maxlength="255" value="{{ $file->license }}">
        </div>

        <div class="media-card__move-actions">
          <button type="submit" class="btn btn--secondary btn--sm">Salva dettagli</button>
          <button type="button" class="js-cancel-details media-card__move-cancel" data-target="dettagli-{{ $file->id }}">Annulla</button>
        </div>
      </form>
    </details>
    <details id="sposta-{{ $file->id }}" class="media-card__move">
      <summary style="display:none;"></summary>
      <div class="media-card__move-current">
        Cartella attuale: <strong>{{ $currentFolderLabel }}</strong>
      </div>
      <select class="form-select js-move-target media-card__move-select" data-media-id="{{ $file->id }}" data-preflight-url="{{ route('admin.media.move-preflight', $file) }}">
        <option value="">Radice</option>
        @foreach($allFolders as $folder)
          <option value="{{ $folder->id }}">{{ str_repeat('— ', $folder->depth() - 1) }}{{ $folder->name }}</option>
        @endforeach
      </select>
      <div class="js-move-preflight media-card__move-preflight"></div>
      <form method="POST" action="{{ route('admin.media.move', $file) }}" class="js-move-form">
        @csrf @method('PATCH')
        <input type="hidden" name="media_folder_id" class="js-move-hidden-input">
        <div class="media-card__move-actions">
          <button type="submit" class="btn btn--secondary btn--sm js-move-confirm" disabled>Conferma spostamento</button>
          <button type="button" class="js-cancel-move media-card__move-cancel" data-target="sposta-{{ $file->id }}">Annulla</button>
        </div>
      </form>
    </details>
    <details id="utilizzi-{{ $file->id }}" class="media-card__usage-detail">
      <summary style="display:none;"></summary>
      @include('admin.media._usage-list', ['usage' => $usage, 'usageCount' => $usageCount])
    </details>
  </li>
  @endforeach
</ul>

@if($files->hasPages())
<div class="media-pagination">{{ $files->links('components.pagination') }}</div>
@endif
@endif

<div id="toast" class="media-toast" role="status" aria-live="polite"></div>

@endsection

@section('scripts')
<script>
function showToast(message) {
  const toast = document.getElementById('toast');
  toast.textContent = message;
  toast.style.display = 'block';
  setTimeout(() => { toast.style.display = 'none'; }, 2500);
}

document.querySelectorAll('.js-media-preview').forEach(function (img) {
  img.addEventListener('error', function () {
    img.closest('.media-card__preview')?.classList.add('media-card__preview--broken');
  });
});

document.querySelectorAll('.js-copy-path').forEach(function (button) {
  button.addEventListener('click', function () {
    const path = button.dataset.path;
    const done = () => showToast('✓ "' + path + '" copiato negli appunti');

    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(path).then(done).catch(() => fallbackCopy(path, done));
    } else {
      fallbackCopy(path, done);
    }
  });
});

function fallbackCopy(text, done) {
  const input = document.createElement('textarea');
  input.value = text;
  input.style.position = 'fixed';
  input.style.opacity = '0';
  document.body.appendChild(input);
  input.focus();
  input.select();
  try {
    document.execCommand('copy');
    done();
  } finally {
    document.body.removeChild(input);
  }
}

document.querySelectorAll('.js-toggle-move, .js-toggle-usage, .js-toggle-details').forEach(function (button) {
  button.addEventListener('click', function () {
    const panel = document.getElementById(button.dataset.target);
    const willOpen = !panel.open;
    panel.open = willOpen;
    button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
  });
});

document.querySelectorAll('.js-confirm-delete').forEach(function (form) {
  form.addEventListener('submit', function (event) {
    const filename = form.dataset.filename;

    if (!confirm('Eliminare definitivamente "' + filename + '"? L\'operazione non può essere annullata.')) {
      event.preventDefault();
    }
  });
});

document.querySelectorAll('.js-cancel-move').forEach(function (button) {
  button.addEventListener('click', function () {
    const panel = document.getElementById(button.dataset.target);
    panel.open = false;
    document.querySelector('.js-toggle-move[data-target="' + button.dataset.target + '"]')
      ?.setAttribute('aria-expanded', 'false');
  });
});

document.querySelectorAll('.js-cancel-details').forEach(function (button) {
  button.addEventListener('click', function () {
    const panel = document.getElementById(button.dataset.target);
    panel.open = false;
    document.querySelector('.js-toggle-details[data-target="' + button.dataset.target + '"]')
      ?.setAttribute('aria-expanded', 'false');
  });
});

document.querySelectorAll('.js-move-target').forEach(function (select) {
  const panel = select.closest('details');
  const preflightBox = panel.querySelector('.js-move-preflight');
  const confirmBtn = panel.querySelector('.js-move-confirm');
  const hiddenInput = panel.querySelector('.js-move-hidden-input');

  select.addEventListener('change', function () {
    hiddenInput.value = select.value;
    confirmBtn.disabled = true;
    preflightBox.textContent = 'Verifica in corso…';

    const url = select.dataset.preflightUrl + (select.value ? ('?media_folder_id=' + encodeURIComponent(select.value)) : '');

    fetch(url, { headers: { 'Accept': 'application/json' } })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (data.is_noop) {
          preflightBox.textContent = 'Il file si trova già in questa destinazione.';
          confirmBtn.disabled = true;

          return;
        }

        const updatable = data.updatable_references.length;
        const blocking = data.blocking_references.length;

        if (blocking > 0) {
          preflightBox.textContent = '⚠ Bloccato: ' + blocking + ' riferimento/i non aggiornabile/i in sicurezza.';
          confirmBtn.disabled = true;

          return;
        }

        preflightBox.textContent = 'Nuovo percorso: ' + data.new_disk_name + (updatable > 0 ? ' · ' + updatable + ' riferimento/i verranno aggiornati.' : ' · nessun riferimento da aggiornare.');
        confirmBtn.disabled = false;
      })
      .catch(function () {
        preflightBox.textContent = 'Verifica non riuscita. Riprova.';
        confirmBtn.disabled = true;
      });
  });
});
</script>
@endsection
