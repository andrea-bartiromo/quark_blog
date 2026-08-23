{{-- Kairus — CMS Admin
     Fondatore: Andrea Bartiromo --}}
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title','Admin') — Kairus</title>
  <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
  <link rel="icon" type="image/svg+xml" href="{{ asset('assets/icons/favicon.svg') }}">
  <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Fraunces:ital,wght@0,700;0,900;1,700&display=swap" rel="stylesheet">
  {{--
      Cache-busting basato su filemtime(), non un numero di versione
      manuale da ricordare di incrementare a ogni modifica (stesso
      principio già in uso per premium-fixes.css?v=10 nel layout
      pubblico, ma automatico qui): senza questo, un browser che aveva
      già scaricato admin.css PRIMA di una modifica a questo file (es.
      l'introduzione della sidebar comprimibile) può continuare a
      servire la versione in cache invariata per giorni — il body
      riceve comunque la classe admin-sidebar-compact dal pulsante di
      collasso (il JavaScript funziona), ma senza le regole CSS che le
      danno un effetto visibile: il controllo sembra "non fare nulla"
      pur funzionando correttamente. Root cause verificata empiricamente
      con un browser reale, non assunta — vedi docs/ADMIN_SIDEBAR.md.
  --}}
  <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ is_file(public_path('css/admin.css')) ? filemtime(public_path('css/admin.css')) : 1 }}">
  <meta name="robots" content="noindex,nofollow">
  {{-- Namespace delle bozze locali dell'editor articoli (autosave/
       recovery) — mai dati sensibili, solo l'id numerico già visibile
       altrove nell'interfaccia admin. Vedi docs/editor-autosave.md. --}}
  <meta name="kairus-user-id" content="{{ auth()->id() }}">
</head>

<body class="admin-body admin-has-sidebar-toggle">
<script>
  // Applica la preferenza "sidebar compatta" prima del primo paint, cosi
  // da evitare un lampo con la sidebar estesa che poi si restringe. Se
  // localStorage non e' disponibile (privacy mode, ecc.) la sidebar resta
  // semplicemente estesa, comportamento identico a prima di questa
  // funzionalita': nessuna navigazione dipende da questo script.
  (function () {
    try {
      if (window.localStorage && localStorage.getItem('kairus-admin-sidebar-compact') === '1') {
        document.body.classList.add('admin-sidebar-compact');
      }
    } catch (e) {}
  })();
</script>
<a href="#admin-main-content" class="skip-link">Vai al contenuto principale</a>
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
        Kairus<span class="dot">.</span>
      </div>
      <span class="admin-sidebar__sub">Pannello redazionale</span>
    </div>

    {{-- Solo desktop (nascosto sotto i 901px via CSS): la sidebar mobile
         e' gia' un drawer a scomparsa, comprimerla non avrebbe senso. --}}
    <button type="button"
            class="admin-sidebar-compact-toggle"
            aria-pressed="false"
            aria-label="Comprimi la sidebar"
            data-admin-sidebar-compact-toggle>
      <span aria-hidden="true">«</span>
    </button>

    @php
      $isDashboard     = request()->routeIs('admin.dashboard');
      $isArticles      = request()->routeIs('admin.articles*');
      $isCategories    = request()->routeIs('admin.categories*');
      $isMedia         = request()->routeIs('admin.media*');
      $isComments      = request()->routeIs('admin.comments*');
      $isContentClusters = request()->routeIs('admin.content-clusters.*');
      $isReview        = request()->routeIs('admin.review*');
      $isVerification  = request()->routeIs('admin.verification');
      $isCollaborators = request()->routeIs('admin.collaborators*');
      $isProgettazioneDashboard = request()->routeIs('admin.progettazione.dashboard');
      $isProgettazioneProjects  = request()->routeIs('admin.progettazione.projects.*');
      $isProgettazioneTasks     = request()->routeIs('admin.progettazione.tasks.index-all');
      $isProgettazioneCalendar  = request()->routeIs('admin.progettazione.calendar');
      $isProgettazioneDocuments = request()->routeIs('admin.progettazione.documents.index-all');
      $isNewsletter    = request()->routeIs('admin.newsletter')
                          || request()->routeIs('admin.newsletter.export')
                          || request()->routeIs('admin.newsletter.send-now');
      $isComunicazioneDashboard = request()->routeIs('admin.comunicazione.dashboard');
      $isComunicazioneCampaigns = request()->routeIs('admin.comunicazione.campaigns.*');
      $isComunicazioneTemplates = request()->routeIs('admin.comunicazione.templates.*');
      $isComunicazioneSenderProfiles = request()->routeIs('admin.comunicazione.sender-profiles.*');
      $isAds           = request()->routeIs('admin.ads*');
      $isTuring        = request()->routeIs('admin.turing*');
      $isSuggestions   = request()->routeIs('admin.suggestions*');
      $isStats         = request()->routeIs('admin.stats*');
      $isActivity      = request()->routeIs('admin.activity');
      $isNewsletterPrev = request()->routeIs('admin.newsletter.preview');
      $isProfile       = request()->routeIs('admin.profile*');

      // Un gruppo si apre da solo quando la pagina corrente gli appartiene
      // (calcolato lato server, corretto al primo render — vedi
      // components/admin/nav-group.blade.php). Dashboard e Account restano
      // sempre visibili, non raggruppati: sono i due ancoraggi che l'utente
      // deve poter raggiungere senza aprire nulla (FASE 5.2 della missione
      // IA sidebar).
      $contenutiOpen = $isArticles || $isCategories || $isMedia || $isComments || $isContentClusters;
      $redazioneOpen = $isReview || $isVerification || $isCollaborators;
      $progettazioneOpen = $isProgettazioneDashboard || $isProgettazioneProjects
                          || $isProgettazioneTasks || $isProgettazioneCalendar || $isProgettazioneDocuments;
      $comunicazioneOpen = $isComunicazioneDashboard || $isComunicazioneCampaigns
                          || $isComunicazioneTemplates || $isComunicazioneSenderProfiles
                          || $isNewsletter || $isNewsletterPrev;
      $strumentiOpen = $isTuring || $isSuggestions;
      $monetizzazioneOpen = $isAds;
      $analisiOpen = $isStats;
      $sistemaOpen = $isActivity;
    @endphp

    <nav class="admin-nav" aria-label="Navigazione amministrazione">

      <span class="admin-nav__section">Principale</span>

      <x-admin.nav-link :route="route('admin.dashboard')" :active="$isDashboard" icon="📊">Dashboard</x-admin.nav-link>

      <x-admin.nav-group label="Contenuti" :open="$contenutiOpen">
        <x-admin.nav-link :route="route('admin.articles')" :active="$isArticles" icon="📝">Articoli</x-admin.nav-link>
        <x-admin.nav-link :route="route('admin.categories')" :active="$isCategories" icon="🏷️">Categorie</x-admin.nav-link>
        <x-admin.nav-link :route="route('admin.media')" :active="$isMedia" icon="🖼️">Media</x-admin.nav-link>
        <x-admin.nav-link :route="route('admin.comments')" :active="$isComments" icon="💬">Commenti</x-admin.nav-link>
        <x-admin.nav-link :route="route('admin.content-clusters.index')" :active="$isContentClusters" icon="🧭">Percorsi</x-admin.nav-link>
      </x-admin.nav-group>

      <x-admin.nav-group label="Redazione" :open="$redazioneOpen">
        <x-admin.nav-link :route="route('admin.review')" :active="$isReview" icon="📋">Revisione</x-admin.nav-link>
        <x-admin.nav-link :route="route('admin.verification')" :active="$isVerification" icon="✅">Fonti</x-admin.nav-link>
        <x-admin.nav-link :route="route('admin.collaborators')" :active="$isCollaborators" icon="👥">Collaboratori</x-admin.nav-link>
      </x-admin.nav-group>

      <x-admin.nav-group label="Progettazione" :open="$progettazioneOpen">
        <x-admin.nav-link :route="route('admin.progettazione.dashboard')" :active="$isProgettazioneDashboard" icon="🗂️">Panoramica</x-admin.nav-link>
        <x-admin.nav-link :route="route('admin.progettazione.projects.index')" :active="$isProgettazioneProjects" icon="📁">Progetti</x-admin.nav-link>
        <x-admin.nav-link :route="route('admin.progettazione.tasks.index-all')" :active="$isProgettazioneTasks" icon="✔️">Attività progetti</x-admin.nav-link>
        <x-admin.nav-link :route="route('admin.progettazione.calendar')" :active="$isProgettazioneCalendar" icon="🗓️">Calendario</x-admin.nav-link>
        <x-admin.nav-link :route="route('admin.progettazione.documents.index-all')" :active="$isProgettazioneDocuments" icon="📄">Documenti</x-admin.nav-link>
      </x-admin.nav-group>

      <x-admin.nav-group label="Comunicazione" :open="$comunicazioneOpen">
        <x-admin.nav-link :route="route('admin.comunicazione.dashboard')" :active="$isComunicazioneDashboard" icon="📡">Dashboard</x-admin.nav-link>
        <x-admin.nav-link :route="route('admin.comunicazione.campaigns.index')" :active="$isComunicazioneCampaigns" icon="📨">Campagne</x-admin.nav-link>
        <x-admin.nav-link :route="route('admin.comunicazione.templates.index')" :active="$isComunicazioneTemplates" icon="🧩">Template</x-admin.nav-link>
        <x-admin.nav-link :route="route('admin.comunicazione.sender-profiles.index')" :active="$isComunicazioneSenderProfiles" icon="📤">Mittenti</x-admin.nav-link>
        <x-admin.nav-link :route="route('admin.newsletter')" :active="$isNewsletter" icon="✉️">Newsletter</x-admin.nav-link>
        <x-admin.nav-link :route="route('admin.newsletter.preview')" :active="$isNewsletterPrev" icon="👁️">Anteprima newsletter</x-admin.nav-link>
      </x-admin.nav-group>

      <x-admin.nav-group label="Strumenti" :open="$strumentiOpen">
        <x-admin.nav-link :route="route('admin.turing')" :active="$isTuring" icon="🧠">Turing</x-admin.nav-link>
        <x-admin.nav-link :route="route('admin.suggestions')" :active="$isSuggestions" icon="🤖">Assistente AI</x-admin.nav-link>
      </x-admin.nav-group>

      <x-admin.nav-group label="Monetizzazione" :open="$monetizzazioneOpen">
        <x-admin.nav-link :route="route('admin.ads')" :active="$isAds" icon="📢">Pubblicità</x-admin.nav-link>
      </x-admin.nav-group>

      <x-admin.nav-group label="Analisi" :open="$analisiOpen">
        <x-admin.nav-link :route="route('admin.stats')" :active="$isStats" icon="📈">Statistiche</x-admin.nav-link>
      </x-admin.nav-group>

      <x-admin.nav-group label="Sistema" :open="$sistemaOpen">
        <x-admin.nav-link :route="route('admin.activity')" :active="$isActivity" icon="🕐">Attività</x-admin.nav-link>
      </x-admin.nav-group>

      <span class="admin-nav__section">Account</span>

      <x-admin.nav-link :route="route('admin.profile')" :active="$isProfile" icon="👤">Profilo</x-admin.nav-link>
      <x-admin.nav-link :route="route('home')" :external="true" icon="🌐">Vedi sito</x-admin.nav-link>

      <button type="submit" form="logout-form" class="admin-nav__logout-btn" title="Esci">
        <span class="icon" aria-hidden="true">🚪</span>
        <span class="admin-nav__label">Esci</span>
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

  <main class="admin-main" id="admin-main-content" tabindex="-1">

    {{--
        Scorciatoia "vai a un articolo" da qualunque pagina admin — nascosta
        SOLO sulla pagina Articoli stessa (match esatto sulla route
        'admin.articles', non sul gruppo 'admin.articles*' usato più sopra
        per evidenziare la voce di menu, che include anche nuovo/modifica):
        lì la toolbar dedicata (ricerca + stato + categoria + autore) copre
        già lo stesso scopo con un'etichetta propria — due caselle "Cerca"
        sulla stessa pagina, entrambe verso lo stesso parametro "q", sarebbero
        ridondanti e confuse. Su ogni altra pagina admin il comportamento
        resta invariato.
    --}}
    @unless(request()->routeIs('admin.articles'))
    <div style="margin-bottom:1.25rem;" x-data>
      <form method="GET" action="{{ route('admin.articles') }}"
            style="display:flex;gap:.5rem;max-width:400px;">
        <input type="text" name="q"
               value="{{ request('q') }}"
               placeholder="🔍 Cerca articoli..."
               aria-label="Cerca articoli per titolo, sommario o testo"
               style="flex:1;padding:.45rem .75rem;border:1px solid #e5e7eb;
                      border-radius:6px;font-size:.82rem;font-family:inherit;
                      background:#fff;">
        <button type="submit" class="btn btn--secondary btn--sm">Cerca</button>
      </form>
    </div>
    @endunless

    @if(session('success'))
    <div id="kairus-flash-success"
         style="background:#d1fae5;border:1px solid #6ee7b7;border-radius:8px;
                padding:.75rem 1rem;margin-bottom:1rem;font-size:.875rem;color:#065f46;">
      ✅ {{ session('success') }}
    </div>
    @endif
    {{-- EDITORIAL RESILIENCE: la pulizia della bozza locale NON deve
         dipendere dal flash 'success' generico (condiviso da ogni
         controller admin) — solo da un marcatore dedicato che
         ArticleController::store()/update() setta esplicitamente.
         Altrimenti un'azione admin qualunque non correlata (upload
         media, modifica categoria, ecc.) svuoterebbe comunque la bozza
         "nuovo articolo" di questo utente. --}}
    @if(session()->has('kairus_draft_cleanup_context'))
    <div id="kairus-draft-cleanup-marker"
         data-editor-surface="admin"
         data-draft-cleanup-context="{{ session('kairus_draft_cleanup_context') }}"
         hidden></div>
    @include('partials.kairus-draft-cleanup-script')
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

