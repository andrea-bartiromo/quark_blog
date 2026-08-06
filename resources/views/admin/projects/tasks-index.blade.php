@extends('layouts.admin')
@section('title', 'Attività progetti — Progettazione')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Attività progetti</h1>
  <a href="{{ route('admin.progettazione.tasks.create-pick-project') }}" class="btn btn--primary">+ Nuova attività</a>
</div>

<form method="GET" style="display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:1.25rem;">
  <select name="status" class="form-input" style="max-width:220px;" onchange="this.form.submit()">
    <option value="">Tutti gli stati</option>
    @foreach($statusOptions as $value => $label)
      <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
    @endforeach
  </select>
  <select name="type" class="form-input" style="max-width:220px;" onchange="this.form.submit()">
    <option value="">Tutti i tipi</option>
    @foreach($typeOptions as $value => $label)
      <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
    @endforeach
  </select>
</form>

@if($tasks->isEmpty())
  <div class="admin-card project-empty-state">
    <div class="project-empty-state__icon">✔️</div>
    <p class="project-empty-state__text">Nessuna attività trovata. <strong>Crea la prima attività</strong> per iniziare a pianificare il lavoro di un progetto.</p>
    <a href="{{ route('admin.progettazione.tasks.create-pick-project') }}" class="btn btn--primary">+ Nuova attività</a>
  </div>
@else
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Titolo / progetto</th>
          <th>Tipo</th>
          <th>Stato</th>
          <th>Responsabile</th>
          <th>Data prevista</th>
          <th>Articolo</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        @include('admin.projects._task-list-rows', ['tasks' => $tasks, 'showProject' => true])
      </tbody>
    </table>
  </div>

  {{ $tasks->links() }}
@endif

@endsection
