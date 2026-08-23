{{--
    Calendario articoli V1 — sola visualizzazione (pubblicati + programmati),
    nessun drag-and-drop: la data si continua a modificare dal form
    dell'articolo (già l'unico punto che applica le regole di validazione
    dello stato). Chiamato "Calendario articoli", non "Calendario
    editoriale", per non collidere con il concetto già esistente in
    App\Services\Editorial\* (piano editoriale dell'Area Progettazione,
    dominio diverso).
--}}
@extends('layouts.admin')
@section('title','Calendario articoli')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Calendario articoli</h1>
  <a href="{{ route('admin.articles') }}" class="btn btn--secondary">☰ Vista elenco filtrabile</a>
</div>

@php
  $filterParams = array_filter([
    'stato' => $status,
    'categoria' => $category,
    'autore' => $authorId,
  ], fn ($value) => $value !== null);
@endphp

<nav class="calendar-toolbar" aria-label="Navigazione calendario">
  <div class="calendar-toolbar__views" role="group" aria-label="Modalità di visualizzazione">
    @foreach(['month' => 'Mese', 'week' => 'Settimana', 'list' => 'Elenco', 'next4' => 'Prossime 4 settimane'] as $mode => $label)
      <a href="{{ route('admin.articles.calendar', ['vista' => $mode, 'data' => $anchor->format('Y-m-d'), ...$filterParams]) }}"
         class="calendar-toolbar__view-btn @if($viewMode === $mode) is-active @endif"
         @if($viewMode === $mode) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
  </div>

  <div class="calendar-toolbar__nav">
    @unless($viewMode === 'next4')
      <a href="{{ $prevUrl }}" class="btn btn--secondary" aria-label="Periodo precedente">‹ Precedente</a>
      <a href="{{ $todayUrl }}" class="btn btn--secondary">Oggi</a>
      <a href="{{ $nextUrl }}" class="btn btn--secondary" aria-label="Periodo successivo">Successivo ›</a>
    @endunless
    <strong class="calendar-toolbar__label">
      @if($viewMode === 'next4')
        {{ $rangeStart->translatedFormat('d M') }} – {{ $rangeEnd->translatedFormat('d M Y') }}
      @elseif($viewMode === 'week')
        {{ $rangeStart->translatedFormat('d M') }} – {{ $rangeEnd->translatedFormat('d M Y') }}
      @else
        {{ ucfirst($anchor->translatedFormat('F Y')) }}
      @endif
    </strong>
  </div>
</nav>

<form method="GET" action="{{ route('admin.articles.calendar') }}" class="articles-toolbar" style="margin:1rem 0;">
  <input type="hidden" name="vista" value="{{ $viewMode }}">
  <input type="hidden" name="data" value="{{ $anchor->format('Y-m-d') }}">
  <div class="articles-toolbar__field">
    <label class="form-label" for="stato">Stato</label>
    <select id="stato" name="stato" class="form-select" onchange="this.form.submit()">
      <option value="">Pubblicati e programmati</option>
      @foreach($statusOptions as $value => $label)
        @if(in_array($value, [\App\Models\Article::STATUS_PUBLISHED, \App\Models\Article::STATUS_SCHEDULED], true))
          <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
        @endif
      @endforeach
    </select>
  </div>
  <div class="articles-toolbar__field">
    <label class="form-label" for="categoria">Categoria</label>
    <select id="categoria" name="categoria" class="form-select" onchange="this.form.submit()">
      <option value="">Tutte le categorie</option>
      @foreach($categoryOptions as $slug => $label)
        <option value="{{ $slug }}" @selected($category === $slug)>{{ $label }}</option>
      @endforeach
    </select>
  </div>
  <div class="articles-toolbar__field">
    <label class="form-label" for="autore">Autore</label>
    <select id="autore" name="autore" class="form-select" onchange="this.form.submit()">
      <option value="">Tutti gli autori</option>
      @foreach($authorOptions as $id => $name)
        <option value="{{ $id }}" @selected($authorId === $id)>{{ $name }}</option>
      @endforeach
    </select>
  </div>
  @if($status !== null || $category !== null || $authorId !== null)
    <a href="{{ route('admin.articles.calendar', ['vista' => $viewMode, 'data' => $anchor->format('Y-m-d')]) }}" class="btn btn--secondary">Azzera filtri</a>
  @endif
</form>

<p style="color:var(--admin-muted);font-size:.85rem;margin-bottom:1rem;">
  In questo periodo: <strong>{{ $counts['published'] }}</strong> {{ $counts['published'] === 1 ? 'pubblicato' : 'pubblicati' }},
  <strong>{{ $counts['scheduled'] }}</strong> {{ $counts['scheduled'] === 1 ? 'programmato' : 'programmati' }}.
</p>

@php
  $statusIcon = fn (string $status) => $status === \App\Models\Article::STATUS_SCHEDULED ? '🕓' : '✅';
@endphp

