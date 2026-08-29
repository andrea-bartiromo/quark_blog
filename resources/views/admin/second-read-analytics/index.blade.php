@extends('layouts.admin')
@section('title','Second read')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Second read</h1>
</div>

<p style="color:var(--admin-muted);font-size:.85rem;margin-bottom:1rem;">
  Quanti lettori iniziano davvero una seconda lettura attraverso "Continua da qui".
  Nessun identificativo di visitatore è mai registrato: la deduplicazione usa la
  stessa sessione già impiegata per il conteggio delle visualizzazioni pubbliche.
</p>

<form method="GET" action="{{ route('admin.second-read') }}" class="articles-toolbar" style="margin-bottom:1.25rem;">
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
    <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--admin-muted);">Second read totali</dt>
    <dd style="margin:.2rem 0 0;font-weight:600;font-size:1.4rem;">{{ number_format($totals['second_reads']) }}</dd>
  </div>
  <div class="admin-card" style="margin:0;">
    <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--admin-muted);">Second read rate</dt>
    <dd style="margin:.2rem 0 0;font-weight:600;font-size:1.4rem;">{{ number_format($totals['second_read_rate'] * 100, 1) }}%</dd>
  </div>
  <div class="admin-card" style="margin:0;">
    <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--admin-muted);">Articoli sorgente coinvolti</dt>
    <dd style="margin:.2rem 0 0;font-weight:600;font-size:1.4rem;">{{ number_format($totals['source_articles_engaged']) }}</dd>
  </div>
</dl>

@if($breakdown->isEmpty())
  <div class="articles-empty-state">
    <p class="articles-empty-state__icon" aria-hidden="true">📖</p>
    <p>Nessun dato registrato ancora in questo periodo.</p>
    <p class="articles-empty-state__hint">
      I dati compaiono qui non appena un lettore vede o segue un blocco "Continua da qui" su un articolo pubblicato.
    </p>
  </div>
@else
  <div style="overflow-x:auto;">
    <table class="admin-table">
      <thead>
        <tr>
          <th scope="col">Articolo di partenza</th>
          <th scope="col">Impression</th>
          <th scope="col">Second read</th>
          <th scope="col">Second read rate</th>
        </tr>
      </thead>
      <tbody>
        @foreach($breakdown as $row)
          <tr>
            <td>
              @if($row['slug'])
                <a href="{{ route('articolo', $row['slug']) }}" target="_blank" rel="noopener">{{ Str::limit($row['title'] ?? $row['slug'], 50) }}</a>
              @else
                <span style="color:var(--admin-faint);">Articolo #{{ $row['source_article_id'] }} (non più disponibile)</span>
              @endif
            </td>
            <td>{{ number_format($row['impressions']) }}</td>
            <td>{{ number_format($row['second_reads']) }}</td>
            <td>{{ number_format($row['second_read_rate'] * 100, 1) }}%</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif

@endsection
