@extends('layouts.app')

@section('title', $author->name.' — Redattore — '.config('laboratorio.name'))
@section('description', 'Articoli di '.$author->name.' su '.config('laboratorio.name').', rivista italiana di divulgazione scientifica.')

@section('content')
<div class="container" style="max-width:900px;margin:2rem auto;padding:0 1rem;">

  {{-- Box autore --}}
  <div style="display:flex;gap:2rem;align-items:flex-start;
              background:var(--color-white);border-radius:var(--radius);
              box-shadow:var(--shadow);padding:2rem;margin-bottom:2rem;
              flex-wrap:wrap;">

    {{-- Avatar --}}
    <div style="flex-shrink:0;">
      @if($author->avatar)
        <img src="{{ asset('storage/'.$author->avatar) }}"
             alt="{{ $author->name }}"
             style="width:100px;height:100px;border-radius:50%;object-fit:cover;
                    border:3px solid var(--color-border);">
      @else
        <div style="width:100px;height:100px;border-radius:50%;
                    background:var(--color-ink);display:flex;align-items:center;
                    justify-content:center;font-family:var(--font-display);
                    font-size:2.5rem;font-weight:700;color:var(--color-white);">
          {{ mb_substr($author->name, 0, 1) }}
        </div>
      @endif
    </div>

    {{-- Dati --}}
    <div style="flex:1;min-width:200px;">
      <div style="font-family:var(--font-ui);font-size:.72rem;font-weight:700;
                  text-transform:uppercase;letter-spacing:.1em;
                  color:var(--color-accent);margin-bottom:.3rem;">Redattore</div>
      <h1 style="font-family:var(--font-display);font-size:1.8rem;font-weight:900;
                 color:var(--color-ink);margin:0 0 .5rem;">{{ $author->name }}</h1>

      @if($author->bio)
        <p style="font-family:var(--font-body);font-size:.95rem;color:var(--color-ink-soft);
                  line-height:1.7;margin:0 0 1rem;">{{ $author->bio }}</p>
      @endif

      {{-- Social links --}}
      <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
        @if($author->twitter)
          <a href="https://twitter.com/{{ ltrim($author->twitter,'@') }}"
             target="_blank" rel="noopener"
             style="font-family:var(--font-ui);font-size:.78rem;color:var(--color-ink-muted);
                    text-decoration:none;display:flex;align-items:center;gap:.3rem;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
            {{ $author->twitter }}
          </a>
        @endif
        @if($author->email && $author->role === 'editor')
          <a href="mailto:{{ $author->email }}"
             style="font-family:var(--font-ui);font-size:.78rem;color:var(--color-ink-muted);
                    text-decoration:none;display:flex;align-items:center;gap:.3rem;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,12 2,6"/></svg>
            Contatta
          </a>
        @endif
      </div>
    </div>

    {{-- Statistiche --}}
    <div style="display:flex;flex-direction:column;gap:.75rem;text-align:center;flex-shrink:0;">
      <div style="background:var(--color-paper-warm);border-radius:var(--radius);padding:.75rem 1.25rem;">
        <div style="font-family:var(--font-display);font-size:2rem;font-weight:900;
                    color:var(--color-ink);line-height:1;">{{ $articles->total() }}</div>
        <div style="font-family:var(--font-ui);font-size:.65rem;font-weight:700;
                    text-transform:uppercase;letter-spacing:.08em;color:var(--color-ink-muted);">
                    articoli</div>
      </div>
      <div style="background:var(--color-paper-warm);border-radius:var(--radius);padding:.75rem 1.25rem;">
        <div style="font-family:var(--font-display);font-size:2rem;font-weight:900;
                    color:var(--color-accent);line-height:1;">
          {{ number_format($articles->sum('views'), 0, ',', '.') }}
        </div>
        <div style="font-family:var(--font-ui);font-size:.65rem;font-weight:700;
                    text-transform:uppercase;letter-spacing:.08em;color:var(--color-ink-muted);">
                    letture totali</div>
      </div>
    </div>
  </div>

  {{-- Lista articoli --}}
  <h2 style="font-family:var(--font-display);font-size:1.2rem;font-weight:700;
             color:var(--color-ink);margin:0 0 1rem;">
    Articoli pubblicati
  </h2>

  <div style="display:flex;flex-direction:column;gap:1rem;">
    @forelse($articles as $article)
    <article style="display:flex;gap:1rem;background:var(--color-white);
                    border-radius:var(--radius);box-shadow:var(--shadow);
                    padding:1.1rem;align-items:flex-start;">

      {{-- Thumbnail --}}
      <a href="{{ route('articolo', $article->slug) }}" style="flex-shrink:0;">
        <img src="{{ asset('assets/img/'.$article->cover_image) }}"
             alt="{{ $article->title }}"
             style="width:90px;height:60px;object-fit:cover;border-radius:4px;">
      </a>

      {{-- Contenuto --}}
      <div style="flex:1;min-width:0;">
        <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.3rem;flex-wrap:wrap;">
          <span class="badge badge--{{ $article->category }}" style="font-size:.62rem;">
            {{ config('laboratorio.categories.'.$article->category) }}
          </span>
          <time style="font-family:var(--font-ui);font-size:.68rem;color:var(--color-ink-muted);">
            {{ $article->published_at->format('d M Y') }}
          </time>
          <span style="font-family:var(--font-ui);font-size:.68rem;color:var(--color-ink-muted);">
            · {{ $article->read_minutes }} min
          </span>
        </div>
        <h3 style="margin:0 0 .3rem;">
          <a href="{{ route('articolo', $article->slug) }}"
             style="font-family:var(--font-display);font-size:.95rem;font-weight:700;
                    color:var(--color-ink);text-decoration:none;line-height:1.3;">
            {{ $article->title }}
          </a>
        </h3>
        <p style="font-family:var(--font-body);font-size:.82rem;color:var(--color-ink-soft);
                  margin:0;line-height:1.5;">
          {{ Str::limit($article->excerpt, 120) }}
        </p>
      </div>
    </article>
    @empty
      <p style="font-family:var(--font-body);color:var(--color-ink-muted);">
        Nessun articolo pubblicato ancora.
      </p>
    @endforelse
  </div>

  {{-- Paginazione --}}
  @if($articles->hasPages())
    <div style="margin-top:1.5rem;">
      {{ $articles->links('components.pagination') }}
    </div>
  @endif

</div>
@endsection
