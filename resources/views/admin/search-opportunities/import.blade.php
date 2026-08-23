@extends('layouts.admin')
@section('title','Importa Search Console')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Importa dati Search Console</h1>
  <a href="{{ route('admin.search-opportunities') }}" class="btn btn--outline">← Opportunità di ricerca</a>
</div>

<div style="background:#f0fdfa;border:1px solid #99f6e4;border-radius:8px;
            padding:.85rem 1.1rem;margin-bottom:1.25rem;font-size:.82rem;color:#0f766e;">
  ℹ️ Carica un export CSV di Google Search Console (colonne: <code>query, page,
  clicks, impressions, ctr, position</code>) indicando il periodo a cui si
  riferisce. Un secondo import per lo stesso periodo sostituisce il
  precedente, non si somma. Formato completo in
  <code>docs/SEARCH_OPPORTUNITIES.md</code>.
</div>

@if($errors->any())
<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;
            padding:.75rem 1rem;margin-bottom:1rem;color:#991b1b;font-size:.85rem;">
  @foreach($errors->all() as $e) <p>{{ $e }}</p> @endforeach
</div>
@endif

<div style="max-width:600px;">
  <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1.5rem;">
    <form method="POST" action="{{ route('admin.search-opportunities.import') }}" enctype="multipart/form-data">
      @csrf

      <div class="form-group">
        <label class="form-label">File CSV *</label>
        <input type="file" name="csv" accept=".csv,.txt" required class="form-input">
      </div>

      <div class="form-group">
        <label class="form-label">Inizio periodo *</label>
        <input type="date" name="period_start" value="{{ old('period_start') }}" required class="form-input">
      </div>

      <div class="form-group">
        <label class="form-label">Fine periodo *</label>
        <input type="date" name="period_end" value="{{ old('period_end') }}" required class="form-input">
      </div>

      <button type="submit" class="btn btn--primary">Importa</button>
    </form>
  </div>
</div>

@endsection
