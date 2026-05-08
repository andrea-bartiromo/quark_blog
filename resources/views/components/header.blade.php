{{-- Header Quark --}}
<header class="site-header" role="banner">
  <div class="container">

    {{-- Logo --}}
    <a href="{{ route('home') }}" class="header-logo">
      Quark<span class="dot">.</span>
    </a>

    {{-- Nav principale --}}
    <nav class="header-nav" aria-label="Navigazione principale">
      <a href="{{ route('notizie') }}"
         @class(['active' => request()->routeIs('notizie')])>Articoli</a>

      <a href="{{ route('turing') }}"
         @class(['active' => request()->is('turing*')])>
        Turing
      </a>

      @foreach(config('laboratorio.categories') as $slug => $label)
        @if($loop->index < 3)
        <a href="{{ route('categoria', $slug) }}"
           @class(['active' => request()->is("categoria/{$slug}")])>
          {{ $label }}
        </a>
        @endif
      @endforeach
    </nav>

    {{-- Azioni --}}
    <div class="header-actions">
      <a href="{{ route('ricerca') }}" class="btn-search" aria-label="Cerca">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"/>
          <path d="m21 21-4.35-4.35"/>
        </svg>
      </a>
      <button class="btn-subscribe"
              onclick="document.querySelector('.newsletter-popup').classList.add('visible')">
        ✉ Newsletter
      </button>
    </div>

  </div>
</header>
