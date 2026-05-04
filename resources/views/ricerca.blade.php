@extends('layouts.app')
@section('title', ($query ? 'Ricerca: '.e($query) : 'Ricerca').' — '.config('laboratorio.name'))

@section('content')
<div class="container" style="padding-block:2rem;">
  <div class="page-layout">
    <section>

      {{-- Header --}}
      <div style="margin-bottom:1.5rem;">
        <hr style="border:none;border-top:3px solid var(--color-ink);margin:0 0 .5rem;">
        <h1 style="font-family:var(--font-display);font-size:1.6rem;font-weight:900;">
          @if($query)
            Risultati per: <em style="color:var(--color-accent);">{{ $query }}</em>
          @else
            Ricerca avanzata
          @endif
        </h1>
        @if($results instanceof \Illuminate\Pagination\LengthAwarePaginator)
          <p style="font-family:var(--font-ui);font-size:.85rem;color:var(--color-ink-muted);margin-top:.35rem;">
            {{ $results->total() }} articoli trovati
          </p>
        @endif
      </div>

      {{-- Form ricerca con filtri avanzati --}}
      <form method="GET" action="{{ route('ricerca') }}"
            style="margin-bottom:1.5rem;">
        <div style="display:flex;gap:.5rem;margin-bottom:.5rem;">
          <input type="text" name="q" value="{{ $query }}"
                 placeholder="Cerca articoli, scoperte, tecnologie…"
                 style="flex:1;font-family:var(--font-ui);font-size:.95rem;
                        padding:.65rem .9rem;border:2px solid var(--color-border);
                        border-radius:var(--radius);background:var(--color-white);
                        color:var(--color-ink);outline:none;"
                 autocomplete="off">
          <button type="submit"
                  style="background:var(--color-ink);color:var(--color-white);
                         border:none;padding:.65rem 1.25rem;border-radius:var(--radius);
                         font-family:var(--font-ui);font-size:.85rem;font-weight:700;cursor:pointer;">
            Cerca
          </button>
        </div>

        {{-- Filtri avanzati --}}
        <details {{ ($category || $authorId || $from || $to) ? 'open' : '' }}
                 style="border:1px solid var(--color-border);border-radius:var(--radius);
                        overflow:hidden;">
          <summary style="font-family:var(--font-ui);font-size:.78rem;font-weight:600;
                          color:var(--color-ink-muted);cursor:pointer;padding:.6rem 1rem;
                          background:var(--color-paper-warm);list-style:none;
                          display:flex;align-items:center;gap:.4rem;">
            ▸ Filtri avanzati
            @if($category || $authorId || $from || $to)
              <span style="background:var(--color-accent);color:#fff;font-size:.6rem;
                           padding:.1rem .4rem;border-radius:10px;margin-left:.2rem;">attivi</span>
            @endif
          </summary>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));
                      gap:.75rem;padding:.85rem;background:var(--color-white);">
            <div>
              <label style="font-family:var(--font-ui);font-size:.68rem;font-weight:700;
                            text-transform:uppercase;letter-spacing:.06em;
                            color:var(--color-ink-muted);display:block;margin-bottom:.25rem;">
                Categoria
              </label>
              <select name="categoria"
                      style="width:100%;font-family:var(--font-ui);font-size:.82rem;
                             padding:.4rem .5rem;border:1px solid var(--color-border);
                             border-radius:4px;background:var(--color-white);color:var(--color-ink);">
                <option value="">Tutte</option>
                @foreach($categories as $val => $label)
                  <option value="{{ $val }}" {{ $category === $val ? 'selected' : '' }}>
                    {{ $label }}
                  </option>
                @endforeach
              </select>
            </div>
            <div>
              <label style="font-family:var(--font-ui);font-size:.68rem;font-weight:700;
                            text-transform:uppercase;letter-spacing:.06em;
                            color:var(--color-ink-muted);display:block;margin-bottom:.25rem;">
                Autore
              </label>
              <select name="autore"
                      style="width:100%;font-family:var(--font-ui);font-size:.82rem;
                             padding:.4rem .5rem;border:1px solid var(--color-border);
                             border-radius:4px;background:var(--color-white);color:var(--color-ink);">
                <option value="">Tutti</option>
                @foreach($authors as $au)
                  <option value="{{ $au->id }}"
                          {{ (string)$authorId === (string)$au->id ? 'selected' : '' }}>
                    {{ $au->name }}
                  </option>
                @endforeach
              </select>
            </div>
            <div>
              <label style="font-family:var(--font-ui);font-size:.68rem;font-weight:700;
                            text-transform:uppercase;letter-spacing:.06em;
                            color:var(--color-ink-muted);display:block;margin-bottom:.25rem;">
                Dal
              </label>
              <input type="date" name="da" value="{{ $from }}"
                     style="width:100%;font-family:var(--font-ui);font-size:.82rem;
                            padding:.4rem .5rem;border:1px solid var(--color-border);
                            border-radius:4px;background:var(--color-white);color:var(--color-ink);">
            </div>
            <div>
              <label style="font-family:var(--font-ui);font-size:.68rem;font-weight:700;
                            text-transform:uppercase;letter-spacing:.06em;
                            color:var(--color-ink-muted);display:block;margin-bottom:.25rem;">
                Al
              </label>
              <input type="date" name="a" value="{{ $to }}"
                     style="width:100%;font-family:var(--font-ui);font-size:.82rem;
                            padding:.4rem .5rem;border:1px solid var(--color-border);
                            border-radius:4px;background:var(--color-white);color:var(--color-ink);">
            </div>
            <div style="display:flex;align-items:flex-end;gap:.5rem;">
              <button type="submit"
                      style="background:var(--color-ink);color:var(--color-white);border:none;
                             padding:.42rem 1rem;border-radius:4px;font-family:var(--font-ui);
                             font-size:.78rem;font-weight:700;cursor:pointer;">
                Applica
              </button>
              <a href="{{ route('ricerca') }}"
                 style="background:var(--color-paper-warm);color:var(--color-ink-muted);
                        border:1px solid var(--color-border);padding:.42rem .85rem;
                        border-radius:4px;font-family:var(--font-ui);font-size:.78rem;
                        text-decoration:none;font-weight:600;">
                Reset
              </a>
            </div>
          </div>
        </details>
      </form>

      {{-- Risultati --}}
      @if($results instanceof \Illuminate\Contracts\Pagination\Paginator && $results->count() > 0)

        @foreach($results as $article)
        <a href="{{ route('articolo', $article->slug) }}"
           class="card-h" style="margin-bottom:.9rem;display:grid;">
          <img class="card-h__img"
               src="{{ asset('assets/img/'.($article->cover_image ?? 'placeholder-1.svg')) }}"
               alt="{{ $article->title }}" loading="lazy">
          <div class="card-h__body">
            <div>
              <div style="display:flex;gap:.4rem;align-items:center;margin-bottom:.3rem;flex-wrap:wrap;">
                <span class="badge badge--{{ $article->category }}" style="font-size:.62rem;">
                  {{ config('laboratorio.categories.'.$article->category) }}
                </span>
                <span style="font-family:var(--font-ui);font-size:.68rem;color:var(--color-ink-muted);">
                  {{ $article->author->name }}
                </span>
              </div>
              <div class="card-h__title">{{ $article->title }}</div>
              @if($article->excerpt)
              <p style="font-size:.85rem;color:var(--color-ink-muted);margin-top:.25rem;">
                {{ Str::limit($article->excerpt, 120) }}
              </p>
              @endif
            </div>
            <div class="card__meta">
              <time datetime="{{ $article->published_at->toDateString() }}">
                {{ $article->published_at->locale('it')->isoFormat('D MMM YYYY') }}
              </time>
              <span class="dot">·</span>
              <span>{{ $article->read_minutes }} min</span>
            </div>
          </div>
        </a>
        @endforeach

        {{-- Paginazione --}}
        @if($results->hasPages())
          <div style="margin-top:1.5rem;">
            {{ $results->links('components.pagination') }}
          </div>
        @endif

      @elseif(request()->hasAny(['q','categoria','autore','da','a']))
        <div style="text-align:center;padding:3rem 1rem;color:var(--color-ink-muted);
                    font-family:var(--font-ui);">
          <p style="font-size:2rem;margin:0 0 .5rem;">🔍</p>
          <p style="font-size:.95rem;">Nessun risultato trovato per questi criteri.</p>
          <a href="{{ route('ricerca') }}"
             style="font-size:.82rem;color:var(--color-accent);text-decoration:none;">
            Rimuovi i filtri
          </a>
        </div>
      @endif

    </section>

    {{-- Sidebar --}}
    <aside>
      @include('components.sidebar')
    </aside>
  </div>
</div>
@endsection
