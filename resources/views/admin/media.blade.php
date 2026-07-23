@extends('layouts.admin')
@section('title', 'Libreria media')

@section('content')

<div class="admin-topbar">
  <div>
    <h1 class="admin-page-title">Libreria media</h1>
    <div style="font-size:.78rem;color:#6b7280;margin-top:.2rem;">
      {{ $currentFolder?->hierarchicalLabel($foldersById) ?? 'Radice' }} · {{ $files->total() }} immagini dirette
    </div>
  </div>
  <a href="#nuova-categoria" class="btn btn--secondary">＋ Nuova categoria</a>
</div>

@if(session('success'))
<div style="background:#d1fae5;border:1px solid #6ee7b7;border-radius:8px;padding:.85rem 1.1rem;margin-bottom:1rem;color:#065f46;font-size:.875rem;">
  ✅ {{ session('success') }}
</div>
@endif

@if(session('error'))
<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:.85rem 1.1rem;margin-bottom:1rem;color:#991b1b;font-size:.875rem;">
  ❌ {{ session('error') }}
</div>
@endif

@if($errors->any())
<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:.85rem 1.1rem;margin-bottom:1rem;color:#991b1b;font-size:.875rem;">
  ❌ {{ $errors->first() }}
</div>
@endif

{{-- Breadcrumb --}}
<nav aria-label="Percorso categoria immagini" style="display:flex;align-items:center;gap:.45rem;flex-wrap:wrap;margin-bottom:1rem;font-size:.82rem;">
  <a href="{{ route('admin.media') }}" style="color:#0d9488;text-decoration:none;">Libreria media</a>
  @foreach($breadcrumb as $ancestor)
    <span style="color:#9ca3af;">/</span>
    <a href="{{ route('admin.media', ['folder' => $ancestor->id]) }}" style="color:#0d9488;text-decoration:none;">{{ $ancestor->name }}</a>
  @endforeach
  @if($currentFolder)
    <span style="color:#9ca3af;">/</span>
    <strong style="color:#111827;">{{ $currentFolder->name }}</strong>
  @endif
</nav>

@if($currentFolder)
<div style="margin-bottom:1rem;">
  <a href="{{ $currentFolder->parent_id ? route('admin.media', ['folder' => $currentFolder->parent_id]) : route('admin.media') }}"
     style="font-size:.78rem;color:#0d9488;text-decoration:none;">← Cartella superiore</a>
</div>
@endif

{{-- Creazione categoria --}}
<details id="nuova-categoria" style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1rem 1.25rem;margin-bottom:1.25rem;" @if($errors->has('name') || $errors->has('parent_id')) open @endif>
  <summary style="cursor:pointer;font-size:.82rem;font-weight:700;color:#111827;">Nuova categoria immagini</summary>
  <form method="POST" action="{{ route('admin.media-folders.store') }}" style="margin-top:1rem;">
    @csrf
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
      <div>
        <label class="form-label">Nome</label>
        <input class="form-input" name="name" value="{{ old('name') }}" maxlength="100" required>
      </div>
      <div>
        <label class="form-label">Categoria superiore</label>
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
    <button type="submit" class="btn btn--primary" style="margin-top:.85rem;">Crea categoria</button>
  </form>
</details>

{{-- Upload --}}
<div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1.5rem;margin-bottom:1.5rem;">
  <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#6b7280;margin-bottom:1rem;">Carica nuova immagine</div>
  <form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data">
    @csrf
    <div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(220px,.6fr) auto;gap:.75rem;align-items:end;">
      <div>
        <label class="form-label">Immagine (JPEG, PNG, WebP, GIF — max 5MB)</label>
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" required class="form-input">
      </div>
      <div>
        <label class="form-label">Categoria di destinazione</label>
        <select class="form-select" name="media_folder_id">
          <option value="">Radice</option>
          @foreach($allFolders as $folder)
            <option value="{{ $folder->id }}" @selected((string) old('media_folder_id', $defaultFolder?->id) === (string) $folder->id)>
              {{ str_repeat('— ', $folder->depth() - 1) }}{{ $folder->name }}
            </option>
          @endforeach
        </select>
      </div>
      <button type="submit" class="btn btn--primary" style="white-space:nowrap;">⬆ Carica</button>
    </div>
    <div style="margin-top:.65rem;">
      <label class="form-label">Testo alternativo (opzionale)</label>
      <input type="text" name="alt_text" value="{{ old('alt_text') }}" maxlength="200" class="form-input" placeholder="Descrivi l'immagine...">
    </div>
  </form>
