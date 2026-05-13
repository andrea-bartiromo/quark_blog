{{-- Sidebar Quark --}}
@php
  $popular = \App\Models\Article::published()
    ->orderByDesc('views')
    ->limit(5)
    ->get(['title','slug','category','read_minutes']);

  $categories = config('laboratorio.categories');
@endphp

<div class="premium-sidebar">

  {{-- Pubblicità sidebar --}}
  <div class="premium-sidebar__ad">
    @include('components.adsense', [
        'slot'   => '3333333333',
        'format' => 'rectangle',
        'style'  => 'margin:0;'
    ])
  </div>

  {{-- Newsletter --}}
  <section class="premium-widget premium-widget--newsletter">
    <span class="premium-widget__kicker">Newsletter</span>
    <h3>Non perderti niente 🧪</h3>
    <p>Ogni settimana i migliori articoli di Quark direttamente nella tua inbox.</p>

    <form class="premium-newsletter-form" action="{{ route('newsletter.subscribe') }}" method="POST">
      @csrf
      <input type="hidden" name="_redirect" value="1">

      <label class="sr-only" for="sidebar-newsletter-email">La tua email</label>
      <input
        id="sidebar-newsletter-email"
        type="email"
        name="email"
        placeholder="La tua email"
        required
        autocomplete="email">

      <button type="submit">Iscriviti gratis</button>
    </form>
  </section>

  {{-- Più letti --}}
  <section class="premium-widget">
    <div class="premium-widget__head">
      <span class="premium-widget__kicker">Trend</span>
      <h3>Più letti</h3>
    </div>

    <div class="premium-most-read">
      @foreach($popular as $i => $art)
        <a href="{{ route('articolo', $art->slug) }}" class="premium-most-read__item">
          <span class="premium-most-read__rank">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
          <span class="premium-most-read__content">
            <span class="badge badge--{{ $art->category }}">{{ $categories[$art->category] ?? $art->category }}</span>
            <strong>{{ Str::limit($art->title, 68) }}</strong>
            <small>{{ $art->read_minutes }} min di lettura</small>
          </span>
        </a>
      @endforeach
    </div>
  </section>

  {{-- Argomenti --}}
  <section class="premium-widget">
    <div class="premium-widget__head">
      <span class="premium-widget__kicker">Sezioni</span>
      <h3>Argomenti</h3>
    </div>

    <nav class="premium-topic-cloud" aria-label="Argomenti">
      @foreach($categories as $slug => $label)
        <a href="{{ route('categoria', $slug) }}">{{ $label }}</a>
      @endforeach
    </nav>
  </section>

</div>