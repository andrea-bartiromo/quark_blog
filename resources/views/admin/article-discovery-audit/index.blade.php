@extends('layouts.admin')
@section('title','Discovery articoli')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Discovery articoli</h1>
</div>

<p style="color:var(--admin-muted);font-size:.85rem;margin-bottom:1rem;max-width:78ch;">
  Fotografia read-only dei percorsi pubblici reali che possono portare a ciascun articolo:
  archivio, autore, categorie, Percorsi e link editoriali in entrata. L'audit viene eseguito
  soltanto aprendo questa pagina; per il dettaglio dei link rotti o isolati usa
  <a href="{{ route('admin.internal-link-audit') }}">Link interni</a>.
</p>

<dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem;">
  @foreach(['ZERO_PATHS' => 'Zero percorsi', 'ONE_PATH' => 'Un percorso', 'MULTIPLE_PATHS' => 'Percorsi multipli'] as $class => $label)
    <div class="admin-card" style="margin:0;">
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--admin-muted);">{{ $label }}</dt>
      <dd style="margin:.2rem 0 0;font-weight:600;font-size:1.4rem;">{{ number_format($counts[$class]) }}</dd>
    </div>
  @endforeach
</dl>

<p style="font-size:.8rem;color:var(--admin-muted);margin-bottom:.75rem;">
  Ordinati dal minor numero di percorsi di discovery. L'archivio Notizie conta come percorso reale,
  quindi ZERO_PATHS è normalmente zero per ogni articolo pubblico correttamente indicizzato.
</p>

@if($rows->isEmpty())
  <div class="articles-empty-state">
    <p class="articles-empty-state__icon" aria-hidden="true">🧭</p>
    <p>Nessun articolo pubblico da analizzare.</p>
  </div>
@else
  <div style="overflow-x:auto;">
    <table class="admin-table">
      <thead>
        <tr>
          <th scope="col">Articolo</th>
          <th scope="col">Classe</th>
          <th scope="col">Percorsi</th>
          <th scope="col">Percorsi attivi</th>
          <th scope="col">Link in entrata</th>
          <th scope="col">Rischi</th>
        </tr>
      </thead>
      <tbody>
        @foreach($rows as $row)
          <tr>
            <td><a href="{{ route('admin.articles.edit', $row['article_id']) }}">{{ Str::limit($row['title'], 55) }}</a></td>
            <td><code>{{ $row['discovery_class'] }}</code></td>
            <td>{{ number_format($row['discovery_path_count']) }}</td>
            <td>{{ number_format($row['active_path_count']) }}</td>
            <td>{{ number_format($row['body_incoming_count']) }}</td>
            <td>{{ $row['risks'] === [] ? '—' : implode(', ', $row['risks']) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  @if($truncated)
    <p style="font-size:.78rem;color:var(--admin-muted);margin-top:.75rem;">
      Mostrate le prime {{ $rows->count() }} righe più deboli su {{ number_format($total) }} articoli analizzati.
    </p>
  @endif
@endif

@endsection
