@extends('layouts.admin')
@section('title', $cluster ? 'Modifica percorso' : 'Nuovo percorso')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">{{ $cluster ? 'Modifica percorso' : 'Nuovo percorso' }}</h1>
  <a class="action-btn" href="{{ route('admin.content-clusters.index') }}">Torna ai percorsi</a>
</div>

@if($errors->any())
<div class="admin-alert admin-alert--danger" role="alert">
  @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
</div>
@endif

@php
  $existing = $cluster?->articles?->keyBy('id') ?? collect();
  $selectedMembershipIds = collect(old('membership_ids', $existing->keys()->all()))->map(fn ($id) => (string) $id);
@endphp

<form method="POST" action="{{ $cluster ? route('admin.content-clusters.update', $cluster) : route('admin.content-clusters.store') }}">
  @csrf
  @if($cluster) @method('PUT') @endif

  <div class="admin-card" style="max-width:980px;display:grid;gap:1rem;">
    <div class="form-group"><label class="form-label" for="name">Nome</label><input id="name" class="form-input" name="name" required maxlength="160" value="{{ old('name', $cluster?->name) }}"></div>
    <div class="form-group"><label class="form-label" for="slug">Slug</label><input id="slug" class="form-input" name="slug" maxlength="180" value="{{ old('slug', $cluster?->slug) }}" placeholder="auto-generato dal nome se vuoto"></div>
    <div class="form-group"><label class="form-label" for="short_description">Descrizione breve</label><textarea id="short_description" class="form-textarea" name="short_description" maxlength="320">{{ old('short_description', $cluster?->short_description) }}</textarea></div>
    <div class="form-group"><label class="form-label" for="description">Descrizione</label><textarea id="description" class="form-textarea" name="description" style="min-height:120px;">{{ old('description', $cluster?->description) }}</textarea></div>
    <div class="form-group"><label class="form-label" for="cover_image">Cover media path</label><input id="cover_image" class="form-input" name="cover_image" maxlength="2048" value="{{ old('cover_image', $cluster?->cover_image) }}"><small>Phase 1A riusa un riferimento media esistente; nessun nuovo uploader.</small></div>
    <div class="form-group"><label class="form-label" for="seo_title">SEO title</label><input id="seo_title" class="form-input" name="seo_title" maxlength="255" value="{{ old('seo_title', $cluster?->seo_title) }}"></div>
    <div class="form-group"><label class="form-label" for="seo_description">SEO description</label><textarea id="seo_description" class="form-textarea" name="seo_description" maxlength="320">{{ old('seo_description', $cluster?->seo_description) }}</textarea></div>
    <div class="form-group"><label class="form-label" for="sort_order">Ordine percorso</label><input id="sort_order" class="form-input" type="number" min="0" name="sort_order" value="{{ old('sort_order', $cluster?->sort_order ?? 0) }}"></div>
    <label class="form-checkbox" style="display:flex;gap:.5rem;align-items:center;"><input type="checkbox" name="is_active" value="1" {{ old('is_active', $cluster?->is_active ?? false) ? 'checked' : '' }}> Percorso attivo</label>

    <fieldset style="border:1px solid #e5e7eb;border-radius:10px;padding:1rem;">
      <legend style="font-weight:700;padding:0 .4rem;">Articoli nel percorso</legend>
      <p style="font-size:.82rem;color:#6b7280;">Solo gli articoli selezionati vengono inviati al backend. Gli articoli scheduled possono essere associati; le posizioni vengono normalizzate a 10, 20, 30… al salvataggio.</p>
      <noscript><p class="admin-alert admin-alert--danger">Per modificare posizione e primary dei nuovi membri è necessario JavaScript; la selezione semplice resta salvabile.</p></noscript>
      <div style="overflow-x:auto;">
        <table class="admin-table">
          <thead><tr><th>Includi</th><th>Articolo</th><th>Stato</th><th>Posizione</th><th>Primary</th></tr></thead>
          <tbody>
          @foreach($articles as $article)
            @php
              $membership = $existing->get($article->id);
              $selected = $selectedMembershipIds->contains((string) $article->id);
              $position = old("memberships.{$article->id}.position", $membership?->pivot?->position);
              $primary = old("memberships.{$article->id}.is_primary", $membership?->pivot?->is_primary ?? false);
            @endphp
            <tr data-membership-row>
              <td>
                <input aria-label="Includi {{ $article->title }}" type="checkbox" name="membership_ids[]" value="{{ $article->id }}" data-membership-toggle {{ $selected ? 'checked' : '' }}>
              </td>
              <td>{{ $article->title }}</td>
              <td>{{ $article->status }}</td>
              <td><input aria-label="Posizione {{ $article->title }}" class="form-input" style="min-width:90px;" type="number" min="0" name="memberships[{{ $article->id }}][position]" value="{{ $position }}" data-membership-detail {{ $selected ? '' : 'disabled' }}></td>
              <td><input aria-label="Primary {{ $article->title }}" type="checkbox" name="memberships[{{ $article->id }}][is_primary]" value="1" data-membership-detail {{ $primary ? 'checked' : '' }} {{ $selected ? '' : 'disabled' }}></td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </fieldset>

    <div class="form-group">
      <label class="form-label" for="pillar_article_id">Pillar</label>
      <select id="pillar_article_id" class="form-input" name="pillar_article_id">
        <option value="">Nessun pillar</option>
        @foreach($articles as $article)
          <option value="{{ $article->id }}" {{ (string) old('pillar_article_id', $cluster?->pillar_article_id) === (string) $article->id ? 'selected' : '' }}>{{ $article->title }} — {{ $article->status }}</option>
        @endforeach
      </select>
      <small>Il backend rifiuta un pillar che non sia anche membro del percorso.</small>
    </div>

    <button class="btn btn--primary" type="submit">{{ $cluster ? 'Salva percorso' : 'Crea percorso' }}</button>
  </div>
</form>

<script>
document.querySelectorAll('[data-membership-row]').forEach((row) => {
  const toggle = row.querySelector('[data-membership-toggle]');
  const details = row.querySelectorAll('[data-membership-detail]');

  function syncMembershipInputs() {
    details.forEach((input) => {
      input.disabled = !toggle.checked;
    });
  }

  toggle.addEventListener('change', syncMembershipInputs);
  syncMembershipInputs();
});
</script>
@endsection
