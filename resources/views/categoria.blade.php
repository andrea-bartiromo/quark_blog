@extends('layouts.app')
@section('title', $categoryLabel.' — '.config('laboratorio.name'))
@section('description', 'Tutti gli articoli di Quark su '.$categoryLabel.': scienza semplice e curiosa.')

@section('content')
<div class="container" style="padding-block:2rem;">

  @if($categoryImage)
  <section style="position:relative;border-radius:28px;overflow:hidden;margin-bottom:2rem;background:#111827;min-height:340px;display:flex;align-items:flex-end;">

    <img src="{{ asset('assets/img/categories/'.$categoryImage) }}"
         alt="{{ $categoryLabel }}"
         style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.78;"
         loading="eager">

    <div style="position:absolute;inset:0;background:linear-gradient(to top, rgba(0,0,0,.82), rgba(0,0,0,.15));"></div>

    <div style="position:relative;z-index:2;padding:2rem;max-width:760px;color:white;">
      <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.8rem;">
        <span class="badge badge--{{ $category }}" style="font-size:.72rem;">
          {{ $categoryLabel }}
        </span>

        <span style="font-size:.78rem;opacity:.82;">
          {{ $articles->total() }} articoli
        </span>
      </div>

      <h1 style="font-family:var(--font-display);font-size:clamp(2.2rem,4vw,4rem);line-height:1;font-weight:900;letter-spacing:-.04em;margin:0;">
        {{ $categoryLabel }}
      </h1>

      @if($categoryDescription)
      <p style="margin-top:1rem;font-size:1rem;line-height:1.7;color:rgba(255,255,255,.86);max-width:680px;">
        {{ $categoryDescription }}
      </p>
      @endif
    </div>
  </section>
  @else
  <div style="margin-bottom:1.5rem;">
    <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.35rem;">
      <span class="badge badge--{{ $category }}" style="font-size:.72rem;">{{ $categoryLabel }}</span>
    </div>

    <h1 style="font-family:var(--font-display);font-size:1.8rem;font-weight:900;color:var(--ink);letter-spacing:-.02em;">
      {{ $categoryLabel }}
    </h1>

    <p style="font-size:.9rem;color:var(--ink-muted);margin-top:.3rem;">
      {{ $articles->total() }} articoli
    </p>
  </div>
  @endif

  <div class="page-layout">
    <section>

      <div class="card-grid">
        @foreach($articles as $article)
        <a href="{{ route('articolo', $article->slug) }}" class="card" style="text-decoration:none;">
          <div class="card__img">
            <img src="{{ asset('assets/img/'.($article->cover_image ?? 'placeholder-1.svg')) }}"
                 alt="{{ $article->title }}" loading="lazy">
          </div>

          <div class="card__body">
            <div class="card__title">{{ $article->title }}</div>
            <div class="card__excerpt">{{ Str::limit($article->excerpt, 100) }}</div>

            <div class="card__footer">
              <div class="card__author">
                <div class="author-avatar">{{ mb_substr($article->author->name, 0, 2) }}</div>
                {{ Str::before($article->author->name, ' ') }}
              </div>

              <span class="card__read">{{ $article->read_minutes }} min</span>
            </div>
          </div>
        </a>
        @endforeach
      </div>

      @if($articles->hasPages())
        <div style="margin-top:2rem;">
          {{ $articles->links('components.pagination') }}
        </div>
      @endif
    </section>

    <aside>
      @include('components.sidebar')
    </aside>
  </div>
</div>
@endsection
