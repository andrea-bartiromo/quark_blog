@extends('layouts.admin')
@section('title','Link interni')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Link interni</h1>
</div>

<p style="color:var(--admin-muted);font-size:.85rem;margin-bottom:1rem;">
  Fotografa lo stato dei collegamenti interni tra articoli: rotti, isolati, opportunità —
  un articolo "isolato" è pubblicato ma non riceve alcun collegamento da nessun altro
  articolo del sito, quindi irraggiungibile dalla navigazione da-articolo-ad-articolo anche
  se già collegato a un Concept o a un Percorso. Sola lettura: nessuna modifica viene mai
  applicata da questa pagina.
</p>

<dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.5rem;">
  <div class="admin-card" style="margin:0;">
    <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--admin-muted);">Analizzati</dt>
    <dd style="margin:.2rem 0 0;font-weight:600;font-size:1.4rem;">{{ number_format($report->analyzed) }}</dd>
  </div>
  <div class="admin-card" style="margin:0;">
    <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--admin-muted);">Senza link interni</dt>
    <dd style="margin:.2rem 0 0;font-weight:600;font-size:1.4rem;">{{ number_format($report->withoutOutgoingLinks) }}</dd>
  </div>
  <div class="admin-card" style="margin:0;">
    <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--admin-muted);">Con 1 link</dt>
    <dd style="margin:.2rem 0 0;font-weight:600;font-size:1.4rem;">{{ number_format($report->withOneOutgoingLink) }}</dd>
  </div>
  <div class="admin-card" style="margin:0;">
    <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--admin-muted);">Con 2+ link</dt>
    <dd style="margin:.2rem 0 0;font-weight:600;font-size:1.4rem;">{{ number_format($report->withTwoOrMoreOutgoingLinks) }}</dd>
  </div>
  <div class="admin-card" style="margin:0;">
    <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--admin-muted);">Isolati (pubblicati, zero incoming)</dt>
    <dd style="margin:.2rem 0 0;font-weight:600;font-size:1.4rem;color:{{ $report->isolatedArticles > 0 ? '#b91c1c' : '#059669' }};">{{ number_format($report->isolatedArticles) }}</dd>
  </div>
  <div class="admin-card" style="margin:0;">
    <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--admin-muted);">Link rotti</dt>
    <dd style="margin:.2rem 0 0;font-weight:600;font-size:1.4rem;color:{{ $report->brokenLinks > 0 ? '#b91c1c' : '#059669' }};">{{ number_format($report->brokenLinks) }}</dd>
  </div>
  <div class="admin-card" style="margin:0;">
    <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--admin-muted);">Anchor ambigui</dt>
    <dd style="margin:.2rem 0 0;font-weight:600;font-size:1.4rem;">{{ number_format($report->articlesWithAmbiguousAnchors) }}</dd>
  </div>
  <div class="admin-card" style="margin:0;">
    <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--admin-muted);">Self-link</dt>
    <dd style="margin:.2rem 0 0;font-weight:600;font-size:1.4rem;color:{{ $report->selfLinks > 0 ? '#b91c1c' : '#059669' }};">{{ number_format($report->selfLinks) }}</dd>
  </div>
  <div class="admin-card" style="margin:0;">
    <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--admin-muted);">Target non pubblicati</dt>
    <dd style="margin:.2rem 0 0;font-weight:600;font-size:1.4rem;color:{{ $report->unpublishedTargets > 0 ? '#b91c1c' : '#059669' }};">{{ number_format($report->unpublishedTargets) }}</dd>
  </div>
  <div class="admin-card" style="margin:0;">
    <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--admin-muted);">Target scheduled sicuri</dt>
    <dd style="margin:.2rem 0 0;font-weight:600;font-size:1.4rem;">{{ number_format($report->scheduledSafeLinks) }}</dd>
  </div>
  <div class="admin-card" style="margin:0;">
    <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--admin-muted);">Link reindirizzati</dt>
    <dd style="margin:.2rem 0 0;font-weight:600;font-size:1.4rem;">{{ number_format($report->redirectedLinks) }}</dd>
  </div>
</dl>

