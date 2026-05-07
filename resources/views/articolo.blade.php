@extends('layouts.app')

@section('title', $article->title.' — '.config('laboratorio.name'))
@section('description', $article->excerpt)
@section('og_type', 'article')

@section('og_image')
  <meta property="og:image" content="{{ asset('assets/img/'.($article->cover_image ?? 'hero-placeholder.svg')) }}">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:description" content="{{ $article->excerpt }}">
  <meta property="article:published_time" content="{{ $article->published_at->toIso8601String() }}">
  <meta property="article:modified_time" content="{{ $article->updated_at->toIso8601String() }}">
  <meta property="article:author" content="{{ $article->author->name }}">
  <meta property="article:section" content="{{ config('laboratorio.categories.'.$article->category) }}">
@endsection

@section('head')
<link rel="canonical" href="{{ route('articolo', $article->slug) }}">

<style>
  .reading-progress {
    position: fixed;
    top: 0;
    left: 0;
    height: 3px;
    width: 0%;
    background: linear-gradient(90deg, #0d9488, #14b8a6);
    z-index: 9999;
    transition: width .08s linear;
  }

  .article-newsletter-cta {
    margin: 2.5rem 0;
    padding: 1.5rem;
    border-radius: 20px;
    background: linear-gradient(135deg, #0f766e, #0d9488);
    color: #fff;
    box-shadow: 0 18px 40px rgba(13, 148, 136, .25);
  }

  .article-newsletter-cta__eyebrow {
    font-size: .72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .12em;
    opacity: .85;
    margin-bottom: .5rem;
  }

  .article-newsletter-cta h3 {
    font-size: 1.35rem;
    margin: 0 0 .5rem;
    color: #fff;
  }

  .article-newsletter-cta p {
    margin: 0 0 1rem;
    opacity: .9;
    line-height: 1.6;
  }

  .article-newsletter-cta form {
    display: flex;
    gap: .6rem;
    flex-wrap: wrap;
  }

  .article-newsletter-cta input {
    flex: 1;
    min-width: 220px;
    border: 0;
    border-radius: 999px;
    padding: .8rem 1rem;
    font-size: .9rem;
  }

  .article-newsletter-cta button {
    border: 0;
    border-radius: 999px;
    padding: .8rem 1.1rem;
    font-weight: 800;
    color: #0f766e;
    background: #fff;
    cursor: pointer;
  }

  @media (max-width: 640px) {
    .article-newsletter-cta {
      padding: 1.2rem;
      border-radius: 16px;
    }

    .article-newsletter-cta button,
    .article-newsletter-cta input {
      width: 100%;
    }
  }
</style>

<script type="application/ld+json">
@verbatim
{
  "@context": "https://schema.org",
  "@type": "BlogPosting",
  "headline": "{{ addslashes($article->title) }}",
  "description": "{{ addslashes($article->excerpt) }}",
  "datePublished": "{{ $article->published_at->toIso8601String() }}",
  "dateModified": "{{ $article->updated_at->toIso8601String() }}",
  "url": "{{ route('articolo', $article->slug) }}",
  "image": {
    "@type": "ImageObject",
    "url": "{{ asset('assets/img/'.($article->cover_image ?? 'hero-placeholder.svg')) }}",
    "width": 1200,
    "height": 630
  },
  "author": {
    "@type": "Person",
    "name": "{{ $article->author->name }}",
    "url": "{{ route('autore', $article->author) }}"
  },
  "publisher": {
    "@type": "Organization",
    "name": "{{ config('laboratorio.name') }}",
    "url": "{{ config('app.url') }}",
    "logo": {
      "@type": "ImageObject",
      "url": "{{ asset('assets/icons/logo.svg') }}"
    }
  },
  "articleSection": "{{ config('laboratorio.categories.'.$article->category) }}",
  "inLanguage": "it-IT",
  "isAccessibleForFree": true,
  "mainEntityOfPage": {
    "@type": "WebPage",
    "@id": "{{ route('articolo', $article->slug) }}"
  }
}
@endverbatim
</script>
@endsection

@section('content')
<div class="reading-progress" id="reading-progress"></div>

<div class="container" style="padding-block:2rem;">
  <div class="page-layout">

    <article itemscope itemtype="https://schema.org/BlogPosting">

      <header class="article-header">
        <div class="hero-eyebrow">
          <a href="{{ route('categoria', $article->category) }}" style="color:inherit;text-decoration:none;">
            {{ config('laboratorio.categories.'.$article->category) }}
          </a>
        </div>

        <h1 class="article-title" itemprop="headline">{{ $article->title }}</h1>

        @if($article->excerpt)
          <p class="article-subtitle">{{ $article->excerpt }}</p>
        @endif

        <div class="article-byline">
          <div class="hero-meta">
            <div class="author-chip">
              <div class="author-avatar">
                @if($article->author->photo)
                  <img src="{{ asset('storage/'.$article->author->photo) }}"
                       alt="{{ $article->author->name }}"
                       style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                @else
                  {{ mb_substr($article->author->name, 0, 2) }}
                @endif
              </div>

              <a href="{{ route('autore', $article->author) }}" class="author-name" style="color:inherit;">
                {{ $article->author->name }}
              </a>
            </div>

            <span class="meta-sep">·</span>

            <time class="meta-date" datetime="{{ $article->published_at->toDateString() }}" itemprop="datePublished">
              {{ $article->published_at->locale('it')->isoFormat('D MMMM YYYY') }}
            </time>

            <span class="meta-sep">·</span>
            <span class="meta-read">{{ $article->read_minutes }} min di lettura</span>

            <span class="meta-sep">·</span>
            <span style="font-size:.78rem;color:var(--ink-muted);">
              {{ number_format($article->views, 0, ',', '.') }} visualizzazioni
            </span>
          </div>

          <div class="article-share">
            <span class="article-share__label">Share</span>

            <a class="share-btn share-btn--tw"
               href="https://twitter.com/intent/tweet?text={{ urlencode($article->title) }}&url={{ urlencode(route('articolo', $article->slug)) }}"
               target="_blank" rel="noopener" aria-label="X/Twitter" title="X / Twitter">
              X
            </a>

            <a class="share-btn share-btn--wa"
               href="https://api.whatsapp.com/send?text={{ urlencode($article->title.' '.route('articolo', $article->slug)) }}"
               target="_blank" rel="noopener" aria-label="WhatsApp" title="WhatsApp">
              WA
            </a>

            <a class="share-btn share-btn--li"
               href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode(route('articolo', $article->slug)) }}"
               target="_blank" rel="noopener" aria-label="LinkedIn" title="LinkedIn">
              in
            </a>

            <button class="share-btn share-btn--copy"
                    id="copy-link-btn"
                    onclick="copyArticleLink('{{ route('articolo', $article->slug) }}')"
                    aria-label="Copia link"
                    title="Copia link">
              ⛓
            </button>
          </div>
        </div>
      </header>

      @if($article->cover_image)
        <figure style="margin-bottom:2rem;">
          <img class="article-cover"
               src="{{ asset('assets/img/'.$article->cover_image) }}"
               alt="{{ $article->title }}"
               itemprop="image"
               loading="lazy">
        </figure>
      @endif

      @include('components.adsense', [
          'slot'   => '1111111111',
          'format' => 'horizontal',
          'style'  => 'margin-bottom:1.5rem;'
      ])

      <div class="article-body" itemprop="articleBody">
        @php
          $bodyParts = explode('---', $article->body);
          $mainBody  = $bodyParts[0] ?? $article->body;
          $sources   = isset($bodyParts[1]) ? trim($bodyParts[1]) : null;
          $isHtml = strip_tags($mainBody) !== $mainBody;
        @endphp

        @if($isHtml)
          {!! $mainBody !!}
        @else
          @php
            $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', e($mainBody));
            $paragraphs = array_filter(explode("\n\n", $html));
          @endphp

          @foreach($paragraphs as $para)
            @php $para = trim($para); @endphp

            @if(Str::startsWith($para, '<strong>') && Str::endsWith(explode("\n", $para)[0], '</strong>'))
              <h2>{!! preg_replace('/^<strong>(.*?)<\/strong>/', '$1', explode("\n", $para)[0]) !!}</h2>

              @php $rest = implode("\n", array_slice(explode("\n", $para), 1)); @endphp

              @if(trim($rest))
                <p>{!! nl2br($rest) !!}</p>
              @endif
            @else
              <p>{!! nl2br($para) !!}</p>
            @endif
          @endforeach
        @endif

        @if($sources)
          <div class="article-sources">
            <strong>Fonti</strong>
            {!! nl2br(e($sources)) !!}
          </div>
        @endif
      </div>

      <div class="article-newsletter-cta">
        <div class="article-newsletter-cta__eyebrow">
          Newsletter Quark
        </div>

        <h3>
          Ricevi il meglio della scienza ogni settimana
        </h3>

        <p>
          Una selezione ragionata di articoli, scoperte e analisi dalla redazione.
          Niente rumore, solo contenuti utili.
        </p>

        <form method="POST" action="{{ route('newsletter.subscribe') }}">
          @csrf

          <input
            type="email"
            name="email"
            placeholder="La tua email"
            required
          >

          <button type="submit">
            Iscriviti
          </button>
        </form>
      </div>

      <div class="author-box" style="margin-top:2.5rem;">
        <div class="author-box__avatar">
          @if($article->author->photo)
            <img src="{{ asset('storage/'.$article->author->photo) }}" alt="{{ $article->author->name }}">
          @else
            {{ mb_substr($article->author->name, 0, 2) }}
          @endif
        </div>

        <div>
          <div class="author-box__role">
            {{ $article->author->role === 'editor' ? 'Fondatore e Direttore' : 'Redattore' }}
          </div>

          <div class="author-box__name">
            <a href="{{ route('autore', $article->author) }}" style="color:inherit;text-decoration:none;">
              {{ $article->author->name }}
            </a>
          </div>

          @if($article->author->bio)
            <div class="author-box__bio">{{ $article->author->bio }}</div>
          @endif

          <div class="author-box__socials">
            @if($article->author->twitter)
              <a href="https://twitter.com/{{ ltrim($article->author->twitter, '@') }}"
                 target="_blank"
                 rel="noopener">
                X/Twitter
              </a>
            @endif

            <a href="{{ route('autore', $article->author) }}">Tutti gli articoli</a>
          </div>
        </div>
      </div>

      @include('components.adsense', [
          'slot'   => '2222222222',
          'format' => 'rectangle',
          'style'  => 'margin:1.5rem 0;'
      ])

      @if(isset($related) && $related->count() > 0)
        <div style="margin-top:2.5rem;">
          <div class="section-head">
            <h2>Potrebbe interessarti</h2>
          </div>

          <div class="card-grid" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr));">
            @foreach($related as $rel)
              <a href="{{ route('articolo', $rel->slug) }}" class="card" style="text-decoration:none;">
                <div class="card__img">
                  <img src="{{ asset('assets/img/'.($rel->cover_image ?? 'placeholder-1.svg')) }}"
                       alt="{{ $rel->title }}"
                       loading="lazy">
                  <span class="card__cat-badge">{{ config('laboratorio.categories.'.$rel->category) }}</span>
                </div>

                <div class="card__body">
                  <div class="card__title">{{ $rel->title }}</div>
                  <div class="card__footer">
                    <span class="card__read">{{ $rel->read_minutes }} min</span>
                  </div>
                </div>
              </a>
            @endforeach
          </div>
        </div>
      @endif

      <div class="comments-section">
        <div class="section-head" style="margin-top:2.5rem;">
          <h2>Commenti ({{ $article->comments->count() }})</h2>
        </div>

        <div class="comment-form">
          <h3>Lascia un commento</h3>

          <form method="POST" action="{{ route('commenti.store') }}">
            @csrf

            <input type="hidden" name="article_id" value="{{ $article->id }}">
            <input type="text" name="website" style="display:none" tabindex="-1" autocomplete="off">

            <div class="form-row">
              <div class="form-group" style="margin:0;">
                <input class="form-input"
                       type="text"
                       name="name"
                       placeholder="Il tuo nome"
                       required
                       maxlength="80">
              </div>

              <div class="form-group" style="margin:0;">
                <input class="form-input"
                       type="email"
                       name="email"
                       placeholder="La tua email (non verrà pubblicata)"
                       required
                       maxlength="150">
              </div>
            </div>

            <div class="form-group">
              <textarea class="form-textarea"
                        name="body"
                        placeholder="Scrivi qui il tuo commento…"
                        required
                        maxlength="1000"
                        style="min-height:80px;"></textarea>
            </div>

            <button type="submit" class="btn btn--primary" style="font-size:.82rem;">
              Invia commento
            </button>
          </form>
        </div>

        @foreach($article->comments as $comment)
          <div class="comment-item">
            <div class="comment-author">
              <div class="author-avatar" style="width:28px;height:28px;font-size:.65rem;">
                {{ mb_substr($comment->name, 0, 2) }}
              </div>

              <span class="comment-author__name">{{ e($comment->name) }}</span>

              <span class="comment-author__date">
                {{ $comment->created_at->locale('it')->diffForHumans() }}
              </span>
            </div>

            <div class="comment-body">{{ e($comment->body) }}</div>
          </div>
        @endforeach
      </div>

    </article>

    <aside>
      @include('components.sidebar')
    </aside>

  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  const bar = document.getElementById('reading-progress');
  if (!bar) return;

  function updateProgress() {
    const scrollTop = window.scrollY || document.documentElement.scrollTop;
    const docHeight = document.documentElement.scrollHeight - window.innerHeight;

    if (docHeight <= 0) {
      bar.style.width = '0%';
      return;
    }

    const progress = Math.min(100, Math.max(0, (scrollTop / docHeight) * 100));
    bar.style.width = progress + '%';
  }

  window.addEventListener('scroll', updateProgress, { passive: true });
  window.addEventListener('resize', updateProgress);
  updateProgress();
})();

function copyArticleLink(url) {
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(url).then(function () {
      showCopyFeedback();
    }).catch(function () {
      fallbackCopy(url);
    });

    return;
  }

  fallbackCopy(url);
}

function fallbackCopy(url) {
  var ta = document.createElement('textarea');
  ta.value = url;
  ta.setAttribute('readonly', '');
  ta.style.position = 'absolute';
  ta.style.left = '-9999px';

  document.body.appendChild(ta);
  ta.select();
  document.execCommand('copy');
  document.body.removeChild(ta);

  showCopyFeedback();
}

function showCopyFeedback() {
  var btn = document.getElementById('copy-link-btn');
  if (!btn) return;

  var original = btn.innerHTML;

  btn.innerHTML = '✓';
  btn.style.background = 'var(--primary)';
  btn.style.color = '#fff';

  setTimeout(function () {
    btn.innerHTML = original;
    btn.style.background = '';
    btn.style.color = '';
  }, 1800);
}
</script>
@endpush