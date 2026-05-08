@extends('layouts.admin')
@section('title','Categorie')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Categorie</h1>
</div>

@if($errors->any())
<div class="admin-alert admin-alert--danger">
  @foreach($errors->all() as $error)
    <div>{{ $error }}</div>
  @endforeach
</div>
@endif

<div style="display:grid;grid-template-columns:380px 1fr;gap:1.5rem;align-items:start;">

  <div class="admin-card">
    <h3 style="margin-top:0;">Nuova categoria</h3>

    <form method="POST" action="{{ route('admin.categories.store') }}" enctype="multipart/form-data">
      @csrf

      <div class="form-group">
        <label class="form-label">Nome</label>
        <input class="form-input" type="text" name="name" value="{{ old('name') }}" required>
      </div>

      <div class="form-group">
        <label class="form-label">Slug</label>
        <input class="form-input" type="text" name="slug" value="{{ old('slug') }}" placeholder="auto-generato se vuoto">
      </div>

      <div class="form-group">
        <label class="form-label">Descrizione</label>
        <textarea class="form-textarea" name="description" style="min-height:90px;">{{ old('description') }}</textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Immagine categoria</label>
        <input type="file" name="image_upload" accept="image/jpeg,image/png,image/webp" style="font-size:.82rem;padding:.55rem;border:1px solid #e5e7eb;border-radius:8px;background:#fff;width:100%;">
        <small style="display:block;margin-top:.35rem;color:#6b7280;font-size:.72rem;">JPG, PNG o WebP. Max 4 MB. Verrà ottimizzata automaticamente.</small>
      </div>

      <div class="form-group">
        <label class="form-label">Colore badge</label>
        <input class="form-input" type="text" name="color" value="{{ old('color') }}" placeholder="#0d9488">
      </div>

      <div class="form-group">
        <label class="form-label">Ordine</label>
        <input class="form-input" type="number" name="sort_order" value="{{ old('sort_order', 0) }}">
      </div>

      <label class="form-checkbox" style="margin-bottom:1rem;display:flex;gap:.5rem;align-items:center;">
        <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
        Categoria attiva
      </label>

      <button class="btn btn--primary btn--full" type="submit">
        Crea categoria
      </button>
    </form>
  </div>

  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Immagine</th>
          <th>Nome</th>
          <th>Slug</th>
          <th>Articoli</th>
          <th>Ordine</th>
          <th>Stato</th>
          <th>Azioni</th>
        </tr>
      </thead>

      <tbody>
        @forelse($categories as $category)
        <tr>
          <td>
            @if($category->image)
              <img src="{{ asset('assets/img/categories/'.$category->image) }}"
                   alt="{{ $category->name }}"
                   style="width:72px;height:48px;object-fit:cover;border-radius:10px;border:1px solid #e5e7eb;display:block;"
                   onerror="this.style.display='none'">
            @else
              <div style="width:72px;height:48px;border-radius:10px;background:#f3f4f6;border:1px dashed #d1d5db;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:.72rem;">
                Nessuna
              </div>
            @endif
          </td>

          <td>
            <div style="font-weight:700;">{{ $category->name }}</div>
            @if($category->description)
            <div style="font-size:.74rem;color:#6b7280;margin-top:.2rem;max-width:260px;">
              {{ $category->description }}
            </div>
            @endif
          </td>

          <td><code>{{ $category->slug }}</code></td>
          <td>{{ $category->articles_count }}</td>
          <td>{{ $category->sort_order }}</td>

          <td>
            @if($category->is_active)
              <span class="status status--published">Attiva</span>
            @else
              <span class="status status--draft">Disattiva</span>
            @endif
          </td>

          <td>
            <details>
              <summary class="action-btn" style="cursor:pointer;">Modifica</summary>

              <form method="POST"
                    action="{{ route('admin.categories.update', $category) }}"
                    enctype="multipart/form-data"
                    style="margin-top:1rem;min-width:320px;display:flex;flex-direction:column;gap:.75rem;">
                @csrf
                @method('PUT')

                @if($category->image)
                  <div>
                    <img src="{{ asset('assets/img/categories/'.$category->image) }}"
                         alt="{{ $category->name }}"
                         style="width:100%;max-height:150px;object-fit:cover;border-radius:10px;border:1px solid #e5e7eb;"
                         onerror="this.style.display='none'">
                    <label class="form-checkbox" style="margin-top:.5rem;display:flex;gap:.5rem;align-items:center;color:#991b1b;">
                      <input type="checkbox" name="remove_image" value="1">
                      Rimuovi immagine attuale
                    </label>
                  </div>
                @endif

                <div>
                  <label class="form-label">Nuova immagine</label>
                  <input type="file" name="image_upload" accept="image/jpeg,image/png,image/webp" style="font-size:.82rem;padding:.55rem;border:1px solid #e5e7eb;border-radius:8px;background:#fff;width:100%;">
                </div>

                <input class="form-input" type="text" name="name" value="{{ $category->name }}" required>
                <input class="form-input" type="text" name="slug" value="{{ $category->slug }}">
                <textarea class="form-textarea" name="description" style="min-height:80px;">{{ $category->description }}</textarea>
                <input class="form-input" type="text" name="color" value="{{ $category->color }}" placeholder="#0d9488">
                <input class="form-input" type="number" name="sort_order" value="{{ $category->sort_order }}">

                <label class="form-checkbox" style="display:flex;gap:.5rem;align-items:center;">
                  <input type="checkbox" name="is_active" value="1" {{ $category->is_active ? 'checked' : '' }}>
                  Attiva
                </label>

                <button class="btn btn--primary btn--sm" type="submit">Salva modifiche</button>
              </form>

              <form method="POST"
                    action="{{ route('admin.categories.destroy', $category) }}"
                    onsubmit="return confirm('Eliminare categoria?')"
                    style="margin-top:.5rem;">
                @csrf
                @method('DELETE')
                <button type="submit" class="action-btn action-btn--danger">
                  Elimina
                </button>
              </form>
            </details>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="7" style="text-align:center;padding:2rem;color:#6b7280;">
            Nessuna categoria disponibile.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

</div>

@endsection
