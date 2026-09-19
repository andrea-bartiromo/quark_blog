@extends('layouts.admin')
@section('title', 'Command Center — Categorie')
@section('content')

<div class="admin-topbar">
  <div>
    <h1 class="admin-page-title">Command Center — Categorie</h1>
  </div>
  <a class="action-btn" href="{{ route('admin.categories') }}">Torna a Categorie</a>
</div>

<p style="font-size:.85rem;color:#6b7280;max-width:70ch;margin:0 0 1.25rem;">
  Riepilogo read-only per ciascuna categoria — stesso principio del
  <a href="{{ route('admin.editorial-operations') }}">Command Center operazioni editoriali</a>:
  nessun nuovo controllo, nessuna correzione automatica, solo audit già esistenti
  (checklist di attivazione, content health) raggruppati per categoria invece che
  in un unico aggregato sitewide.
</p>

<div style="display:grid;gap:1rem;">
  @foreach($snapshot as $row)
    <div class="admin-card">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;">
        <div>
          <h2 style="margin:0 0 .35rem;font-size:1rem;">{{ $row['name'] }}</h2>
          <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <span class="status {{ $row['is_active'] ? 'status--published' : 'status--draft' }}">{{ $row['is_active'] ? 'Attiva' : 'Disattiva' }}</span>
            <span class="status {{ $row['visibility_label'] === 'Pubblica' ? 'status--published' : 'status--draft' }}">{{ $row['visibility_label'] }}</span>
          </div>
        </div>
        <a class="action-btn" href="{{ route('admin.categories', ['modifica' => $row['category_id']]) }}">Modifica categoria</a>
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.75rem;margin-top:1rem;">
        <div>
          <div style="font-size:.72rem;color:#6b7280;text-transform:uppercase;">Articoli totali</div>
          <div style="font-size:1.15rem;font-weight:700;">{{ $row['total_article_count'] }}</div>
        </div>
        <div>
          <div style="font-size:.72rem;color:#6b7280;text-transform:uppercase;">Articoli pubblicati</div>
          <div style="font-size:1.15rem;font-weight:700;">{{ $row['published_article_count'] }}</div>
        </div>
        <div>
          <div style="font-size:.72rem;color:#6b7280;text-transform:uppercase;">Checklist attivazione</div>
          @if($row['readiness']['ready'])
            <span class="status status--published" title="Nessuna criticità rilevata.">Pronta</span>
          @else
            <span
                class="status status--draft"
                title="{{ collect($row['readiness']['findings'])->map(fn ($finding) => \App\Services\CategoryPublicationReadiness::label($finding))->join('; ') }}"
            >{{ count($row['readiness']['findings']) }} da verificare</span>
          @endif
        </div>
        <div>
          <div style="font-size:.72rem;color:#6b7280;text-transform:uppercase;">Content health</div>
          @if($row['content_health_warning_count'] > 0)
            <span class="status status--draft">{{ $row['content_health_warning_count'] }} criticità</span>
          @else
            <span class="status status--published">Nessuna criticità</span>
          @endif
        </div>
      </div>

      @if(count($row['articles_with_warnings']) > 0)
        <details style="margin-top:1rem;">
          <summary style="cursor:pointer;font-size:.85rem;color:#374151;">Articoli con criticità ({{ count($row['articles_with_warnings']) }})</summary>
          <ul style="margin:.6rem 0 0;padding-left:1.2rem;font-size:.82rem;">
            @foreach($row['articles_with_warnings'] as $article)
              <li>
                <a href="{{ route('admin.articles.edit', $article['article_id']) }}">{{ $article['title'] }}</a>
                — {{ $article['warning_count'] }} criticità
              </li>
            @endforeach
          </ul>
        </details>
      @endif
    </div>
  @endforeach
</div>
@endsection