<section class="admin-card" style="margin-bottom:1.5rem;">
  <h2 style="font-size:1rem;margin:0 0 .75rem;">Pubblicati senza incoming links</h2>
  @if($report->publishedWithoutIncomingLinks === [])
    <p style="font-size:.82rem;color:var(--admin-muted);margin:0;">Nessuno — ogni articolo pubblicato riceve almeno un collegamento interno.</p>
  @else
    <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.4rem;">
      @foreach($report->publishedWithoutIncomingLinks as $row)
        <li style="font-size:.85rem;">
          <a href="{{ route('admin.articles.edit', $row['id']) }}">{{ $row['title'] }}</a>
        </li>
      @endforeach
    </ul>
  @endif
</section>

@if($report->scheduledWithoutInternalLinks !== [])
<section class="admin-card" style="margin-bottom:1.5rem;">
  <h2 style="font-size:1rem;margin:0 0 .75rem;">Programmati senza link interni</h2>
  <p style="font-size:.82rem;color:var(--admin-muted);margin:0 0 .6rem;">
    Articoli programmati che usciranno senza alcun collegamento interno in uscita — un'occasione persa
    di contesto per il lettore se pubblicati così come sono ora.
  </p>
  <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.4rem;">
    @foreach($report->scheduledWithoutInternalLinks as $row)
      <li style="font-size:.85rem;">
        <a href="{{ route('admin.articles.edit', $row['id']) }}">{{ $row['title'] }}</a>
      </li>
    @endforeach
  </ul>
</section>
@endif

@if($report->highConfidenceUnusedSuggestions !== [])
<section class="admin-card" style="margin-bottom:1.5rem;">
  <h2 style="font-size:1rem;margin:0 0 .75rem;">Opportunità di collegamento ad alta confidenza</h2>
  <p style="font-size:.82rem;color:var(--admin-muted);margin:0 0 .6rem;">
    Suggerimenti di collegamento interno non ancora rivisti, con punteggio di confidenza alto e
    temporalmente inseribili subito — una coda pronta all'azione, non un invito ad automatizzare
    l'inserimento.
  </p>
  <div style="overflow-x:auto;">
    <table class="admin-table">
      <thead>
        <tr>
          <th scope="col">Da</th>
          <th scope="col">A</th>
          <th scope="col">Anchor</th>
          <th scope="col">Confidenza</th>
        </tr>
      </thead>
      <tbody>
        @foreach($report->highConfidenceUnusedSuggestions as $suggestion)
          <tr>
            <td><a href="{{ route('admin.articles.edit', $suggestion['source']['id']) }}">{{ Str::limit($suggestion['source']['title'], 40) }}</a></td>
            <td><a href="{{ route('admin.articles.edit', $suggestion['target']['id']) }}">{{ Str::limit($suggestion['target']['title'], 40) }}</a></td>
            <td>{{ $suggestion['anchor_text'] }}</td>
            <td>{{ $suggestion['confidence_score'] }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
@endif

@if($flagged->isEmpty())
  <div class="articles-empty-state">
    <p class="articles-empty-state__icon" aria-hidden="true">✅</p>
    <p>Nessuno — tutti gli articoli analizzati sono senza anomalie rilevate.</p>
  </div>
@else
  <div style="overflow-x:auto;">
    <table class="admin-table">
      <thead>
        <tr>
          <th scope="col">Articolo</th>
          <th scope="col">Stato</th>
          <th scope="col">Anomalie</th>
        </tr>
      </thead>
      <tbody>
        @foreach($flagged as $row)
          @php
            $reasons = [];
            if ($row->countByClassification('missing') > 0) {
              $reasons[] = $row->countByClassification('missing').' link rotti';
            }
            if ($row->countByClassification('self') > 0) {
              $reasons[] = 'self-link';
            }
            if ($row->countByClassification('unpublished') > 0) {
              $reasons[] = $row->countByClassification('unpublished').' target non pubblicati';
            }
            if ($row->hasAmbiguousAnchor) {
              $reasons[] = 'anchor ambigui';
            }
            if ($row->isOrphan()) {
              $reasons[] = 'isolato';
            }
          @endphp
          <tr>
            <td><a href="{{ route('admin.articles.edit', $row->articleId) }}">{{ $row->title }}</a></td>
            <td>{{ ucfirst($row->status) }}</td>
            <td>{{ implode(', ', $reasons) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif

@endsection
