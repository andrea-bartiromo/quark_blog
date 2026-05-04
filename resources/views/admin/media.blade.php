@extends('layouts.admin')
@section('title', 'Libreria media')

@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Libreria media</h1>
  <span style="font-size:.82rem;color:#6b7280;">{{ $files->total() }} file caricati</span>
</div>

{{-- Messaggio successo/errore --}}
@if(session('success'))
<div style="background:#d1fae5;border:1px solid #6ee7b7;border-radius:8px;
            padding:.85rem 1.1rem;margin-bottom:1rem;color:#065f46;font-size:.875rem;">
  ✅ {{ session('success') }}
</div>
@endif

@if($errors->any())
<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;
            padding:.85rem 1.1rem;margin-bottom:1rem;color:#991b1b;font-size:.875rem;">
  ❌ {{ $errors->first() }}
</div>
@endif

{{-- Form upload semplice --}}
<div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.08);
            padding:1.5rem;margin-bottom:1.5rem;">
  <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;
              letter-spacing:.1em;color:#6b7280;margin-bottom:1rem;">
    Carica nuova immagine
  </div>

  <form method="POST" action="{{ route('admin.media.store') }}"
        enctype="multipart/form-data">
    @csrf
    <div style="display:grid;grid-template-columns:1fr auto;gap:.75rem;align-items:end;">
      <div>
        <label style="display:block;font-size:.78rem;font-weight:600;color:#111827;margin-bottom:.35rem;">
          Seleziona immagine (JPEG, PNG, WebP — max 5MB)
        </label>
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif"
               required
               style="width:100%;padding:.55rem .85rem;border:1px solid #e5e7eb;
                      border-radius:6px;font-size:.875rem;font-family:inherit;
                      background:#fff;color:#111827;">
      </div>
      <button type="submit" class="btn btn--primary" style="white-space:nowrap;">
        ⬆ Carica
      </button>
    </div>
    <div style="margin-top:.5rem;">
      <label style="font-size:.78rem;font-weight:600;color:#111827;">
        Testo alternativo (opzionale)
      </label>
      <input type="text" name="alt_text" placeholder="Descrivi l'immagine..."
             maxlength="200"
             style="width:100%;padding:.45rem .75rem;border:1px solid #e5e7eb;
                    border-radius:6px;font-size:.82rem;margin-top:.25rem;">
    </div>
  </form>
</div>

{{-- Griglia immagini --}}
@if($files->isEmpty())
<div style="text-align:center;color:#6b7280;padding:3rem;background:#fff;
            border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.08);">
  <p style="font-size:2rem;margin-bottom:.5rem;">🖼</p>
  <p>Nessuna immagine ancora. Carica la prima usando il form sopra.</p>
</div>
@else

<div style="font-size:.7rem;font-weight:700;text-transform:uppercase;
            letter-spacing:.1em;color:#6b7280;margin-bottom:.75rem;">
  Clicca su un'immagine per copiare il nome e usarla negli articoli
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem;">
  @foreach($files as $file)
  <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.08);
              overflow:hidden;border:2px solid transparent;transition:all .2s;cursor:pointer;"
       id="card-{{ $file->id }}"
       title="{{ $file->disk_name }}">

    {{-- Immagine --}}
    <div style="aspect-ratio:4/3;overflow:hidden;background:#f5f5f4;position:relative;">
      <img src="{{ asset('assets/img/'.$file->disk_name) }}"
           alt="{{ $file->alt_text ?? $file->filename }}"
           style="width:100%;height:100%;object-fit:cover;"
           loading="lazy"
           onerror="this.parentElement.innerHTML='<div style=\'display:flex;align-items:center;justify-content:center;height:100%;font-size:2rem;\'>🖼</div>'">
      <div id="check-{{ $file->id }}"
           style="display:none;position:absolute;top:6px;right:6px;
                  background:#0d9488;color:#fff;border-radius:50%;
                  width:24px;height:24px;display:none;align-items:center;
                  justify-content:center;font-size:.8rem;">✓</div>
    </div>

    {{-- Info --}}
    <div style="padding:.65rem .75rem;">
      <div style="font-size:.72rem;font-weight:600;color:#111827;overflow:hidden;
                  text-overflow:ellipsis;white-space:nowrap;" title="{{ $file->disk_name }}">
        {{ $file->disk_name }}
      </div>
      <div style="font-size:.65rem;color:#6b7280;display:flex;
                  justify-content:space-between;margin-top:.2rem;">
        <span>{{ $file->human_size }}</span>
        <span>{{ $file->created_at->format('d/m/Y') }}</span>
      </div>
    </div>

    {{-- Azioni --}}
    <div style="padding:.5rem .75rem;border-top:1px solid #f3f4f6;">
      {{-- Campo selezionabile con nome file --}}
      <div style="display:flex;gap:.35rem;align-items:center;margin-bottom:.4rem;">
        <input type="text" readonly
               value="{{ $file->disk_name }}"
               onclick="this.select();"
               style="flex:1;font-size:.65rem;padding:.25rem .4rem;
                      border:1px solid #e5e7eb;border-radius:4px;
                      background:#f9fafb;color:#111827;cursor:pointer;"
               title="Clicca per selezionare il nome">
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <a href="{{ asset('assets/img/'.$file->disk_name) }}" target="_blank"
           style="font-size:.65rem;color:#0d9488;text-decoration:none;">
          Apri →
        </a>
        <form method="POST" action="{{ route('admin.media.destroy', $file) }}"
              onsubmit="return confirm('Eliminare questa immagine?')">
          @csrf @method('DELETE')
          <button type="submit"
                  style="background:none;border:none;cursor:pointer;
                         font-size:.65rem;color:#6b7280;">
            Elimina
          </button>
        </form>
      </div>
    </div>
  </div>
  @endforeach
</div>

{{-- Paginazione --}}
@if($files->hasPages())
<div style="margin-top:1.5rem;">
  {{ $files->links('components.pagination') }}
</div>
@endif

@endif

{{-- Toast notifica --}}
<div id="toast" style="display:none;position:fixed;bottom:1.5rem;right:1.5rem;
     z-index:9999;background:#111827;color:#fff;font-size:.82rem;
     padding:.65rem 1.1rem;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.2);">
</div>

@endsection

@section('scripts')
<script>
let selectedCard = null;

function selectImage(filename, id) {
  // Rimuovi selezione precedente
  if (selectedCard) {
    document.getElementById('card-' + selectedCard).style.borderColor = 'transparent';
    const prevCheck = document.getElementById('check-' + selectedCard);
    if (prevCheck) prevCheck.style.display = 'none';
  }

  // Seleziona nuova card
  selectedCard = id;
  document.getElementById('card-' + id).style.borderColor = '#0d9488';
  const check = document.getElementById('check-' + id);
  if (check) check.style.display = 'flex';

  // Copia negli appunti
  if (navigator.clipboard) {
    navigator.clipboard.writeText(filename).then(() => showToast('✓ "' + filename + '" copiato negli appunti'));
  } else {
    // Fallback
    const ta = document.createElement('textarea');
    ta.value = filename;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    showToast('✓ "' + filename + '" copiato negli appunti');
  }
}

function showToast(msg) {
  const toast = document.getElementById('toast');
  toast.textContent = msg;
  toast.style.display = 'block';
  setTimeout(() => { toast.style.display = 'none'; }, 2500);
}
</script>
@endsection