</div>

{{-- Categorie dirette --}}
@if($folders->isNotEmpty())
<div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#6b7280;margin-bottom:.75rem;">Categorie immagini</div>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:1rem;margin-bottom:1.5rem;">
  @foreach($folders as $folder)
  @php($directCount = $folderCounts[$folder->path] ?? 0)
  <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1rem;border:1px solid #f3f4f6;">
    <a href="{{ route('admin.media', ['folder' => $folder->id]) }}" style="display:flex;gap:.75rem;color:inherit;text-decoration:none;">
      <span style="font-size:1.65rem;line-height:1;">{{ $folder->icon ?: '📁' }}</span>
      <span style="min-width:0;">
        <strong style="display:block;font-size:.86rem;color:#111827;overflow:hidden;text-overflow:ellipsis;">{{ $folder->name }}</strong>
        <span style="display:block;font-size:.68rem;color:#6b7280;margin-top:.2rem;">{{ $directCount }} {{ $directCount === 1 ? 'immagine' : 'immagini' }}</span>
      </span>
    </a>
    @if($folder->description)
      <p style="font-size:.7rem;color:#6b7280;margin:.65rem 0 0;">{{ $folder->description }}</p>
    @endif
    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:.75rem;padding-top:.6rem;border-top:1px solid #f3f4f6;">
      @if($folder->is_protected)
        <span style="font-size:.65rem;color:#9a3412;">🔒 Protetta</span>
      @else
        <span></span>
        @if($directCount === 0 && $folder->children_count === 0)
        <form method="POST" action="{{ route('admin.media-folders.destroy', $folder) }}" onsubmit="return confirm('Eliminare questa categoria vuota?')">
          @csrf @method('DELETE')
          <button type="submit" style="background:none;border:none;color:#6b7280;font-size:.65rem;cursor:pointer;">Elimina</button>
        </form>
        @endif
      @endif
    </div>
  </div>
  @endforeach
</div>
@endif

{{-- Media diretti --}}
<div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#6b7280;margin-bottom:.75rem;">Immagini nella categoria corrente</div>

@if($files->isEmpty())
<div style="text-align:center;color:#6b7280;padding:3rem;background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);">
  <p style="font-size:2rem;margin-bottom:.5rem;">{{ $folders->isEmpty() ? '🖼' : '📂' }}</p>
  <p>{{ $currentFolder ? 'Questa categoria non contiene immagini dirette.' : 'Nessuna immagine nella radice.' }}</p>
  @if($folders->isEmpty())
    <p style="font-size:.75rem;margin-top:.35rem;">Puoi creare una categoria o caricare una nuova immagine.</p>
  @endif
