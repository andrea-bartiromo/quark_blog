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
<div class="container" style="padding-block:2rem;">
  <div class="page-layout">

    {{-- Articolo --}}
    <article itemscope itemtype="https://schema.org/BlogPosting">

      {{-- Header --}}
      <header class="article-header">

        <div class="hero-eyebrow">
          <a href="{{ route('categoria', $article->category) }}"
             style="color:inherit;text-decoration:none;">
            {{ config('laboratorio.categories.'.$article->category) }}
          </a>
        </div>

        <h1 class="article-title" itemprop="headline">{{ $article->title }}</h1>

        @if($article->excerpt)
        <p class="article-subtitle">{{ $article->excerpt }}</p>
        @endif

        <div class="article-byline">
          {{-- Autore e meta --}}
          <div class="hero-meta">
            <div class="author-chip">
              <div class="author-avatar">
                @if($article->author->photo)
                  <img src="{{ asset('storage/'.$article->author->photo) }}"
                       alt="{{ $article->author->name }}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                @else
                  {{ mb_substr($article->author->name, 0, 2) }}
                @endif
              </div>
              <a href="{{ route('autore', $article->author) }}"
                 class="author-name" style="color:inherit;">
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
            <span style="font-size:.78rem;color:var(--ink-muted);">{{ number_format($article->views, 0, ',', '.') }} visualizzazioni</span>
          </div>

          {{-- Share --}}
          <div class="article-share">
            <span class="article-share__label">Share</span>
            <a class="share-btn share-btn--tw"
               href="https://twitter.com/intent/tweet?text={{ urlencode($article->title) }}&url={{ urlencode(route('articolo', $article->slug)) }}"
               target="_blank" rel="noopener" aria-label="X/Twitter" title="X / Twitter">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
            </a>
            <a class="share-btn share-btn--wa"
               href="https://api.whatsapp.com/send?text={{ urlencode($article->title.' '.route('articolo', $article->slug)) }}"
               target="_blank" rel="noopener" aria-label="WhatsApp" title="WhatsApp">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            </a>
            <a class="share-btn share-btn--li"
               href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode(route('articolo', $article->slug)) }}"
               target="_blank" rel="noopener" aria-label="LinkedIn" title="LinkedIn">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
            </a>
            <button class="share-btn share-btn--copy" id="copy-link-btn"
                    onclick="copyArticleLink('{{ route('articolo', $article->slug) }}')"
                    aria-label="Copia link" title="Copia link">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
            </button>
          </div>
        </div>
      </header>

      {{-- Immagine cover --}}
      @if($article->cover_image)
      <figure style="margin-bottom:2rem;">
        <img class="article-cover"
             src="{{ asset('assets/img/'.$article->cover_image) }}"
             alt="{{ $article->title }}"
             itemprop="image" loading="lazy">
      </figure>
      @endif

      {{-- Pubblicità: sopra l'articolo --}}
      @include('components.adsense', [
          'slot'   => '1111111111',
          'format' => 'horizontal',
          'style'  => 'margin-bottom:1.5rem;'
      ])

      {{-- Corpo --}}
      <div class="article-body" itemprop="articleBody">
        @php
          $bodyParts = explode('---', $article->body);
          $mainBody  = $bodyParts[0] ?? $article->body;
          $sources   = isset($bodyParts[1]) ? trim($bodyParts[1]) : null;

          // Rileva se il body contiene HTML (da TinyMCE) o testo semplice
          $isHtml = strip_tags($mainBody) !== $mainBody;
        @endphp

        @if($isHtml)
          {{-- Contenuto HTML da TinyMCE — output diretto --}}
          {!! $mainBody !!}
        @else
          {{-- Contenuto testo semplice — rendering classico --}}
          @php
            $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', e($mainBody));
            $paragraphs = array_filter(explode("\n\n", $html));
          @endphp
          @foreach($paragraphs as $para)
            @php $para = trim($para); @endphp
            @if(Str::startsWith($para, '<strong>') && Str::endsWith(explode("\n",$para)[0], '</strong>'))
              <h2>{!! preg_replace('/^<strong>(.*?)<\/strong>/', '$1', explode("\n",$para)[0]) !!}</h2>
              @php $rest = implode("\n", array_slice(explode("\n",$para), 1)); @endphp
              @if(trim($rest))<p>{!! nl2br($rest) !!}</p>@endif
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

      {{-- Box autore --}}
      <div class="author-box" style="margin-top:2.5rem;">
        <div class="author-box__avatar">
          @if($article->author->photo)
            <img src="{{ asset('storage/'.$article->author->photo) }}"
                 alt="{{ $article->author->name }}">
          @else
            {{ mb_substr($article->author->name, 0, 2) }}
          @endif
        </div>
        <div>
          <div class="author-box__role">
            {{ $article->author->role === 'editor' ? 'Fondatore e Direttore' : 'Redattore' }}
          </div>
          <div class="author-box__name">
            <a href="{{ route('autore', $article->author) }}"
               style="color:inherit;text-decoration:none;">
              {{ $article->author->name }}
            </a>
          </div>
          @if($article->author->bio)
          <div class="author-box__bio">{{ $article->author->bio }}</div>
          @endif
          <div class="author-box__socials">
            @if($article->author->twitter)
              <a href="https://twitter.com/{{ ltrim($article->author->twitter,'@') }}"
                 target="_blank" rel="noopener">X/Twitter</a>
            @endif
            <a href="{{ route('autore', $article->author) }}">Tutti gli articoli</a>
          </div>
        </div>
      </div>

      {{-- Pubblicità: dopo l'autore --}}
      @include('components.adsense', [
          'slot'   => '2222222222',
          'format' => 'rectangle',
          'style'  => 'margin:1.5rem 0;'
      ])

      {{-- Articoli correlati --}}
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
                   alt="{{ $rel->title }}" loading="lazy">
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

      {{-- Commenti --}}
      <div class="comments-section">
        <div class="section-head" style="margin-top:2.5rem;">
          <h2>Commenti ({{ $article->comments->count() }})</h2>
        </div>

        {{-- Form commento --}}
        <div class="comment-form">
          <h3>Lascia un commento</h3>
          <form method="POST" action="{{ route('commenti.store') }}">
            @csrf
            <input type="hidden" name="article_id" value="{{ $article->id }}">
            <input type="text" name="website" style="display:none" tabindex="-1" autocomplete="off">
            <div class="form-row">
              <div class="form-group" style="margin:0;">
                <input class="form-input" type="text" name="name"
                       placeholder="Il tuo nome" required maxlength="80">
              </div>
              <div class="form-group" style="margin:0;">
                <input class="form-input" type="email" name="email"
                       placeholder="La tua email (non verrà pubblicata)" required maxlength="150">
              </div>
            </div>
            <div class="form-group">
              <textarea class="form-textarea" name="body"
                        placeholder="Scrivi qui il tuo commento…"
                        required maxlength="1000" style="min-height:80px;"></textarea>
            </div>
            <button type="submit" class="btn btn--primary" style="font-size:.82rem;">
              Invia commento
            </button>
          </form>
        </div>

        {{-- Lista commenti --}}
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

    {{-- Sidebar --}}
    <aside>
      @include('components.sidebar')
    </aside>

  </div>
</div>
@endsection

@push('scripts')
<script>
function copyArticleLink(url) {
    navigator.clipboard.writeText(url).then(function() {
        var btn = document.getElementById('copy-link-btn');
        var orig = btn.innerHTML;
        var checkSvg = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>';
        btn.innerHTML = checkSvg;
        btn.style.background = 'var(--primary)';
        btn.style.color = '#fff';
        setTimeout(function() {
            btn.innerHTML = orig;
            btn.style.background = '';
            btn.style.color = '';
        }, 2000);
    }).catch(function() {
        var ta = document.createElement('textarea');
        ta.value = url;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    });
}
</script>
@endpush