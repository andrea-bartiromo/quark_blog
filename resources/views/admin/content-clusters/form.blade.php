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

@if($cluster && !empty($health['findings']))
<section class="admin-alert" aria-labelledby="cluster-health-warnings" role="status">
  <h2 id="cluster-health-warnings" style="font-size:1rem;margin:0 0 .5rem;">Stato editoriale del percorso</h2>
  <p style="margin:0;">{{ implode(' · ', $health['findings']) }}</p>
  @if(!$health['pillar_public'] && $health['pillar_present'])<p>Pillar non pubblico.</p>@endif
  @if($health['scheduled_count'] > 0 && $health['article_count_published'] === 0)<p>Il percorso contiene solo contenuti non ancora pubblici.</p>@endif
  <small>Warning informativi: non bloccano il salvataggio.</small>
</section>
@endif

<form method="POST" action="{{ $cluster ? route('admin.content-clusters.update', $cluster) : route('admin.content-clusters.store') }}">
  @csrf
  @if($cluster) @method('PUT') @endif
  <div class="admin-card" style="max-width:980px;display:grid;gap:1rem;">
    <div class="form-group"><label class="form-label" for="name">Nome</label><input id="name" class="form-input" name="name" required maxlength="160" value="{{ old('name', $cluster?->name) }}"></div>
    <div class="form-group"><label class="form-label" for="slug">Slug</label><input id="slug" class="form-input" name="slug" maxlength="180" value="{{ old('slug', $cluster?->slug) }}" placeholder="auto-generato dal nome se vuoto"></div>
    <div class="form-group"><label class="form-label" for="short_description">Descrizione breve</label><textarea id="short_description" class="form-textarea" name="short_description" maxlength="320">{{ old('short_description', $cluster?->short_description) }}</textarea></div>
    <div class="form-group"><label class="form-label" for="description">Descrizione</label><textarea id="description" class="form-textarea" name="description" style="min-height:120px;">{{ old('description', $cluster?->description) }}</textarea></div>
    <div class="form-group"><label class="form-label" for="cover_image">Cover media path</label><input id="cover_image" class="form-input" name="cover_image" maxlength="2048" value="{{ old('cover_image', $cluster?->cover_image) }}"></div>
    <div class="form-group"><label class="form-label" for="seo_title">SEO title</label><input id="seo_title" class="form-input" name="seo_title" maxlength="255" value="{{ old('seo_title', $cluster?->seo_title) }}"></div>
    <div class="form-group"><label class="form-label" for="seo_description">SEO description</label><textarea id="seo_description" class="form-textarea" name="seo_description" maxlength="320">{{ old('seo_description', $cluster?->seo_description) }}</textarea></div>
    <div class="form-group"><label class="form-label" for="sort_order">Ordine percorso</label><input id="sort_order" class="form-input" type="number" min="0" name="sort_order" value="{{ old('sort_order', $cluster?->sort_order ?? 0) }}"></div>
    <label class="form-checkbox" style="display:flex;gap:.5rem;align-items:center;"><input type="checkbox" name="is_active" value="1" {{ old('is_active', $cluster?->is_active ?? false) ? 'checked' : '' }}> Percorso attivo</label>
    <button class="btn btn--primary" type="submit">{{ $cluster ? 'Salva metadati' : 'Crea percorso' }}</button>
    @if(!$cluster)<small>Dopo la creazione potrai aggiungere articoli dal catalogo ricercabile e paginato.</small>@endif
  </div>
</form>

@if($cluster)
  @php
    $existing = $cluster->articles->keyBy('id');
    $selectedIds = collect(old('membership_ids', $existing->keys()->all()))->map(fn ($id) => (int) $id);
    $selected = $existing->filter(fn ($article) => $selectedIds->contains((int) $article->id));
  @endphp

  <section class="admin-card" style="max-width:1100px;margin-top:1.25rem;" aria-labelledby="selected-memberships-title">
    <h2 id="selected-memberships-title">Membership selezionate</h2>
    <p>Questa form invia soltanto le membership del Percorso corrente. La dimensione della request non dipende dal catalogo totale.</p>
    <form method="POST" action="{{ route('admin.content-clusters.memberships.update', $cluster) }}">
      @csrf
      @method('PUT')
      <div style="overflow-x:auto;">
        <table class="admin-table">
          <thead><tr><th>Articolo</th><th>Stato</th><th>Posizione</th><th>Primary</th><th>Azione</th></tr></thead>
          <tbody>
          @forelse($selected as $article)
            @php
              $position = old("memberships.{$article->id}.position", $article->pivot?->position);
              $primary = old("memberships.{$article->id}.is_primary", $article->pivot?->is_primary ?? false);
            @endphp
            <tr>
              <td>
                {{ $article->title }}
                <input type="hidden" name="membership_ids[]" value="{{ $article->id }}">
              </td>
              <td>{{ $article->status }}</td>
              <td><input aria-label="Posizione {{ $article->title }}" class="form-input" style="min-width:90px;" type="number" min="0" name="memberships[{{ $article->id }}][position]" value="{{ $position }}"></td>
              <td><input aria-label="Primary {{ $article->title }}" type="checkbox" name="memberships[{{ $article->id }}][is_primary]" value="1" {{ $primary ? 'checked' : '' }}></td>
              <td><button class="action-btn" type="submit" name="remove_article_id" value="{{ $article->id }}">Rimuovi</button></td>
            </tr>
          @empty
            <tr><td colspan="5">Nessuna membership. Usa il catalogo qui sotto.</td></tr>
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
          <tr>
            <td>{{ $article->title }}</td><td>{{ $article->status }}</td><td>{{ $article->category }}</td>
            <td>
              <form method="POST" action="{{ route('admin.content-clusters.memberships.add', [$cluster, $article]) }}">
                @csrf
                <button class="action-btn" type="submit">Aggiungi</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="4">Nessun articolo corrisponde ai filtri correnti.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
    {{ $catalog->links() }}
  </section>
@endif
@endsection
