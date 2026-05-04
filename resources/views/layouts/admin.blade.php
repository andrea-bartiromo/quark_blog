{{-- Il Laboratorio — CMS Admin
     Fondatore e Direttore Responsabile: Andrea Bartiromo
     https://www.illaboratorio.it --}}
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>@yield('title','Admin') — Il Laboratorio</title>

  {{-- Fonts --}}
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Fraunces:ital,wght@0,700;0,900;1,700&display=swap" rel="stylesheet">

  {{-- CSS Admin --}}
  <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ time() }}">

  <meta name="robots" content="noindex,nofollow">
</head>

<body class="admin-body">

<div class="admin-layout">

  {{-- Sidebar --}}
  <aside class="admin-sidebar">

    <div class="admin-sidebar__brand">
      <div class="admin-sidebar__logo">
        <span style="font-family:var(--font-display);font-weight:900;color:white;">
          Quark<span style="color:var(--admin-primary,#0d9488);">.</span>
        </span>
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

      <a href="{{ route('admin.comments') }}"
         @class(['active' => request()->routeIs('admin.comments*')])>
        <span class="icon">💬</span> Commenti
      </a>

      <a href="{{ route('admin.newsletter') }}"
         @class(['active' => request()->routeIs('admin.newsletter')])>
        <span class="icon">✉️</span> Newsletter
      </a>

      <span class="admin-nav__section">Sistema</span>

      <a href="{{ route('home') }}" target="_blank">
        <span class="icon">🌐</span> Vedi sito
      </a>

      <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
        <span class="icon">🚪</span> Esci
      </a>

      <form id="logout-form" action="{{ route('admin.logout') }}" method="POST" style="display:none">
        @csrf
      </form>

    </nav>

    <div class="admin-sidebar__user">
      <div>
        <span class="admin-sidebar__user-name">{{ auth()->user()->name }}</span>
        <span class="admin-sidebar__user-role">{{ auth()->user()->role }}</span>
      </div>
    </div>

  </aside>

  {{-- Contenuto principale --}}
  <main class="admin-main">

    @if(session('success'))
      <div style="
        background:#e8f5e9;
        border:1px solid #a5d6a7;
        border-radius:6px;
        padding:.75rem 1rem;
        margin-bottom:1rem;
        font-family:var(--font-ui);
        font-size:.85rem;
        color:#2e7d32;
      ">
        {{ session('success') }}
      </div>
    @endif

    @yield('content')

  </main>

</div>

{{-- 🔥 QUI VANNO GLI SCRIPT (TinyMCE ecc.) --}}
@yield('scripts')
@stack('scripts')

</body>
</html>