@extends('layouts.admin')
@section('title','Nuova bozza Social')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Nuova bozza Social</h1>
  <a href="{{ route('admin.social-drafts.index') }}" class="btn btn--secondary">← Torna alle bozze</a>
</div>

@if($errors->any())
<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;color:#991b1b;font-size:.85rem;">
  <ul style="margin:0;padding-left:1.2rem;">
    @foreach($errors->all() as $message)
      <li>{{ $message }}</li>
    @endforeach
  </ul>
</div>
@endif

<div style="max-width:640px;background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1.5rem;">
  <form method="POST" action="{{ route('admin.social-drafts.store') }}">
    @csrf

    <div class="form-group">
      <label class="form-label" for="article_id">Articolo *</label>
      <select id="article_id" name="article_id" class="form-select" required>
        <option value="">— Seleziona un articolo —</option>
        @foreach($articles as $article)
          <option value="{{ $article->id }}" @selected(old('article_id', $preselectedArticle) == $article->id)>
            {{ $article->title }} ({{ $article->status }})
          </option>
        @endforeach
      </select>
      <small style="font-size:.72rem;color:var(--admin-muted);">
        Un articolo non ancora pubblicato può avere una bozza in preparazione, ma non potrà mai essere programmato finché non è pubblico o programmato.
      </small>
    </div>

    <div class="form-group">
      <label class="form-label" for="channel">Canale *</label>
      <select id="channel" name="channel" class="form-select" required>
        <option value="">— Seleziona un canale —</option>
        @foreach($channelOptions as $value => $label)
          <option value="{{ $value }}" @selected(old('channel') === $value)>{{ $label }}</option>
        @endforeach
      </select>
      <small style="font-size:.72rem;color:var(--admin-muted);">
        Ogni bozza rappresenta un solo canale: per pubblicare su entrambi crea due bozze distinte, con copy indipendente.
      </small>
    </div>

    <div class="form-group">
      <label class="form-label" for="copy">Copy (opzionale)</label>
      <textarea id="copy" name="copy" class="form-input" rows="5" maxlength="10000">{{ old('copy') }}</textarea>
      <small style="font-size:.72rem;color:var(--admin-muted);">
        Se lasci vuoto, viene proposto un testo di partenza da titolo e sommario dell'articolo — sempre modificabile dopo la creazione.
      </small>
    </div>

    <button type="submit" class="btn btn--primary">Crea bozza</button>
  </form>
</div>

@endsection