(function () {
  const compactToggle = document.querySelector('[data-admin-sidebar-compact-toggle]');
  const STORAGE_KEY = 'kairus-admin-sidebar-compact';

  if (!compactToggle) {
    return;
  }

  // Stato open/closed di ogni gruppo immediatamente prima di forzarli
  // tutti aperti per la modalita' compatta (vedi sotto): senza
  // ripristinarlo all'uscita, tornare alla sidebar estesa lasciava TUTTI
  // i gruppi aperti anche se in origine solo quello della pagina
  // corrente lo era — una sidebar normalmente ordinata che si ritrovava
  // improvvisamente con ogni sezione dispiegata dopo un giro in
  // modalita' compatta, senza che l'utente lo avesse chiesto.
  let groupsOpenBeforeCompact = null;

  function applyCompact(compact) {
    document.body.classList.toggle('admin-sidebar-compact', compact);
    compactToggle.setAttribute('aria-pressed', compact ? 'true' : 'false');
    compactToggle.setAttribute('aria-label', compact ? 'Espandi la sidebar' : 'Comprimi la sidebar');

    const groups = document.querySelectorAll('.admin-nav__group');

    // Senza testo ad accompagnarle, le icone non guadagnerebbero nulla
    // dall'essere dentro un gruppo "chiuso": in modalita' compatta i
    // gruppi si aprono tutti, cosi' l'utente non deve interagire con
    // nessun accordion solo per vedere le icone disponibili. Il CSS
    // (.admin-sidebar-compact .admin-nav__group:not([open])) tiene
    // comunque visibile il contenuto anche se un gruppo viene richiuso
    // per errore (es. da tastiera).
    if (compact) {
      groupsOpenBeforeCompact = Array.from(groups).map((group) => group.open);
      groups.forEach((group) => {
        group.open = true;
      });

      return;
    }

    if (groupsOpenBeforeCompact) {
      groups.forEach((group, index) => {
        group.open = groupsOpenBeforeCompact[index] ?? false;
      });
      groupsOpenBeforeCompact = null;
    }
  }

  // Lo script anti-FOUC in testa al <body> ha gia' applicato la classe
  // corretta prima del primo paint leggendo localStorage: qui ci si
  // limita a sincronizzare gli attributi accessibili del pulsante con
  // quello stato, senza rileggere localStorage una seconda volta.
  applyCompact(document.body.classList.contains('admin-sidebar-compact'));

  compactToggle.addEventListener('click', () => {
    const next = !document.body.classList.contains('admin-sidebar-compact');
    applyCompact(next);

    try {
      if (window.localStorage) {
        localStorage.setItem(STORAGE_KEY, next ? '1' : '0');
      }
    } catch (e) {}
  });
})();
</script>

</body>
</html>