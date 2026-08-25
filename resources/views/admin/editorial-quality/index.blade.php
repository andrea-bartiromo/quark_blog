@extends('layouts.admin')
@section('title','Qualità editoriale')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Qualità editoriale</h1>
</div>

<p style="color:var(--admin-muted);font-size:.85rem;margin-bottom:1rem;">
  Fotografa la completezza editoriale di ogni articolo (titolo, corpo, cover, alt, SEO,
  struttura, fonti, collegamenti interni, coerenza di pubblicazione) — mai l'accuratezza
  scientifica del contenuto, che nessun controllo automatico può giudicare. Sola lettura:
  nessuna modifica viene mai applicata da questa pagina.
</p>

<form method="GET" action="{{ route('admin.editorial-quality') }}" class="articles-toolbar" style="margin-bottom:1.25rem;">
  <div class="articles-toolbar__field">
    <label class="form-label" for="stato">Stato</label>
    <select id="stato" name="stato" class="form-select" onchange="this.form.submit()">
      <option value="" @selected($selectedStatus === null)>Tutti gli stati</option>
      @foreach($statusOptions as $status)
        <option value="{{ $status }}" @selected($selectedStatus === $status)>{{ ucfirst($status) }}</option>
      @endforeach
    </select>
  </div>
</form>

<dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.5rem;">
  <div class="admin-card" style="margin:0;">
    <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--admin-muted);">Analizzati</dt>
    <dd style="margin:.2rem 0 0;font-weight:600;font-size:1.4rem;">{{ number_format($summary->analyzed) }}</dd>
  </div>
  <div class="admin-card" style="margin:0;">
    <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--admin-muted);">Pronti</dt>
    <dd style="margin:.2rem 0 0;font-weight:600;font-size:1.4rem;color:#059669;">{{ number_format($summary->readyCount) }}</dd>
  </div>
  <div class="admin-card" style="margin:0;">
    <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--admin-muted);">Attenzione</dt>
    <dd style="margin:.2rem 0 0;font-weight:600;font-size:1.4rem;color:#b45309;">{{ number_format($summary->attentionCount) }}</dd>
  </div>
  <div class="admin-card" style="margin:0;">
    <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--admin-muted);">Da completare</dt>
    <dd style="margin:.2rem 0 0;font-weight:600;font-size:1.4rem;color:#b91c1c;">{{ number_format($summary->incompleteCount) }}</dd>
  </div>
</dl>

@if($summary->mostFrequentIssues !== [])
  <section class="admin-card" style="margin-bottom:1.5rem;">
    <h2 style="font-size:1rem;margin:0 0 .75rem;">Problemi più frequenti</h2>
    <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.35rem;">
      @foreach(array_slice($summary->mostFrequentIssues, 0, 10) as $issue)
        <li style="font-size:.85rem;display:flex;justify-content:space-between;gap:1rem;">
          <span>{{ $issue['label'] }}</span>
          <span style="color:var(--admin-muted);">{{ number_format($issue['count']) }}</span>
        </li>
      @endforeach
    </ul>
  </section>
@endif

@if($flagged->isEmpty())
  <div class="articles-empty-state">
    <p class="articles-empty-state__icon" aria-hidden="true">✅</p>
    <p>Ogni articolo analizzato risulta Pronto.</p>
  </div>
@else
  <div style="overflow-x:auto;">
    <table class="admin-table">
      <thead>
        <tr>
          <th scope="col">Articolo</th>
          <th scope="col">Stato</th>
          <th scope="col">Livello</th>
          <th scope="col">Controlli superati</th>
        </tr>
      </thead>
      <tbody>
        @foreach($flagged as $entry)
          @php
            $article = $entry['article'];
            $report = $entry['report'];
            $levelColor = $report->level() === \App\Services\EditorialQuality\EditorialQualityReport::LEVEL_INCOMPLETE ? '#b91c1c' : '#b45309';
          @endphp
          <tr>
            <td><a href="{{ route('admin.articles.edit', $article) }}">{{ $article->title }}</a></td>
            <td>{{ ucfirst($article->status) }}</td>
            <td><span style="color:{{ $levelColor }};font-weight:600;">{{ $report->levelLabel() }}</span></td>
            <td>{{ $report->passedCount() }}/{{ $report->applicableCount() }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif

@endsection
