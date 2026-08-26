@extends('layouts.admin')
@section('title', 'Gestione pubblicità')

@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Pubblicità</h1>
  <button type="button"
          id="modal-new-open"
          class="btn btn--primary"
          aria-haspopup="dialog"
          aria-controls="modal-new">+ Nuovo annuncio</button>
</div>

@if(session('success'))
<div style="background:#d1fae5;border:1px solid #6ee7b7;border-radius:8px;
            padding:.85rem 1.1rem;margin-bottom:1rem;color:#065f46;font-size:.875rem;">
  ✅ {{ session('success') }}
</div>
@endif

{{-- Legenda posizioni --}}
<div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.08);
            padding:1.25rem;margin-bottom:1.5rem;">
  <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;
              letter-spacing:.1em;color:#6b7280;margin-bottom:.85rem;">
    Posizioni disponibili nel sito
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.65rem;">
    @foreach(\App\Models\Ad::POSITIONS as $slug => $label)
    <div style="display:flex;align-items:center;gap:.5rem;padding:.5rem .75rem;
                background:#f9fafb;border-radius:6px;border:1px solid #e5e7eb;">
      <span style="width:8px;height:8px;border-radius:50%;background:#0d9488;flex-shrink:0;"></span>
      <div>
        <div style="font-size:.72rem;font-weight:600;color:#111827;">{{ $label }}</div>
        <div style="font-size:.63rem;color:#6b7280;font-family:monospace;">{{ $slug }}</div>
      </div>
    </div>
    @endforeach
  </div>
</div>

{{-- Lista annunci --}}
<div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.08);overflow:hidden;">
  <div style="padding:.85rem 1.1rem;border-bottom:1px solid #e5e7eb;
              display:flex;align-items:center;justify-content:space-between;">
    <span style="font-size:.7rem;font-weight:700;text-transform:uppercase;
                 letter-spacing:.1em;color:#6b7280;">
      {{ $ads->count() }} annunci
    </span>
  </div>

  @if($ads->isEmpty())
  <div style="padding:3rem;text-align:center;color:#6b7280;">
    <p style="font-size:1.5rem;margin-bottom:.5rem;">📢</p>
    <p>Nessun annuncio ancora. Clicca "+ Nuovo annuncio" per iniziare.</p>
  </div>
  @else
  <table class="admin-table">
    <thead>
      <tr>
        <th>Nome</th>
        <th>Posizione</th>
        <th>Tipo</th>
        <th>Stato</th>
        <th>Priorità</th>
        <th>Azioni</th>
      </tr>
    </thead>
    <tbody>
      @foreach($ads as $ad)
      <tr>
        <td>
          <div style="font-weight:600;font-size:.85rem;color:#111827;">{{ $ad->name }}</div>
          @if($ad->notes)
          <div style="font-size:.7rem;color:#6b7280;">{{ $ad->notes }}</div>
          @endif
        </td>
        <td>
          <span style="font-size:.72rem;font-family:monospace;background:#f3f4f6;
                       padding:.2rem .5rem;border-radius:4px;color:#374151;">
            {{ $ad->position }}
          </span>
        </td>
        <td>
          <span style="font-size:.72rem;font-weight:600;
            color:{{ $ad->type === 'adsense' ? '#1e40af' : ($ad->type === 'banner' ? '#854d0e' : '#5b21b6') }}">
            {{ \App\Models\Ad::TYPES[$ad->type] }}
          </span>
        </td>
        <td>
          <form method="POST" action="{{ route('admin.ads.toggle', $ad) }}" style="display:inline;">
            @csrf @method('PATCH')
            <button type="submit"
                    style="border:none;cursor:pointer;padding:.25rem .65rem;border-radius:20px;
                           font-size:.7rem;font-weight:700;
                           background:{{ $ad->active ? '#d1fae5' : '#f3f4f6' }};
                           color:{{ $ad->active ? '#065f46' : '#6b7280' }};">
              {{ $ad->active ? '● Attivo' : '○ Inattivo' }}
            </button>
          </form>
        </td>
        <td style="font-size:.82rem;color:#6b7280;">{{ $ad->priority }}</td>
        <td>
          <div class="actions">
            <button onclick="openEdit({{ $ad->id }})"
                    class="btn btn--secondary btn--sm">Modifica</button>
            <form method="POST" action="{{ route('admin.ads.destroy', $ad) }}"
                  onsubmit="return confirm('Eliminare questo annuncio?')">
              @csrf @method('DELETE')
              <button type="submit" class="btn btn--danger btn--sm">Elimina</button>
            </form>
          </div>
        </td>
      </tr>

      {{-- Form modifica inline nascosto --}}
      <tr id="edit-row-{{ $ad->id }}" style="display:none;background:#f0fdfa;">
        <td colspan="6" style="padding:1.25rem;">
          <form method="POST" action="{{ route('admin.ads.update', $ad) }}">
            @csrf @method('PUT')
            @include('admin.ads-form', ['ad' => $ad])
            <div style="display:flex;gap:.5rem;margin-top:1rem;">
              <button type="submit" class="btn btn--primary btn--sm">Salva</button>
              <button type="button" onclick="closeEdit({{ $ad->id }})"
                      class="btn btn--secondary btn--sm">Annulla</button>
            </div>
          </form>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif
