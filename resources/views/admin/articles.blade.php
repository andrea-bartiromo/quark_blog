@extends('layouts.admin')
@section('title','Articoli')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Articoli</h1>
  <a href="{{ route('admin.articles.create') }}" class="btn btn--primary">+ Nuovo articolo</a>
    <a href="{{ route('admin.articles.quick-draft') }}"
       class="btn btn--secondary" style="font-size:.78rem;">⚡ Bozza rapida</a>
    <a href="{{ route('admin.articles.calendar') }}"
       class="btn btn--secondary" style="font-size:.78rem;">📅 Calendario</a>
</div>

<form method="GET" action="{{ route('admin.articles') }}" class="articles-toolbar" role="search" aria-label="Ricerca e filtri negli articoli">
  <div class="articles-toolbar__search-row">
    <div class="articles-toolbar__field articles-toolbar__field--search">
      <label class="form-label" for="articles-search">Cerca</label>
      <input type="search" id="articles-search" name="q" value="{{ $search }}" maxlength="150"
             class="form-input" placeholder="Titolo, sommario o testo dell'articolo…"
             aria-label="Cerca per titolo, sommario o testo dell'articolo">
    </div>

    <div class="articles-toolbar__field">
      <label class="form-label" for="articles-status">Stato</label>
      <select id="articles-status" name="status" class="form-select" aria-label="Filtra per stato editoriale">
        <option value="">Tutti gli stati</option>
        @foreach($statusOptions as $value => $label)
          <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>

    <div class="articles-toolbar__field">
      <label class="form-label" for="articles-category">Categoria</label>
      <select id="articles-category" name="category" class="form-select" aria-label="Filtra per categoria">
        <option value="">Tutte le categorie</option>
        @foreach($categoryOptions as $slug => $label)
          <option value="{{ $slug }}" @selected($category === $slug)>{{ $label }}</option>
        @endforeach
      </select>
    </div>

    @if(count($authorOptions) > 1)
    <div class="articles-toolbar__field">
      <label class="form-label" for="articles-author">Autore</label>
      <select id="articles-author" name="author" class="form-select" aria-label="Filtra per autore">
        <option value="">Tutti gli autori</option>
        @foreach($authorOptions as $id => $name)
          <option value="{{ $id }}" @selected($authorId === $id)>{{ $name }}</option>
        @endforeach
      </select>
    </div>
    @endif

    <button type="submit" class="btn btn--primary">Filtra</button>
    @if($hasActiveFilters)
      <a href="{{ route('admin.articles') }}" class="btn btn--secondary">Azzera filtri</a>
    @endif
  </div>

  <div class="articles-toolbar__footer">
    <div class="articles-toolbar__count" role="status">
      {{ $articles->total() }} {{ $articles->total() === 1 ? 'risultato' : 'risultati' }}
    </div>

    @if($hasActiveFilters)
    <div class="articles-filter-badges" aria-label="Filtri attivi">
      @if($search !== '')<span class="badge badge--filter">Ricerca: “{{ $search }}”</span>@endif
      @if($status !== null)<span class="badge badge--filter">Stato: {{ $statusOptions[$status] }}</span>@endif
      @if($category !== null)<span class="badge badge--filter">Categoria: {{ $categoryOptions[$category] ?? $category }}</span>@endif
      @if($authorId !== null)<span class="badge badge--filter">Autore: {{ $authorOptions[$authorId] ?? $authorId }}</span>@endif
    </div>
    @endif
  </div>
</form>

@if($articles->isEmpty())
  @if($articlesExistAtAll)
  <div class="articles-empty-state">
    <p class="articles-empty-state__icon" aria-hidden="true">🔍</p>
    <p>Nessun articolo corrisponde ai filtri selezionati.</p>
    <p class="articles-empty-state__hint">
      <a href="{{ route('admin.articles') }}">Azzera filtri</a>
    </p>
  </div>
  @else
  <div class="articles-empty-state">
    <p class="articles-empty-state__icon" aria-hidden="true">📝</p>
    <p>Non c'è ancora nessun articolo.</p>
    <p class="articles-empty-state__hint">
      <a href="{{ route('admin.articles.create') }}">Crea il primo articolo</a>
    </p>
  </div>
  @endif
@else
<div class="admin-table-wrap">
  <table class="admin-table admin-table--compact articles-table">
    <thead>
      <tr>
        <th class="sr-only">Anteprima</th>
        <th>Titolo</th>
        <th class="col-category">Categoria</th>
        <th class="col-author">Autore</th>
        <th>Stato</th>
        <th class="col-views">Views</th>
        <th class="col-date">Data</th>
        <th>Azioni</th>
      </tr>
    </thead>
    <tbody>
      @foreach($articles as $article)
      <tr>
        <td>
          <img class="article-thumb"
               src="{{ asset('assets/img/'.($article->cover_image ?? 'placeholder-1.jpg')) }}"
               alt="">
        </td>
        <td class="article-title-cell" title="{{ $article->title }}">{{ Str::limit($article->title,55) }}</td>
        <td class="col-category">{{ $article->category }}</td>
        <td class="col-author">{{ $article->author->name }}</td>
        <td>
          @if($article->isScheduled() && $article->published_at)
            <span class="status status--scheduled" title="Pubblicazione programmata">
              <span class="status__row">
                <svg class="status__icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                  <circle cx="10" cy="10" r="7.25" stroke="currentColor" stroke-width="1.5"/>
                  <path d="M10 6v4l2.5 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Programmato
              </span>
              <time datetime="{{ $article->published_at->toIso8601String() }}">{{ $article->publishedAtForEditors()->translatedFormat('d M, H:i') }}</time>
            </span>
            <span class="status status--links-count" title="{{ $article->article_links_count }} {{ $article->article_links_count === 1 ? 'collegamento ad altro articolo Kairus nel testo' : 'collegamenti ad altri articoli Kairus nel testo' }}">
              🔗 {{ $article->article_links_count }} {{ $article->article_links_count === 1 ? 'articolo' : 'articoli' }}
            </span>
          @else
            <span class="status status--{{ $article->status }}">{{ \App\Models\Article::statusOptions()[$article->status] ?? $article->status }}</span>
          @endif
        </td>
        <td class="col-views">{{ number_format($article->views) }}</td>
        <td class="col-date">{{ $article->created_at->format('d/m/Y') }}</td>
        <td>
          <div class="actions">
            <a href="{{ route('admin.articles.edit', $article) }}" class="action-btn">Modifica</a>
            <a href="{{ route('articolo', $article->slug) }}" target="_blank" class="action-btn">Vedi</a>
            <form method="POST" action="{{ route('admin.articles.destroy', $article) }}"
                  onsubmit="return confirm('Eliminare questo articolo?')" style="display:inline;">
              @csrf @method('DELETE')
              <button type="submit" class="action-btn action-btn--danger">Elimina</button>
            </form>
          </div>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>

{{ $articles->links('components.pagination') }}
@endif

@endsection
