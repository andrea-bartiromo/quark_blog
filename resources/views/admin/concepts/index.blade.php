@extends('layouts.admin')
@section('title','Concetti')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Concetti (Content Graph)</h1>
  <a class="btn btn--primary" href="{{ route('admin.concepts.create') }}">Nuovo concetto</a>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1.25rem;">
  <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.1rem 1.25rem;">
    <div style="font-family:var(--font-ui);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#6b7280;">Copertura articoli</div>
    <div style="font-size:1.7rem;font-weight:700;margin:.35rem 0;">{{ $coverage['articles']['coverage_percent'] }}%</div>
    <span style="font-size:.78rem;color:#6b7280;">{{ $coverage['articles']['published_with_concept_link'] }} / {{ $coverage['articles']['published_total'] }} pubblicati con almeno un concetto</span>
  </div>
  <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.1rem 1.25rem;">
    <div style="font-family:var(--font-ui);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#6b7280;">Concetti attivi</div>
    <div style="font-size:1.7rem;font-weight:700;margin:.35rem 0;">{{ $coverage['concepts']['by_status']['active'] }}</div>
    <span style="font-size:.78rem;color:#6b7280;">{{ $coverage['concepts']['active_without_article_link'] }} senza alcun articolo collegato</span>
  </div>
  <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.1rem 1.25rem;">
    <div style="font-family:var(--font-ui);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#6b7280;">Domande pubbliche</div>
    <div style="font-size:1.7rem;font-weight:700;margin:.35rem 0;">{{ $coverage['questions']['publicly_answerable_total'] }}</div>
    <span style="font-size:.78rem;color:#6b7280;">su {{ $coverage['questions']['total'] }} totali ({{ $coverage['questions']['active_concepts_without_questions'] }} concetti attivi senza domande)</span>
  </div>
</div>

@if(! empty($orphanArticles) || ! empty($orphanConcepts))
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;margin-bottom:1.25rem;">
    @if(! empty($orphanArticles))
      <section style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:var(--radius);padding:1rem 1.25rem;">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#374151;margin-bottom:.5rem;">
          Articoli pubblicati senza concetti ({{ count($orphanArticles) }})
        </div>
        <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.35rem;max-height:220px;overflow-y:auto;">
          @foreach($orphanArticles as $article)
            <li style="font-size:.82rem;"><a href="{{ route('admin.articles.edit', $article['id']) }}">{{ $article['title'] }}</a></li>
          @endforeach
        </ul>
      </section>
    @endif
    @if(! empty($orphanConcepts))
      <section style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:var(--radius);padding:1rem 1.25rem;">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#374151;margin-bottom:.5rem;">
          Concetti attivi senza articoli ({{ count($orphanConcepts) }})
        </div>
        <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.35rem;max-height:220px;overflow-y:auto;">
          @foreach($orphanConcepts as $orphan)
            <li style="font-size:.82rem;"><a href="{{ route('admin.concepts.edit', $orphan['id']) }}">{{ $orphan['name'] }}</a></li>
          @endforeach
        </ul>
      </section>
    @endif
  </div>
@endif

@if(! empty($duplicates))
  <section style="background:#fffbeb;border:1px solid #fde68a;border-radius:var(--radius);padding:1rem 1.25rem;margin-bottom:1.25rem;">
    <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#92400e;margin-bottom:.5rem;">
      Possibili concetti duplicati ({{ count($duplicates) }}) — informativo, nessuna fusione automatica
    </div>
    <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.6rem;">
      @foreach($duplicates as $group)
        <li style="font-size:.85rem;color:#78350f;">
          <span style="color:#92400e;">"{{ $group['normalized_text'] }}"</span> —
          @foreach($group['concepts'] as $i => $c)
            <a href="{{ route('admin.concepts.edit', $c['id']) }}">{{ $c['name'] }}</a>
            <span style="color:#b45309;font-size:.75rem;">({{ $c['matched_via'] }}, {{ $c['status'] }})</span>{{ $i < count($group['concepts']) - 1 ? ', ' : '' }}
          @endforeach
        </li>
      @endforeach
    </ul>
  </section>
@endif

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead><tr><th>Nome</th><th>Stato</th><th>Salute operativa</th><th>Alias</th><th>Articoli collegati</th><th>Domande</th><th>Azioni</th></tr></thead>
    <tbody>
      @forelse($concepts as $concept)
        @php($health = $conceptHealth->get($concept->id))
        <tr>
          <td><strong>{{ $concept->name }}</strong><br><code>{{ $concept->slug }}</code></td>
          <td><span class="status {{ $concept->status === 'active' ? 'status--published' : 'status--draft' }}">{{ ucfirst($concept->status) }}</span></td>
          <td>
            <span class="status {{ $health['health'] === 'READY' ? 'status--published' : 'status--draft' }}">{{ $health['label'] }}</span>
            @if(! empty($health['codes']))
              <details style="margin-top:.35rem;max-width:18rem;">
                <summary style="cursor:pointer;font-size:.75rem;">Diagnosi ({{ count($health['codes']) }})</summary>
                <ul style="margin:.35rem 0 0;padding-left:1rem;font-size:.72rem;">
                  @foreach(array_slice($health['codes'], 0, 3) as $code)
                    <li><code style="overflow-wrap:anywhere;">{{ $code }}</code></li>
                  @endforeach
                </ul>
              </details>
            @endif
          </td>
          <td>{{ $concept->aliases_count }}</td>
          <td>{{ $concept->article_links_count }}</td>
          <td>{{ $concept->questions_count }}</td>
          <td><a class="action-btn" href="{{ route('admin.concepts.edit', $concept) }}">{{ $health['health'] === 'READY' ? 'Modifica' : 'Verifica' }}</a></td>
        </tr>
      @empty
        <tr><td colspan="7" style="text-align:center;padding:2rem;color:#6b7280;">Nessun concetto disponibile.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

{{ $concepts->links() }}
@endsection
