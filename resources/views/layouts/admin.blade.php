{{-- Quark — CMS Admin
     Fondatore: Andrea Bartiromo --}}
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title','Admin') — Quark</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Fraunces:ital,wght@0,700;0,900;1,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
  <meta name="robots" content="noindex,nofollow">
</head>

<body class="admin-body">
<button type="button"
        class="admin-sidebar-toggle"
        aria-label="Apri menu amministrazione"
        aria-controls="admin-sidebar"
        aria-expanded="false"
        data-admin-sidebar-toggle>
  <span aria-hidden="true">☰</span>
</button>
<div class="admin-sidebar-overlay" data-admin-sidebar-overlay hidden></div>

<div class="admin-layout">

  <aside id="admin-sidebar" class="admin-sidebar" aria-label="Menu amministrazione">

    <div class="admin-sidebar__brand">
      <div class="admin-sidebar__logo">
        Quark<span class="dot">.</span>
      </div>
      <span class="admin-sidebar__sub">Pannello redazionale</span>
    </div>

    @php
      $isDashboard     = request()->routeIs('admin.dashboard');
      $isArticles      = request()->routeIs('admin.articles*');
      $isCategories    = request()->routeIs('admin.categories*');
      $isMedia         = request()->routeIs('admin.media*');
      $isComments      = request()->routeIs('admin.comments*');
      $isReview        = request()->routeIs('admin.review*');
      $isVerification  = request()->routeIs('admin.verification');
      $isCollaborators = request()->routeIs('admin.collaborators*');
      $isNewsletter    = request()->routeIs('admin.newsletter')
                          || request()->routeIs('admin.newsletter.export')
                          || request()->routeIs('admin.newsletter.send-now');
      $isAds           = request()->routeIs('admin.ads*');
      $isTuring        = request()->routeIs('admin.turing*');
      $isSuggestions   = request()->routeIs('admin.suggestions*');
      $isStats         = request()->routeIs('admin.stats*');
      $isActivity      = request()->routeIs('admin.activity');
      $isNewsletterPrev = request()->routeIs('admin.newsletter.preview');
      $isProfile       = request()->routeIs('admin.profile*');

      $toolsOpen = $isTuring || $isSuggestions || $isStats || $isActivity || $isNewsletterPrev;
    @endphp

    <nav class="admin-nav" aria-label="Navigazione amministrazione">

      <span class="admin-nav__section">Principale</span>

      <a href="{{ route('admin.dashboard') }}"
         @class(['active' => $isDashboard])
         @if($isDashboard) aria-current="page" @endif>
        <span class="icon">📊</span> Dashboard
      </a>

      <span class="admin-nav__section">Contenuti</span>

      <a href="{{ route('admin.articles') }}"
         @class(['active' => $isArticles])
         @if($isArticles) aria-current="page" @endif>
        <span class="icon">📝</span> Articoli
      </a>

      <a href="{{ route('admin.categories') }}"
         @class(['active' => $isCategories])
         @if($isCategories) aria-current="page" @endif>
        <span class="icon">🏷️</span> Categorie
      </a>

      <a href="{{ route('admin.media') }}"
         @class(['active' => $isMedia])
         @if($isMedia) aria-current="page" @endif>
        <span class="icon">🖼️</span> Media
      </a>

      <a href="{{ route('admin.comments') }}"
         @class(['active' => $isComments])
         @if($isComments) aria-current="page" @endif>
        <span class="icon">💬</span> Commenti
      </a>

      <span class="admin-nav__section">Redazione</span>

      <a href="{{ route('admin.review') }}"
         @class(['active' => $isReview])
         @if($isReview) aria-current="page" @endif>
        <span class="icon">📋</span> Revisione
      </a>

      <a href="{{ route('admin.verification') }}"
         @class(['active' => $isVerification])
         @if($isVerification) aria-current="page" @endif>
        <span class="icon">✅</span> Fonti
      </a>

      <a href="{{ route('admin.collaborators') }}"
         @class(['active' => $isCollaborators])
         @if($isCollaborators) aria-current="page" @endif>
        <span class="icon">👥</span> Collaboratori
      </a>

      <span class="admin-nav__section">Comunicazione</span>

      <a href="{{ route('admin.newsletter') }}"
         @class(['active' => $isNewsletter])
         @if($isNewsletter) aria-current="page" @endif>
        <span class="icon">✉️</span> Newsletter
      </a>

      <a href="{{ route('admin.ads') }}"
         @class(['active' => $isAds])
         @if($isAds) aria-current="page" @endif>
        <span class="icon">📢</span> Pubblicità
      </a>

      <details class="admin-nav__group" @if($toolsOpen) open @endif>
        <summary class="admin-nav__section admin-nav__section--toggle">Strumenti</summary>

        <a href="{{ route('admin.turing') }}"
           @class(['active' => $isTuring])
           @if($isTuring) aria-current="page" @endif>
          <span class="icon">🧠</span> Turing
        </a>

        <a href="{{ route('admin.suggestions') }}"
           @class(['active' => $isSuggestions])
           @if($isSuggestions) aria-current="page" @endif>
          <span class="icon">🤖</span> Assistente AI
        </a>

        <a href="{{ route('admin.stats') }}"
           @class(['active' => $isStats])
           @if($isStats) aria-current="page" @endif>
          <span class="icon">📈</span> Statistiche
        </a>

        <a href="{{ route('admin.activity') }}"
           @class(['active' => $isActivity])
           @if($isActivity) aria-current="page" @endif>
          <span class="icon">🕐</span> Attività
        </a>

        <a href="{{ route('admin.newsletter.preview') }}"
           @class(['active' => $isNewsletterPrev])
           @if($isNewsletterPrev) aria-current="page" @endif>
          <span class="icon">👁️</span> Anteprima newsletter
        </a>
      </details>

      <span class="admin-nav__section">Account</span>

      <a href="{{ route('admin.profile') }}"
         @class(['active' => $isProfile])
         @if($isProfile) aria-current="page" @endif>
        <span class="icon">👤</span> Profilo
      </a>

      <a href="{{ route('home') }}" target="_blank">
        <span class="icon">🌐</span> Vedi sito
      </a>

      <button type="submit" form="logout-form" class="admin-nav__logout-btn">
        <span class="icon">🚪</span> Esci
      </button>

      <form id="logout-form" action="{{ route('admin.logout') }}" method="POST" style="display:none">
        @csrf
      </form>

    </nav>

    <div class="admin-sidebar__user">
      <div class="admin-sidebar__user-avatar">
        {{ mb_substr(auth()->user()->name, 0, 2) }}
      </div>
      <div>
        <span class="admin-sidebar__user-name">{{ auth()->user()->name }}</span>
        <span class="admin-sidebar__user-role">{{ auth()->user()->role }}</span>
      </div>
    </div>

  </aside>

  <main class="admin-main">

    <div style="margin-bottom:1.25rem;" x-data>
      <form method="GET" action="{{ route('admin.articles') }}"
            style="display:flex;gap:.5rem;max-width:400px;">
        <input type="text" name="q"
               value="{{ request('q') }}"
               placeholder="🔍 Cerca articoli, commenti..."
               style="flex:1;padding:.45rem .75rem;border:1px solid #e5e7eb;
                      border-radius:6px;font-size:.82rem;font-family:inherit;
                      background:#fff;">
        <button type="submit" class="btn btn--secondary btn--sm">Cerca</button>
      </form>
    </div>

    @if(session('success'))
    <div style="background:#d1fae5;border:1px solid #6ee7b7;border-radius:8px;
                padding:.75rem 1rem;margin-bottom:1rem;font-size:.875rem;color:#065f46;">
      ✅ {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;
                padding:.75rem 1rem;margin-bottom:1rem;font-size:.875rem;color:#991b1b;">
      ❌ {{ session('error') }}
    </div>
    @endif

    @yield('content')

  </main>

</div>

@yield('scripts')
@stack('scripts')

<script>
(function () {
  const toggle = document.querySelector('[data-admin-sidebar-toggle]');
  const sidebar = document.getElementById('admin-sidebar');
  const overlay = document.querySelector('[data-admin-sidebar-overlay]');

  if (!toggle || !sidebar || !overlay) {
    return;
  }

  const focusableSelector = 'a[href], button:not([disabled]), summary, input, select, textarea, [tabindex]:not([tabindex="-1"])';
  let lastFocused = null;

  function sidebarFocusables() {
    return Array.from(sidebar.querySelectorAll(focusableSelector))
      .filter((element) => element.offsetParent !== null);
  }

  function setSidebarOpen(open) {
    sidebar.classList.toggle('open', open);
    overlay.hidden = !open;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle.setAttribute('aria-label', open ? 'Chiudi menu amministrazione' : 'Apri menu amministrazione');
    document.body.classList.toggle('admin-sidebar-is-open', open);

    if (open) {
      lastFocused = document.activeElement;
      (sidebarFocusables()[0] || toggle).focus();
      return;
    }

    if (lastFocused && document.contains(lastFocused)) {
      lastFocused.focus();
    }
  }

  toggle.addEventListener('click', () => setSidebarOpen(!sidebar.classList.contains('open')));
  overlay.addEventListener('click', () => setSidebarOpen(false));

  document.addEventListener('keydown', (event) => {
    if (!sidebar.classList.contains('open')) {
      return;
    }

    if (event.key === 'Escape') {
      event.preventDefault();
      setSidebarOpen(false);
      return;
    }

    if (event.key !== 'Tab') {
      return;
    }

    const focusable = [toggle, ...sidebarFocusables()];
    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    }

    if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  window.matchMedia('(min-width: 901px)').addEventListener('change', (event) => {
    if (event.matches) {
      setSidebarOpen(false);
    }
  });
})();
</script>

</body>
</html>