</div>

{{-- Modal nuovo annuncio --}}
<div id="modal-new"
     role="dialog"
     aria-modal="true"
     aria-labelledby="modal-new-title"
     aria-hidden="true"
     style="display:none;position:fixed;inset:0;z-index:999;
            background:rgba(0,0,0,0.5);align-items:center;justify-content:center;padding:1rem;">
  <div style="background:#fff;border-radius:12px;padding:1.5rem;
              max-width:600px;width:100%;max-height:90vh;overflow-y:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
      <h2 id="modal-new-title"
          style="font-size:1rem;font-weight:700;color:#111827;margin:0;">Nuovo annuncio</h2>
      <button type="button"
              id="modal-new-close"
              aria-label="Chiudi finestra nuovo annuncio"
              style="background:none;border:none;cursor:pointer;font-size:1.2rem;color:#6b7280;">×</button>
    </div>
    <form method="POST" action="{{ route('admin.ads.store') }}">
      @csrf
      @include('admin.ads-form', ['ad' => null])
      <div style="display:flex;gap:.5rem;margin-top:1rem;">
        <button type="submit" class="btn btn--primary">Crea annuncio</button>
        <button type="button"
                id="modal-new-cancel"
                class="btn btn--secondary">Annulla</button>
      </div>
    </form>
  </div>
</div>

@endsection

@section('scripts')
<script>
const newAdModal = document.getElementById('modal-new');
const newAdModalOpen = document.getElementById('modal-new-open');
const newAdModalClose = document.getElementById('modal-new-close');
const newAdModalCancel = document.getElementById('modal-new-cancel');

let newAdModalLastFocused = null;

const newAdModalFocusableSelector =
  'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]):not([tabindex="-1"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

function openNewAdModal() {
  if (!newAdModal || newAdModal.style.display === 'flex') {
    return;
  }

  newAdModalLastFocused = document.activeElement;
  newAdModal.style.display = 'flex';
  newAdModal.setAttribute('aria-hidden', 'false');

  const firstFocusable = newAdModal.querySelector(newAdModalFocusableSelector);

  if (firstFocusable) {
    firstFocusable.focus();
  }
}

function closeNewAdModal() {
  if (!newAdModal || newAdModal.style.display === 'none') {
    return;
  }

  newAdModal.style.display = 'none';
  newAdModal.setAttribute('aria-hidden', 'true');

  if (
    newAdModalLastFocused &&
    typeof newAdModalLastFocused.focus === 'function'
  ) {
    newAdModalLastFocused.focus();
  }

  newAdModalLastFocused = null;
}

if (newAdModalOpen) {
  newAdModalOpen.addEventListener('click', openNewAdModal);
}

if (newAdModalClose) {
  newAdModalClose.addEventListener('click', closeNewAdModal);
}

if (newAdModalCancel) {
  newAdModalCancel.addEventListener('click', closeNewAdModal);
}

if (newAdModal) {
  newAdModal.addEventListener('click', event => {
    if (event.target === newAdModal) {
      closeNewAdModal();
    }
  });
}

document.addEventListener('keydown', event => {
  if (
    !newAdModal ||
    newAdModal.style.display !== 'flex'
  ) {
    return;
  }

  if (event.key === 'Escape') {
    event.preventDefault();
    closeNewAdModal();

    return;
  }

  if (event.key !== 'Tab') {
    return;
  }

  const focusable = Array.from(
    newAdModal.querySelectorAll(newAdModalFocusableSelector)
  );

  if (focusable.length === 0) {
    event.preventDefault();

    return;
  }

  const first = focusable[0];
  const last = focusable[focusable.length - 1];

  if (
    event.shiftKey &&
    document.activeElement === first
  ) {
    event.preventDefault();
    last.focus();
  } else if (
    !event.shiftKey &&
    document.activeElement === last
  ) {
    event.preventDefault();
    first.focus();
  }
});

function openEdit(id) {
  document.querySelectorAll('[id^="edit-row-"]').forEach(r => r.style.display = 'none');
  document.getElementById('edit-row-' + id).style.display = 'table-row';
}
function closeEdit(id) {
  document.getElementById('edit-row-' + id).style.display = 'none';
}

// Mostra/nascondi campi in base al tipo
document.querySelectorAll('[name="type"]').forEach(sel => {
  sel.addEventListener('change', function() {
    updateTypeFields(this);
  });
  updateTypeFields(sel);
});

function updateTypeFields(sel) {
  const form = sel.closest('form');
  if (!form) return;
  const type = sel.value;
  const adsense = form.querySelector('.fields-adsense');
  const banner  = form.querySelector('.fields-banner');
  const html    = form.querySelector('.fields-html');
  if (adsense) adsense.style.display = type === 'adsense' ? 'block' : 'none';
  if (banner)  banner.style.display  = type === 'banner'  ? 'block' : 'none';
  if (html)    html.style.display    = type === 'html'    ? 'block' : 'none';
}
</script>
@endsection