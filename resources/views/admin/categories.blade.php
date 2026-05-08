@extends('layouts.admin')
@section('title','Categorie')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Categorie</h1>
</div>

<div style="display:grid;grid-template-columns:380px 1fr;gap:1.5rem;align-items:start;">

  <div class="admin-card">
    <h3 style="margin-top:0;">Nuova categoria</h3>

    <form method="POST" action="{{ route('admin.categories.store') }}">
      @csrf

      <div class="form-group">
        <label class="form-label">Nome</label>
        <input class="form-input" type="text" name="name" required>
      </div>

      <div class="form-group">
        <label class="form-label">Slug</label>
        <input class="form-input" type="text" name="slug" placeholder="auto-generato se vuoto">
      </div>

      <div class="form-group">
        <label class="form-label">Descrizione</label>
        <textarea class="form-textarea" name="description" style="min-height:90px;"></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Colore badge</label>
        <input class="form-input" type="text" name="color" placeholder="#0d9488">
      </div>

      <div class="form-group">
        <label class="form-label">Ordine</label>
        <input class="form-input" type="number" name="sort_order" value="0">
      </div>

      <label class="form-checkbox" style="margin-bottom:1rem;display:flex;gap:.5rem;align-items:center;">
        <input type="checkbox" name="is_active" value="1" checked>
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
            <div style="font-weight:700;">{{ $category->name }}</div>
            @if($category->description)
            <div style="font-size:.74rem;color:#6b7280;margin-top:.2rem;">
              {{ $category->description }}
            </div>
            @endif
          </td>

          <td>
            <code>{{ $category->slug }}</code>
          </td>

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
                    style="margin-top:1rem;min-width:280px;display:flex;flex-direction:column;gap:.75rem;">
                @csrf
                @method('PUT')

                <input class="form-input" type="text" name="name" value="{{ $category->name }}" required>
                <input class="form-input" type="text" name="slug" value="{{ $category->slug }}">
                <textarea class="form-textarea" name="description" style="min-height:80px;">{{ $category->description }}</textarea>
                <input class="form-input" type="text" name="color" value="{{ $category->color }}">
                <input class="form-input" type="number" name="sort_order" value="{{ $category->sort_order }}">

                <label class="form-checkbox" style="display:flex;gap:.5rem;align-items:center;">
                  <input type="checkbox" name="is_active" value="1" {{ $category->is_active ? 'checked' : '' }}>
                  Attiva
                </label>

                <button class="btn btn--primary btn--sm" type="submit">Salva</button>
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
          <td colspan="6" style="text-align:center;padding:2rem;color:#6b7280;">
            Nessuna categoria disponibile.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

</div>

@endsection