</div>
@else
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem;">
  @foreach($files as $file)
  <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);overflow:hidden;">
    <div style="aspect-ratio:4/3;overflow:hidden;background:#f5f5f4;">
      <img src="{{ asset('assets/img/'.$file->disk_name) }}" alt="{{ $file->alt_text ?? $file->filename }}" style="width:100%;height:100%;object-fit:cover;" loading="lazy">
    </div>
    <div style="padding:.65rem .75rem;">
      <div style="font-size:.72rem;font-weight:600;color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ basename($file->disk_name) }}">{{ basename($file->disk_name) }}</div>
      <div style="font-size:.63rem;color:#6b7280;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:.18rem;" title="{{ $file->disk_name }}">{{ $file->disk_name }}</div>
      <div style="font-size:.65rem;color:#6b7280;display:flex;justify-content:space-between;margin-top:.25rem;"><span>{{ $file->human_size }}</span><span>{{ $file->created_at->format('d/m/Y') }}</span></div>
    </div>
    <div style="padding:.5rem .75rem;border-top:1px solid #f3f4f6;">
      <input type="text" readonly value="{{ $file->disk_name }}" onclick="this.select();copyMediaName(this.value)" class="form-input" style="font-size:.65rem;padding:.25rem .4rem;margin-bottom:.4rem;cursor:pointer;">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <a href="{{ asset('assets/img/'.$file->disk_name) }}" target="_blank" style="font-size:.65rem;color:#0d9488;text-decoration:none;">Apri →</a>
        <div style="display:flex;gap:.6rem;">
          <button type="button" onclick="document.getElementById('sposta-{{ $file->id }}').open = !document.getElementById('sposta-{{ $file->id }}').open" style="background:none;border:none;cursor:pointer;font-size:.65rem;color:#0d9488;">Sposta</button>
          <form method="POST" action="{{ route('admin.media.destroy', $file) }}" onsubmit="return confirm('Eliminare questa immagine?')">
            @csrf @method('DELETE')
            <button type="submit" style="background:none;border:none;cursor:pointer;font-size:.65rem;color:#6b7280;">Elimina</button>
          </form>
        </div>
      </div>
    </div>
    <details id="sposta-{{ $file->id }}" style="border-top:1px solid #f3f4f6;padding:.65rem .75rem;">
      <summary style="display:none;"></summary>
      <div style="font-size:.68rem;color:#6b7280;margin-bottom:.4rem;">
        Cartella attuale: <strong>{{ $file->disk_name && str_contains($file->disk_name, '/') ? $foldersById->first(fn ($f) => $f->path === dirname($file->disk_name))?->name ?? dirname($file->disk_name) : 'Radice' }}</strong>
      </div>
      <select class="form-select js-move-target" data-media-id="{{ $file->id }}" data-preflight-url="{{ route('admin.media.move-preflight', $file) }}" style="font-size:.72rem;margin-bottom:.5rem;">
        <option value="">Radice</option>
        @foreach($allFolders as $folder)
          <option value="{{ $folder->id }}">{{ str_repeat('— ', $folder->depth() - 1) }}{{ $folder->name }}</option>
        @endforeach
      </select>
      <div class="js-move-preflight" style="font-size:.65rem;color:#6b7280;margin-bottom:.5rem;min-height:1.2em;"></div>
      <form method="POST" action="{{ route('admin.media.move', $file) }}" class="js-move-form">
        @csrf @method('PATCH')
        <input type="hidden" name="media_folder_id" class="js-move-hidden-input">
        <div style="display:flex;gap:.5rem;">
          <button type="submit" class="btn btn--secondary js-move-confirm" style="font-size:.68rem;padding:.35rem .7rem;" disabled>Conferma spostamento</button>
          <button type="button" onclick="document.getElementById('sposta-{{ $file->id }}').open = false" style="background:none;border:none;cursor:pointer;font-size:.65rem;color:#6b7280;">Annulla</button>
        </div>
      </form>
    </details>
  </div>
  @endforeach
</div>

@if($files->hasPages())
<div style="margin-top:1.5rem;">{{ $files->links('components.pagination') }}</div>
@endif
@endif

<div id="toast" style="display:none;position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;background:#111827;color:#fff;font-size:.82rem;padding:.65rem 1.1rem;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.2);"></div>

@endsection

@section('scripts')
<script>
function copyMediaName(filename) {
  const done = () => {
    const toast = document.getElementById('toast');
    toast.textContent = '✓ "' + filename + '" copiato negli appunti';
    toast.style.display = 'block';
    setTimeout(() => { toast.style.display = 'none'; }, 2500);
  };

  if (navigator.clipboard) {
    navigator.clipboard.writeText(filename).then(done);
  } else {
    document.execCommand('copy');
    done();
  }
}

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
