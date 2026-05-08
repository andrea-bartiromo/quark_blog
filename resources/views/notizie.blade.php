@extends('layouts.app')
@section('title', 'Tutti gli articoli — '.config('laboratorio.name'))
@section('description', 'Tutti gli articoli di Quark: scienza, tecnologia e innovazione spiegate in modo chiaro, visuale e moderno.')

@section('content')
<div class="public-shell">
  <div class="container container--wide">

    <section class="public-hero public-hero--light">
      <span class="public-hero__kicker">Quark Archive</span>
      <h1>Tutti gli articoli</h1>
      <p>Il meglio della divulgazione scientifica di Quark: IA, spazio, energia, ambiente, salute, tecnologia e società in un unico flusso editoriale.</p>
      <div class="public-hero__meta">
        <span>{{ $articles->total() }} articoli pubblicati</span>
        <span>·</span>
        <span>Archivio aggiornato in tempo reale</span>
      </div>
    </section>

    <div class="public-pill-row">
      <a href="{{ route('notizie') }}" class="active">Tutti</a>
      @foreach(\App\Models\Category::options() as $slug => $label)
        <a href="{{ route('categoria', $slug) }}">{{ $label }}</a>
      @endforeach
    </div>

    <div class="public-premium-layout">
      <section>
        <div class="public-section-head">
          <div>
            <span>Latest stories</span>
            <h2>Ultime pubblicazioni</h2>
          </div>
        </div>

        <div class="public-card-grid">
          @foreach($articles as $article)
          <a href="{{ route('articolo', $article->slug) }}" class="public-card">
            <div class="public-card__media">
              <img src="{{ asset('assets/img/'.($article->cover_image ?? 'placeholder-1.svg')) }}"
                   alt="{{ $article->title }}" loading="lazy">
              <span class="public-card__badge">
                {{ \App\Models\Category::options(false)[$article->category] ?? $article->category }}
              </span>
            </div>

            <div class="public-card__body">
              <h3>{{ $article->title }}</h3>
              <p>{{ Str::limit($article->excerpt, 118) }}</p>

              <div class="public-card__footer">
                <span>{{ Str::before($article->author->name, ' ') }}</span>
                <span>{{ $article->read_minutes }} min</span>
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
        <div class="public-sidebar-card">
          @include('components.sidebar')
        </div>
      </aside>
    </div>
  </div>
</div>
@endsection