@if($viewMode === 'list' || $viewMode === 'next4')

  @php $visibleDays = $days->filter(fn ($day) => $day['articles']->isNotEmpty()); @endphp

  @if($visibleDays->isEmpty())
    <div class="articles-empty-state">
      <p class="articles-empty-state__icon" aria-hidden="true">🗓️</p>
      @if($status !== null || $category !== null || $authorId !== null)
        <p>Nessun articolo trovato per questi filtri in questo periodo.</p>
        <p class="articles-empty-state__hint">
          <a href="{{ route('admin.articles.calendar', ['vista' => $viewMode, 'data' => $anchor->format('Y-m-d')]) }}">Azzera i filtri</a> per vedere tutto il periodo.
        </p>
      @elseif($viewMode === 'next4')
        <p>Nessun articolo pubblicato o programmato nelle prossime 4 settimane.</p>
      @else
        <p>Nessun articolo pubblicato o programmato in questo mese.</p>
      @endif
    </div>
  @else
    <div class="calendar-list">
      @foreach($visibleDays as $day)
        <section class="calendar-list__day">
          <h2 class="calendar-list__day-heading @if($day['isToday']) is-today @endif">
            {{ ucfirst($day['date']->translatedFormat('l j F Y')) }}
            @if($day['isToday'])<span class="badge badge--filter">Oggi</span>@endif
          </h2>
          <ul class="calendar-list__items">
            @foreach($day['articles'] as $article)
              <li class="calendar-list__item">
                <span class="calendar-list__time">{{ $article->publishedAtForEditors()->format('H:i') }}</span>
                <span class="status status--{{ $article->status }}">{{ $statusIcon($article->status) }} {{ $statusOptions[$article->status] ?? $article->status }}</span>
                <a href="{{ route('admin.articles.edit', $article) }}" class="calendar-list__title">{{ $article->title }}</a>
                <span class="calendar-list__author">{{ $article->author->name ?? '—' }}</span>
              </li>
            @endforeach
          </ul>
        </section>
      @endforeach
    </div>
  @endif

@elseif($viewMode === 'week')

  <div class="calendar-week" role="table" aria-label="Articoli della settimana">
    @foreach($days as $day)
      <div class="calendar-week__day @if($day['isToday']) is-today @endif" role="row">
        <div class="calendar-week__day-heading" role="columnheader">
          {{ ucfirst($day['date']->translatedFormat('l j')) }}
          @if($day['isToday'])<span class="badge badge--filter">Oggi</span>@endif
        </div>
        @if($day['articles']->isEmpty())
          <p class="calendar-week__empty">Nessun articolo.</p>
        @else
          <ul class="calendar-week__items">
            @foreach($day['articles'] as $article)
              <li>
                <span class="calendar-list__time">{{ $article->publishedAtForEditors()->format('H:i') }}</span>
                <span class="status status--{{ $article->status }}">{{ $statusIcon($article->status) }}</span>
                <a href="{{ route('admin.articles.edit', $article) }}">{{ $article->title }}</a>
              </li>
            @endforeach
          </ul>
        @endif
      </div>
    @endforeach
  </div>

@else{{-- month --}}

  @php
    $weeks = $days->chunk(7);
    $weekdayLabels = collect($days)->take(7)->map(fn ($day) => ucfirst($day['date']->translatedFormat('D')));
    $maxChipsPerDay = 3;
  @endphp

  <table class="calendar-month" aria-label="Calendario del mese">
    <caption class="sr-only">{{ ucfirst($anchor->translatedFormat('F Y')) }}</caption>
    <thead>
      <tr>
        @foreach($weekdayLabels as $label)
          <th scope="col">{{ $label }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach($weeks as $week)
        <tr>
          @foreach($week as $day)
            <td class="calendar-month__cell @unless($day['inFocusedMonth']) is-outside @endunless @if($day['isToday']) is-today @endif">
              <div class="calendar-month__date">{{ $day['date']->day }}</div>
              @if($day['articles']->isNotEmpty())
                <ul class="calendar-month__chips">
                  @foreach($day['articles']->take($maxChipsPerDay) as $article)
                    <li>
                      <a href="{{ route('admin.articles.edit', $article) }}"
                         class="calendar-month__chip calendar-month__chip--{{ $article->status }}"
                         title="{{ $article->publishedAtForEditors()->format('H:i') }} — {{ $article->title }}">
                        {{ $statusIcon($article->status) }} {{ Str::limit($article->title, 28) }}
                      </a>
                    </li>
                  @endforeach
                </ul>
                @if($day['articles']->count() > $maxChipsPerDay)
                  <a class="calendar-month__more"
                     href="{{ route('admin.articles.calendar', ['vista' => 'list', 'data' => $day['date']->format('Y-m-d')]) }}">
                    +{{ $day['articles']->count() - $maxChipsPerDay }} altri
                  </a>
                @endif
              @endif
            </td>
          @endforeach
        </tr>
      @endforeach
    </tbody>
  </table>

@endif

@endsection
