@extends('layouts.app')

@section('title', $article->title.' — '.config('laboratorio.name'))
@section('description', $article->excerpt)
@section('og_type', 'article')

@section('head')
<link rel="canonical" href="{{ route('articolo', $article->slug) }}">
<meta property="og:image" content="{{ asset('assets/img/'.($article->cover_image ?? 'hero-placeholder.svg')) }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="article:published_time" content="{{ $article->published_at->toIso8601String() }}">
<meta property="article:modified_time" content="{{ $article->updated_at->toIso8601String() }}">
<meta property="article:author" content="{{ $article->author->name }}">
<meta property="article:section" content="{{ \App\Models\Category::options(false)[$article->category] ?? $article->category }}">
<style>
.reading-progress{position:fixed;top:0;left:0;height:3px;width:0;background:linear-gradient(90deg,#0d9488,#67e8f9);z-index:9999;transition:width .08s linear}
</style>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BlogPosting",
  "headline": @json($article->title),
  "description": @json($article->excerpt),
  "datePublished": "{{ $article->published_at->toIso8601String() }}",
  "dateModified": "{{ $article->updated_at->toIso8601String() }}",
  "url": "{{ route('articolo', $article->slug) }}",
  "image": "{{ asset('assets/img/'.($article->cover_image ?? 'hero-placeholder.svg')) }}",
  "author": {"@type":"Person","name": @json($article->author->name)},
  "publisher": {"@type":"Organization","name": @json(config('laboratorio.name'))},
  "articleSection": @json(\App\Models\Category::options(false)[$article->category] ?? $article->category),
  "inLanguage": "it-IT"
}
</script>
@endsection

@section('content')
<div class="reading-progress" id="reading-progress"></div>

@php
  $categoryLabel = \App\Models\Category::options(false)[$article->category] ?? $article->category;
  $cover = asset('assets/img/'.($article->cover_image ?? 'hero-placeholder.svg'));
  $bodyParts = explode('---', $article->body);
  $mainBody = $bodyParts[0] ?? $article->body;
  $sources = isset($bodyParts[1]) ? trim($bodyParts[1]) : null;
  $isHtml = strip_tags($mainBody) !== $mainBody;
@endphp

<div class="public-shell">
  <article class="article-premium" itemscope itemtype="https://schema.org/BlogPosting">

    <header class="article-premium__hero">
      <img src="{{ $cover }}" alt="{{ $article->title }}" loading="eager" itemprop="image">
      <div class="article-premium__overlay"></div>

      <div class="article-premium__content">
        <a href="{{ route('categoria', $article->category) }}" class="public-hero__kicker" style="text-decoration:none;">
          {{ $categoryLabel }}
        </a>

        <h1 itemprop="headline">{{ $article->title }}</h1>

        @if($article->excerpt)
        <p class="article-premium__excerpt" itemprop="description">
          {{ $article->excerpt }}
        </p>
        @endif

        <div class="article-premium__meta">
          <span>{{ $article->author->name }}</span>
          <span>·</span>
          <time datetime="{{ $article->published_at->toDateString() }}" itemprop="datePublished">
            {{ $article->published_at->locale('it')->isoFormat('D MMMM YYYY') }}
          </time>
          <span>·</span>
          <span>{{ $article->read_minutes }} min di lettura</span>
          <span>·</span>
          <span>{{ number_format($article->views, 0, ',', '.') }} visualizzazioni</span>
        </div>
      </div>
    </header>

    <div class="article-premium__layout">
      <main>
        <section class="article-premium__body" itemprop="articleBody">
          @if($isHtml)
            {!! $mainBody !!}
          @else
            @php
              $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', e($mainBody));
              $paragraphs = array_filter(explode("\n\n", $html));
            @endphp

            @foreach($paragraphs as $para)
              @php $para = trim($para); @endphp

              @if(Str::startsWith($para, '<strong>') && Str::contains(explode("\n", $para)[0], '</strong>'))
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
          <div class="article-premium__panel" style="margin-top:2rem;">
            <h3>Fonti</h3>
            <p style="margin:0;">{!! nl2br(e($sources)) !!}</p>
          </div>
          @endif
        </section>

        <section class="public-feature-band">
          <span class="public-hero__kicker">Newsletter Quark</span>
          <h2>Ricevi il meglio della scienza ogni settimana.</h2>
          <p>Una selezione ragionata di articoli, scoperte e analisi dalla redazione. Niente rumore, solo contenuti utili.</p>
          <form method="POST" action="{{ route('newsletter.subscribe') }}" style="display:flex;gap:.7rem;flex-wrap:wrap;margin-top:1rem;">
            @csrf
            <input type="email" name="email" placeholder="La tua email" required style="flex:1;min-width:220px;border:0;border-radius:14px;padding:.9rem 1rem;">
            <button type="submit" style="border:0;border-radius:14px;padding:.9rem 1.1rem;font-weight:900;background:#67e8f9;color:#001018;cursor:pointer;">Iscriviti</button>
          </form>
        </section>

        @if(isset($related) && $related->count())
        <section style="margin-top:2rem;">
          <div class="public-section-head">
            <div>
              <span>Related stories</span>
              <h2>Continua a leggere</h2>
            </div>
          </div>

          <div class="related-premium-grid">
            @foreach($related as $item)
            <a href="{{ route('articolo', $item->slug) }}" class="public-card">
              <div class="public-card__media">
                <img src="{{ asset('assets/img/'.($item->cover_image ?? 'placeholder-1.svg')) }}" alt="{{ $item->title }}" loading="lazy">
              </div>
              <div class="public-card__body">
                <h3>{{ $item->title }}</h3>
                <p>{{ Str::limit($item->excerpt, 90) }}</p>
              </div>
            </a>
            @endforeach
          </div>
        </section>
        @endif
      </main>

      <aside class="article-premium__aside">
        <div class="article-premium__panel">
          <h3>Autore</h3>
          <div style="display:flex;gap:.8rem;align-items:center;">
            <div class="author-avatar">
              @if($article->author->photo)
                <img src="{{ asset('storage/'.$article->author->photo) }}" alt="{{ $article->author->name }}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
              @else
                {{ mb_substr($article->author->name, 0, 2) }}
              @endif
            </div>
            <div>
              <strong>{{ $article->author->name }}</strong><br>
              <a href="{{ route('autore', $article->author) }}" style="font-size:.85rem;color:#0f766e;text-decoration:none;font-weight:800;">Profilo autore</a>
            </div>
          </div>
        </div>

        <div class="article-premium__panel">
          <h3>Condividi</h3>
          <div class="article-premium__share">
            <a href="https://twitter.com/intent/tweet?text={{ urlencode($article->title) }}&url={{ urlencode(route('articolo', $article->slug)) }}" target="_blank" rel="noopener">X</a>
            <a href="https://api.whatsapp.com/send?text={{ urlencode($article->title.' '.route('articolo', $article->slug)) }}" target="_blank" rel="noopener">WA</a>
            <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode(route('articolo', $article->slug)) }}" target="_blank" rel="noopener">in</a>
            <button type="button" onclick="copyArticleLink('{{ route('articolo', $article->slug) }}')">Link</button>
          </div>
        </div>

        <div class="article-premium__panel">
          @include('components.sidebar')
        </div>
      </aside>
    </div>
  </article>
</div>
@endsection

@push('scripts')
<script>
function copyArticleLink(url) {
  navigator.clipboard.writeText(url).then(() => alert('Link copiato negli appunti'));
}

window.addEventListener('scroll', () => {
  const progress = document.getElementById('reading-progress');
  if (!progress) return;
  const scrollTop = window.scrollY || document.documentElement.scrollTop;
  const docHeight = document.documentElement.scrollHeight - window.innerHeight;
  progress.style.width = docHeight > 0 ? ((scrollTop / docHeight) * 100) + '%' : '0%';
});
</script>
@endpush
