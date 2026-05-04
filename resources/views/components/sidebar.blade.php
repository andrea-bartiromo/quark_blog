{{-- Sidebar Quark --}}
@php
  $popular = \App\Models\Article::published()
    ->orderByDesc('views')
    ->limit(5)
    ->get(['title','slug','category','read_minutes']);

  $categories = config('laboratorio.categories');
@endphp

<div class="sidebar">

  {{-- Pubblicità sidebar --}}
  @include('components.adsense', [
      'slot'   => '3333333333',
      'format' => 'rectangle',
      'style'  => 'margin-bottom:.5rem;'
  ])


  {{-- Newsletter --}}
  <div class="newsletter-box">
    <h3>Non perderti niente 🧪</h3>
    <p>Ogni settimana i migliori articoli di Quark direttamente nella tua inbox.</p>
    <form class="newsletter-form" action="{{ route('newsletter.subscribe') }}" method="POST">
      @csrf
      <input type="email" name="email" placeholder="La tua email" required>
      <button type="submit">Iscriviti gratis</button>
    </form>
  </div>

  {{-- Più letti --}}
  <div class="sidebar-box">
    <div class="sidebar-box__head">
      <h3>🔥 Più letti</h3>
    </div>
    <div class="sidebar-box__body" style="padding:0;">
      @foreach($popular as $i => $art)
      <a href="{{ route('articolo', $art->slug) }}"
         style="display:flex;align-items:flex-start;gap:.75rem;padding:.75rem 1rem;
                border-bottom:1px solid var(--border-light);text-decoration:none;
                transition:background .15s;"
         onmouseover="this.style.background='var(--paper-warm)'"
         onmouseout="this.style.background=''">
        <span style="font-family:var(--font-display);font-size:1.4rem;font-weight:900;
                     color:var(--border);line-height:1;flex-shrink:0;margin-top:2px;">
          {{ $i + 1 }}
        </span>
        <div>
          <span class="badge badge--{{ $art->category }}" style="margin-bottom:.2rem;display:inline-block;">
            {{ $categories[$art->category] ?? $art->category }}
          </span>
          <div style="font-size:.82rem;font-weight:600;color:var(--ink);line-height:1.3;">
            {{ Str::limit($art->title, 60) }}
          </div>
          <div style="font-size:.7rem;color:var(--ink-muted);margin-top:.2rem;">
            {{ $art->read_minutes }} min
          </div>
        </div>
      </a>
      @endforeach
    </div>
  </div>

  {{-- Argomenti --}}
  <div class="sidebar-box">
    <div class="sidebar-box__head">
      <h3>Argomenti</h3>
    </div>
    <div class="sidebar-box__body">
      <div class="tag-pills">
        @foreach($categories as $slug => $label)
          <a href="{{ route('categoria', $slug) }}" class="tag-pill">{{ $label }}</a>
        @endforeach
      </div>
    </div>
  </div>

</div>