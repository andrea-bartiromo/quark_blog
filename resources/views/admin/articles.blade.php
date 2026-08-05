@extends('layouts.admin')
@section('title','Articoli')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Articoli</h1>
  <a href="{{ route('admin.articles.create') }}" class="btn btn--primary">+ Nuovo articolo</a>
    <a href="{{ route('admin.articles.quick-draft') }}"
       class="btn btn--secondary" style="font-size:.78rem;">⚡ Bozza rapida</a>
</div>

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

@endsection