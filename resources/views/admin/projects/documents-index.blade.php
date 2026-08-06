@extends('layouts.admin')
@section('title', 'Documenti — Progettazione')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Documenti</h1>
</div>

@if($documents->isEmpty())
  <div class="admin-card" style="text-align:center;padding:3rem;color:#6b7280;">
    Nessun documento trovato.
  </div>
@else
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Titolo / progetto</th>
          <th>Tipo</th>
          <th>Stato</th>
          <th>Versione</th>
          <th>Aggiornato</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        @foreach($documents as $document)
        <tr>
          <td>
            <div style="font-weight:700;">{{ $document->title }}</div>
            <div style="font-size:.72rem;color:#6b7280;">{{ $document->project->title }}</div>
          </td>
          <td>{{ \App\Models\ProjectDocument::typeOptions()[$document->type] ?? $document->type }}</td>
          <td><span class="status status--doc-{{ $document->status }}">{{ \App\Models\ProjectDocument::statusOptions()[$document->status] ?? $document->status }}</span></td>
          <td>v{{ $document->version }}</td>
          <td>{{ $document->updated_at->format('d/m/Y H:i') }}</td>
          <td><a href="{{ route('admin.progettazione.projects.documents.edit', [$document->project, $document]) }}" class="action-btn">Apri</a></td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  {{ $documents->links() }}
@endif

@endsection
