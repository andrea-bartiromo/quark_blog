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
<div class="admin-layout">

  <aside class="admin-sidebar">

    <div class="admin-sidebar__brand">
      <div class="admin-sidebar__logo">
        Quark<span class="dot">.</span>
      </div>
      <span class="admin-sidebar__sub">Pannello redazionale</span>
    </div>

    <nav class="admin-nav">

      <span class="admin-nav__section">Contenuti</span>

      <a href="{{ route('admin.dashboard') }}"
         @class(['active' => request()->routeIs('admin.dashboard')])>
        <span class="icon">📊</span> Dashboard
      </a>

      <a href="{{ route('admin.articles') }}"
         @class(['active' => request()->routeIs('admin.articles*')])>
        <span class="icon">📝</span> Articoli
      </a>

      <a href="{{ route('admin.turing') }}"
         @class(['active' => request()->routeIs('admin.turing*')])>
        <span class="icon">🧠</span> Speciale Turing
      </a>

      <a href="{{ route('admin.categories') }}"
         @class(['active' => request()->routeIs('admin.categories*')])>
        <span class="icon">🏷️</span> Categorie
      </a>

      <a href="{{ route('admin.comments') }}"
         @class(['active' => request()->routeIs('admin.comments*')])>
        <span class="icon">💬</span> Commenti
      </a>

      <a href="{{ route('admin.newsletter') }}"
         @class(['active' => request()->routeIs('admin.newsletter')])>
        <span class="icon">✉️</span> Newsletter
      </a>

      <span class="admin-nav__section">Gestione</span>

      <a href="{{ route('admin.media') }}"
         @class(['active' => request()->routeIs('admin.media*')])>
        <span class="icon">🖼</span> Media
      </a>

      <a href="{{ route('admin.ads') }}"
         @class(['active' => request()->routeIs('admin.ads*')])>
        <span class="icon">📢</span> Pubblicità
      </a>

      <a href="{{ route('admin.collaborators') }}"
         @class(['active' => request()->routeIs('admin.collaborators*')])>
        <span class="icon">👥</span> Collaboratori
      </a>

      <a href="{{ route('admin.review') }}"
         @class(['active' => request()->routeIs('admin.review*')])>
        <span class="icon">📋</span> Revisione
      </a>

      <a href="{{ route('admin.verification') }}"
         @class(['active' => request()->routeIs('admin.verification')])>
        <span class="icon">✅</span> Verifica fonti
      </a>

      <a href="{{ route('admin.suggestions') }}"
         @class(['active' => request()->routeIs('admin.suggestions*')])>
        <span class="icon">🤖</span> Suggerimenti AI
      </a>

      <span class="admin-nav__section">Analytics</span>

      <a href="{{ route('admin.stats') }}"
         @class(['active' => request()->routeIs('admin.stats')])>
        <span class="icon">📈</span> Statistiche
      </a>

      <a href="{{ route('admin.activity') }}"
         @class(['active' => request()->routeIs('admin.activity')])>
        <span class="icon">🕐</span> Log attività
      </a>

      <a href="{{ route('admin.newsletter.preview') }}"
         @class(['active' => request()->routeIs('admin.newsletter.preview')])>
        <span class="icon">👁</span> Anteprima newsletter
      </a>

      <span class="admin-nav__section">Account</span>

      <a href="{{ route('admin.profile') }}"
         @class(['active' => request()->routeIs('admin.profile*')])>
        <span class="icon">👤</span> Profilo
      </a>

      <a href="{{ route('home') }}" target="_blank">
        <span class="icon">🌐</span> Vedi sito
      </a>

      <a href="#" onclick="event.preventDefault();document.getElementById('logout-form').submit();">
        <span class="icon">🚪</span> Esci
      </a>

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

</body>
</html>
