@extends('layouts.admin')
@section('title', ($document->exists ? 'Modifica' : 'Nuovo').' documento — '.$project->title)
@section('content')

<div class="admin-topbar">
  <div>
    <a href="{{ route('admin.progettazione.projects.show', [$project, 'tab' => 'documents']) }}" style="font-size:.8rem;color:#6b7280;text-decoration:none;">← {{ $project->title }}</a>
    <h1 class="admin-page-title" style="margin-top:.25rem;">{{ $document->exists ? 'Modifica documento' : 'Nuovo documento' }}</h1>
  </div>
</div>

<div class="admin-card" style="max-width:760px;">
  <form method="POST"
        action="{{ $document->exists ? route('admin.progettazione.projects.documents.update', [$project, $document]) : route('admin.progettazione.projects.documents.store', $project) }}">
    @csrf
    @if($document->exists) @method('PUT') @endif

    <div class="form-group">
      <label class="form-label" for="title">Titolo *</label>
      <input class="form-input" type="text" id="title" name="title" required
             value="{{ old('title', $document->title) }}">
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
      <div class="form-group">
        <label class="form-label" for="type">Tipo *</label>
        <select class="form-select" id="type" name="type" required>
          @foreach(\App\Models\ProjectDocument::typeOptions() as $value => $label)
            <option value="{{ $value }}" @selected(old('type', $document->type ?: 'note') === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="status">Stato *</label>
        <select class="form-select" id="status" name="status" required>
          @foreach(\App\Models\ProjectDocument::statusOptions() as $value => $label)
            <option value="{{ $value }}" @selected(old('status', $document->status ?: 'draft') === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="content">Contenuto (Markdown)</label>
      <textarea class="form-textarea" id="content" name="content" rows="10">{{ old('content', $document->content) }}</textarea>
    </div>

    <div class="form-group">
      <label class="form-label" for="media_id">Allegato dalla libreria Media</label>
      <select class="form-select" id="media_id" name="media_id">
        <option value="">Nessuno</option>
        @foreach($mediaOptions as $media)
          <option value="{{ $media->id }}" @selected((string) old('media_id', $document->media_id) === (string) $media->id)>{{ $media->filename }}</option>
        @endforeach
      </select>
    </div>

    @if($document->exists && $document->content)
      <details style="margin-bottom:1rem;">
        <summary style="cursor:pointer;font-size:.82rem;color:#0d9488;font-weight:600;">Anteprima</summary>
        <div style="margin-top:.75rem;padding:1rem;background:#fafafa;border-radius:8px;border:1px solid #f1f5f9;">
          {!! $document->renderedContent() !!}
        </div>
      </details>
    @endif

    <div style="display:flex;gap:.6rem;margin-top:1rem;">
      <button class="btn btn--primary" type="submit">{{ $document->exists ? 'Salva modifiche' : 'Crea documento' }}</button>
      <a href="{{ route('admin.progettazione.projects.show', [$project, 'tab' => 'documents']) }}" class="btn btn--secondary">Annulla</a>
    </div>
  </form>

  @if($document->exists)
    <form id="delete-document-form" method="POST" action="{{ route('admin.progettazione.projects.documents.destroy', [$project, $document]) }}"
          onsubmit="return confirm('Eliminare questo documento?')" style="display:inline;">
      @csrf @method('DELETE')
      <button type="button" class="btn btn--secondary" onclick="document.getElementById('delete-document-form').submit()" style="margin-left:.5rem;">Elimina</button>
    </form>
  @endif
</div>

@endsection
