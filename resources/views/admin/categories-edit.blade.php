@extends('layouts.admin')
@section('title','Modifica categoria')
@section('content')

<div class="admin-topbar">
  <div>
    <a href="{{ route('admin.categories') }}" style="font-size:.82rem;color:#64748b;text-decoration:none;">← Torna alle categorie</a>
    <h1 class="admin-page-title" style="margin-top:.45rem;">Modifica categoria</h1>
  </div>
</div>

@if(session('success'))
  <div class="admin-alert admin-alert--success">{{ session('success') }}</div>
@endif
@if($errors->any())
  <div class="admin-alert admin-alert--danger">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
@endif

<div class="admin-card" style="max-width:820px;">
  <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;margin-bottom:1.5rem;">
    <div>
      <h2 style="margin:0 0 .35rem;">{{ $category->name }}</h2>
      <div style="font-size:.8rem;color:#64748b;">{{ $category->articles_count }} articolo/i associato/i · slug: <code>{{ $category->slug }}</code></div>
    </div>
    @if($category->is_active)<span class="status status--published">Attiva</span>@else<span class="status status--draft">Disattiva</span>@endif
  </div>

  {{--
      Prompt 3 — anteprima di visibilità: stato effettivo, data in
      Europe/Rome, URL pubblico che verrà pubblicato, checklist minima
      (CategoryPublicationReadiness, stesso pattern già in uso per i
      Percorsi via ContentClusterHealth). Sola lettura: non pubblica né
      modifica mai automaticamente la categoria o gli articoli collegati.
  --}}
  @php $categoryVisibilityLabel = $category->effectiveVisibilityLabel(); @endphp
  <section class="admin-alert" role="status" style="margin-bottom:1.5rem;">
    <h2 style="font-size:1rem;margin:0 0 .5rem;">Anteprima pubblicazione</h2>
    <p style="margin:0 0 .35rem;">
      Stato effettivo:
      <span class="status {{ $categoryVisibilityLabel === 'Pubblica' ? 'status--published' : 'status--draft' }}">{{ $categoryVisibilityLabel }}</span>
      @if($category->publishedAtForEditors())
        — {{ $category->publishedAtForEditors()->format('d/m/Y H:i') }} (Europe/Rome)
      @endif
    </p>
    <p style="margin:0 0 .35rem;">
      URL pubblico:
      @if($category->isPubliclyVisible())
        <a href="{{ route('categoria', $category->slug) }}" target="_blank" rel="noopener">{{ route('categoria', $category->slug) }}</a>
      @else
        <code>{{ route('categoria', $category->slug) }}</code> — non ancora raggiungibile (risponde 404 finché la categoria non diventa pubblica)
        ·
        {{--
            Cantiere 11 (programma 100-cantieri Kairus): anteprima di sola
            lettura della stessa pagina, senza attendere l'attivazione —
            Admin\CategoryController::preview(), staff-only.
        --}}
        <a href="{{ route('admin.categories.preview', $category) }}" target="_blank" rel="noopener">Vedi anteprima →</a>
      @endif
    </p>
    @if(! empty($readiness['findings']))
      <p style="margin:.5rem 0 .25rem;font-weight:600;">Checklist:</p>
      <ul style="margin:0;padding-left:1.2rem;">
        @foreach($readiness['findings'] as $finding)
          <li>{{ \App\Services\CategoryPublicationReadiness::label($finding) }}</li>
        @endforeach
      </ul>
    @else
      <p style="margin:.5rem 0 0;color:#15803d;">Nessuna criticità rilevata.</p>
    @endif
  </section>

  <form method="POST" action="{{ route('admin.categories.update', $category) }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div class="form-group"><label class="form-label">Nome</label><input class="form-input" type="text" name="name" value="{{ old('name', $category->name) }}" required></div>
    <div class="form-group"><label class="form-label">Slug</label><input class="form-input" type="text" name="slug" value="{{ old('slug', $category->slug) }}"></div>
    <div class="form-group"><label class="form-label">Descrizione</label><textarea class="form-textarea" name="description" style="min-height:110px;">{{ old('description', $category->description) }}</textarea></div>

    <div class="form-group">
      <label class="form-label">Immagine categoria</label>
      @if($category->image)
        <div style="margin-bottom:.8rem;max-width:520px;">
          <img src="{{ asset('assets/img/categories/'.$category->image) }}" alt="{{ $category->name }}" style="width:100%;max-height:260px;object-fit:cover;border-radius:12px;border:1px solid #e5e7eb;display:block;" onerror="this.style.display='none'">
          <label class="form-checkbox" style="margin-top:.6rem;display:flex;gap:.5rem;align-items:center;color:#991b1b;"><input type="checkbox" name="remove_image" value="1" {{ old('remove_image') ? 'checked' : '' }}>Rimuovi immagine attuale</label>
        </div>
      @else
        <div style="padding:.9rem 1rem;margin-bottom:.8rem;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:10px;color:#64748b;font-size:.82rem;">Nessuna immagine impostata.</div>
      @endif
      <input type="file" name="image_upload" accept="image/jpeg,image/png,image/webp" style="font-size:.82rem;padding:.65rem;border:1px solid #e5e7eb;border-radius:8px;background:#fff;width:100%;">
      <small style="display:block;margin-top:.35rem;color:#6b7280;font-size:.72rem;">JPG, PNG o WebP. Max 4 MB. Una nuova immagine sostituisce quella attuale.</small>
    </div>

    <div style="display:grid;grid-template-columns:minmax(0,1fr) 180px;gap:1rem;">
      <div class="form-group"><label class="form-label">Colore badge</label><input class="form-input" type="text" name="color" value="{{ old('color', $category->color) }}" placeholder="#0d9488"></div>
      <div class="form-group"><label class="form-label">Ordine</label><input class="form-input" type="number" name="sort_order" value="{{ old('sort_order', $category->sort_order) }}"></div>
    </div>

    <label class="form-checkbox" style="margin:0 0 1.25rem;display:flex;gap:.5rem;align-items:center;"><input type="checkbox" name="is_active" value="1" {{ old('is_active', $category->is_active) ? 'checked' : '' }}>Categoria attiva</label>

    @php $currentCategoryStatus = old('status', $category->status); @endphp
    <div class="form-group">
      <label class="form-label" for="status">Pubblicazione</label>
      <select class="form-select" id="status" name="status">
        @foreach(\App\Models\Category::statusOptions() as $value => $label)
          <option value="{{ $value }}" {{ $currentCategoryStatus === $value ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
      </select>
    </div>

    <div class="form-group" id="schedule-fields" @if($currentCategoryStatus !== \App\Models\Category::STATUS_SCHEDULED) hidden @endif>
      <label class="form-label" for="scheduled_date">Data pubblicazione (Europe/Rome)</label>
      <input class="form-input" type="date" id="scheduled_date" name="scheduled_date"
             value="{{ old('scheduled_date', optional($category->publishedAtForEditors())->format('Y-m-d')) }}">
      <label class="form-label" for="scheduled_time" style="margin-top:.5rem;">Ora pubblicazione (Europe/Rome)</label>
      <input class="form-input" type="time" id="scheduled_time" name="scheduled_time"
             value="{{ old('scheduled_time', optional($category->publishedAtForEditors())->format('H:i')) }}">
      <small style="display:block;margin-top:.35rem;color:#6b7280;font-size:.72rem;">Fino a quella data/ora la categoria resta selezionabile nei form editoriali ma non compare in navigazione, URL pubblico, sitemap o ricerca.</small>
    </div>

    <div style="display:flex;gap:.75rem;align-items:center;margin-top:1.25rem;">
      <button class="btn btn--primary" type="submit">Salva modifiche</button>
      <a class="btn" href="{{ route('admin.categories') }}">Annulla</a>
    </div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const statusSelect = document.getElementById('status');
  const scheduleFields = document.getElementById('schedule-fields');
  if (! statusSelect || ! scheduleFields) {
    return;
  }
  statusSelect.addEventListener('change', function () {
    scheduleFields.hidden = statusSelect.value !== 'scheduled';
  });
});
</script>
@endsection
