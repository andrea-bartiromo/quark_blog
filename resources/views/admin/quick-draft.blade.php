@extends('layouts.admin')
@section('title', 'Bozza rapida')

@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Bozza rapida</h1>
  <a href="{{ route('admin.articles') }}" class="btn btn--secondary">← Articoli</a>
</div>

<div style="max-width:600px;">
  <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1.5rem;">
    <p style="font-size:.82rem;color:#6b7280;margin-bottom:1.25rem;">
      Crea una bozza veloce con solo titolo e categoria. Potrai completarla in seguito.
    </p>

    <form method="POST" action="{{ route('admin.articles.store') }}" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="status" value="draft">
      <input type="hidden" name="body" value="[Bozza da completare]">
      <input type="hidden" name="category" id="quick-category" value="intelligenza-artificiale">

      <div class="form-group">
        <label class="form-label">Titolo *</label>
        <input class="form-input" type="text" name="title"
               placeholder="Scrivi il titolo dell'articolo..."
               required autofocus style="font-size:1rem;">
      </div>

      <div class="form-group">
        <label class="form-label">Categoria *</label>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
          @foreach(config('laboratorio.categories') as $slug => $label)
          <label style="cursor:pointer;">
            <input type="radio" name="category" value="{{ $slug }}"
                   {{ $slug === 'intelligenza-artificiale' ? 'checked' : '' }}
                   style="display:none;">
            <span class="badge badge--{{ $slug }}"
                  style="cursor:pointer;padding:.3rem .75rem;font-size:.72rem;
                         border:2px solid transparent;"
                  onclick="this.closest('label').querySelector('input').checked = true;
                           document.querySelectorAll('.cat-badge').forEach(b => b.style.borderColor = 'transparent');
                           this.style.borderColor = '#0d9488';">
              {{ $label }}
            </span>
          </label>
          @endforeach
        </div>
      </div>

      <button type="submit" class="btn btn--primary" style="width:100%;">
        📝 Crea bozza
      </button>
    </form>
  </div>
</div>

@endsection