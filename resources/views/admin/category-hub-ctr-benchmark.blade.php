@extends('layouts.admin')
@section('title','Benchmark CTR hub categorie')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Benchmark CTR hub categorie</h1>
</div>

<p style="color:var(--admin-muted);font-size:.85rem;margin-bottom:1rem;">
  Quanti visitatori di una pagina hub categoria proseguono verso un articolo. Nessun
  identificativo di visitatore è mai registrato: la deduplicazione delle impression usa
  la stessa sessione già impiegata per il conteggio delle visualizzazioni pubbliche, e il
  click-through è dedotto dal referer già raccolto per ogni visualizzazione articolo —
  nessun nuovo tracciamento dedicato introdotto.
</p>

<form method="GET" action="{{ route('admin.category-hub-ctr-benchmark') }}" class="articles-toolbar" style="margin-bottom:1.25rem;">
  <div class="articles-toolbar__field">
    <label class="form-label" for="periodo">Periodo</label>
    <select id="periodo" name="periodo" class="form-select" onchange="this.form.submit()">
      <option value="sempre" @selected($rangeOption === 'sempre')>Da sempre</option>
      <option value="90" @selected($rangeOption === '90')>Ultimi 90 giorni</option>
      <option value="30" @selected($rangeOption === '30')>Ultimi 30 giorni</option>
      <option value="7" @selected($rangeOption === '7')>Ultimi 7 giorni</option>
    </select>
  </div>
</form>

<dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem;">
  <div class="admin-card" style="margin:0;">
    <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--admin-muted);">Impression totali</dt>
    <dd style="margin:.2rem 0 0;font-weight:600;font-size:1.4rem;">{{ number_format($totals['impressions']) }}</dd>
  </div>
  <div class="admin-card" style="margin:0;">
    <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--admin-muted);">Click-through totali</dt>
    <dd style="margin:.2rem 0 0;font-weight:600;font-size:1.4rem;">{{ number_format($totals['click_throughs']) }}</dd>
  </div>
  <div class="admin-card" style="margin:0;">
    <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--admin-muted);">CTR</dt>
    <dd style="margin:.2rem 0 0;font-weight:600;font-size:1.4rem;">{{ number_format($totals['ctr'] * 100, 1) }}%</dd>
  </div>
</dl>

<section aria-labelledby="category-hub-ctr-title">
  <h2 id="category-hub-ctr-title" style="font-size:1.05rem;margin-bottom:.35rem;">CTR per categoria</h2>
  <p style="color:var(--admin-muted);font-size:.8rem;margin-bottom:.85rem;">
    Ogni categoria pubblicamente raggiungibile compare qui, anche con zero impression/click-through.
  </p>

  @if($breakdown->isEmpty())
    <div class="admin-card" style="margin:0;color:var(--admin-muted);">
      Nessuna categoria pubblicamente raggiungibile al momento.
    </div>
  @else
    <div style="overflow-x:auto;">
      <table class="admin-table">
        <thead>
          <tr>
            <th scope="col">Categoria</th>
            <th scope="col">Impression</th>
            <th scope="col">Click-through</th>
            <th scope="col">CTR</th>
          </tr>
        </thead>
        <tbody>
          @foreach($breakdown as $row)
            <tr>
              <td><a href="{{ route('categoria', $row['slug']) }}">{{ $row['name'] }}</a></td>
              <td>{{ number_format($row['impressions']) }}</td>
              <td>{{ number_format($row['click_throughs']) }}</td>
              <td>{{ number_format($row['ctr'] * 100, 1) }}%</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>
@endsection
