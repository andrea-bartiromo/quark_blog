@extends('layouts.admin')
@section('title','Bozze Social')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Bozze Social</h1>
  <a href="{{ route('admin.social-drafts.create') }}" class="btn btn--primary">+ Nuova bozza</a>
  <a href="{{ route('admin.social-distribution') }}" class="btn btn--secondary" style="font-size:.78rem;">Generatore link UTM</a>
</div>

<div style="background:#f0fdfa;border:1px solid #99f6e4;border-radius:8px;
            padding:.85rem 1.1rem;margin-bottom:1.25rem;font-size:.82rem;color:#0f766e;">
  ℹ️ Workspace interno per preparare, revisionare, approvare e programmare
  bozze Social per Facebook e LinkedIn. <strong>Nessun post viene mai
  pubblicato da qui</strong>: questa V1 non chiama alcuna API social, non
  usa token e non invia nulla.
</div>

<form method="GET" action="{{ route('admin.social-drafts.index') }}" class="articles-toolbar" role="search" aria-label="Filtri bozze Social">
  <div class="articles-toolbar__search-row">
    <div class="articles-toolbar__field articles-toolbar__field--search">
      <label class="form-label" for="drafts-search">Cerca articolo</label>
      <input type="search" id="drafts-search" name="q" value="{{ $search }}" maxlength="150"
             class="form-input" placeholder="Titolo articolo…" aria-label="Cerca per titolo articolo">
    </div>

    <div class="articles-toolbar__field">
      <label class="form-label" for="drafts-channel">Canale</label>
      <select id="drafts-channel" name="channel" class="form-select" aria-label="Filtra per canale">
        <option value="">Tutti i canali</option>
        @foreach($channelOptions as $value => $label)
          <option value="{{ $value }}" @selected($channel === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>

    <div class="articles-toolbar__field">
      <label class="form-label" for="drafts-status">Stato</label>
      <select id="drafts-status" name="status" class="form-select" aria-label="Filtra per stato">
        <option value="">Tutti gli stati</option>
        <option value="draft" @selected($status === 'draft')>Bozza</option>
        <option value="reviewed" @selected($status === 'reviewed')>Revisionato</option>
        <option value="approved" @selected($status === 'approved')>Approvato</option>
        <option value="scheduled" @selected($status === 'scheduled')>Programmato</option>
        <option value="published" @selected($status === 'published')>Pubblicato (storico)</option>
        <option value="failed" @selected($status === 'failed')>Fallito (storico)</option>
      </select>
    </div>

    <div class="articles-toolbar__field">
      <label class="form-label" for="drafts-from">Programmato dal</label>
      <input type="date" id="drafts-from" name="from" value="{{ $from }}" class="form-input">
    </div>

    <div class="articles-toolbar__field">
      <label class="form-label" for="drafts-to">al</label>
      <input type="date" id="drafts-to" name="to" value="{{ $to }}" class="form-input">
    </div>

    <button type="submit" class="btn btn--primary">Filtra</button>
    @if($hasActiveFilters)
      <a href="{{ route('admin.social-drafts.index') }}" class="btn btn--secondary">Azzera filtri</a>
    @endif
  </div>

  <div class="articles-toolbar__footer">
    <div class="articles-toolbar__count" role="status">
      {{ $drafts->total() }} {{ $drafts->total() === 1 ? 'bozza' : 'bozze' }}
    </div>
  </div>
</form>

@if($drafts->isEmpty())
  <div class="articles-empty-state">
    <p class="articles-empty-state__icon" aria-hidden="true">📝</p>
    <p>Nessuna bozza Social corrisponde ai filtri.</p>
    @if($hasActiveFilters)
      <p class="articles-empty-state__hint"><a href="{{ route('admin.social-drafts.index') }}">Azzera filtri</a></p>
    @else
      <p class="articles-empty-state__hint"><a href="{{ route('admin.social-drafts.create') }}">Crea la prima bozza</a></p>
    @endif
  </div>
@else
<div class="admin-table-wrap">
  <table class="admin-table admin-table--compact">
    <thead>
      <tr>
        <th>Articolo</th>
        <th>Canale</th>
        <th>Stato</th>
        <th>Programmato (Europe/Rome)</th>
        <th>Aggiornato</th>
        <th>Azioni</th>
      </tr>
    </thead>
    <tbody>
      @foreach($drafts as $draft)
        @php
          $collisionKey = $draft->channel.'|'.optional($draft->scheduled_at)->toDateTimeString();
          $inCollision = $draft->status === 'scheduled' && in_array($collisionKey, $collidingKeys, true);
        @endphp
        <tr>
          <td>{{ $draft->article->title ?? 'Articolo eliminato' }}</td>
          <td>{{ $channelOptions[$draft->channel] ?? $draft->channel }}</td>
          <td>
            <span class="badge badge--{{ $draft->status }}">{{ ucfirst($draft->status) }}</span>
            @if($inCollision)
              <span class="badge" style="background:#fee2e2;color:#991b1b;" title="Un'altra bozza è programmata per lo stesso canale nello stesso istante">⚠ Collisione</span>
            @endif
          </td>
          <td>
            @if($draft->scheduled_at)
              <time datetime="{{ $draft->scheduled_at->toIso8601String() }}">
                {{ $draft->scheduled_at->clone()->timezone('Europe/Rome')->locale('it')->isoFormat('D MMM YYYY, HH:mm') }}
              </time>
            @else
              —
            @endif
          </td>
          <td>{{ $draft->updated_at->diffForHumans() }}</td>
          <td><a href="{{ route('admin.social-drafts.show', $draft) }}" class="btn btn--secondary" style="font-size:.78rem;">Apri</a></td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

<div class="admin-pagination-wrap">
  {{ $drafts->links('components.pagination') }}
</div>
@endif

@endsection
