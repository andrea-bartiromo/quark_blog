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

    <div style="display:flex;gap:.75rem;align-items:center;">
      <button class="btn btn--primary" type="submit">Salva modifiche</button>
      <a class="btn" href="{{ route('admin.categories') }}">Annulla</a>
    </div>
  </form>
</div>
@endsection
