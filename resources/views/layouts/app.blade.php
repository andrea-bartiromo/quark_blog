{{-- Quark — Blog di divulgazione scientifica --}}
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>@yield('title', config('laboratorio.name').' — '.config('laboratorio.tagline'))</title>
  <meta name="description" content="@yield('description', config('laboratorio.description'))">

  <meta name="csrf-token" content="{{ csrf_token() }}">

  {{-- Open Graph --}}
  <meta property="og:site_name" content="{{ config('laboratorio.name') }}">
  <meta property="og:type" content="@yield('og_type','website')">
  <meta property="og:title" content="@yield('title')">
  <meta property="og:description" content="@yield('description')">
  <meta property="og:url" content="{{ url()->current() }}">

  {{-- Google Analytics 4 --}}
  {{--
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('consent', 'default', {
      analytics_storage: 'denied',
      ad_storage: 'denied',
      wait_for_update: 500
    });
    gtag('js', new Date());
    gtag('config', 'G-XXXXXXXXXX', { anonymize_ip: true });
  </script>
  --}}

  {{-- Google AdSense --}}
  {{-- <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-XXXXXXXXXXXXXXXXX" crossorigin="anonymous"></script> --}}

  {{-- Fonts + CSS --}}
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Fraunces:ital,wght@0,700;0,900;1,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  <link rel="stylesheet" href="{{ asset('css/home-premium.css') }}">
  <link rel="stylesheet" href="{{ asset('css/home-fix.css') }}">
  <link rel="stylesheet" href="{{ asset('css/public-premium.css') }}">
  <link rel="stylesheet" href="{{ asset('css/public-unified.css') }}">
  <link rel="stylesheet" href="{{ asset('css/premium-fixes.css') }}">

  @yield('head')
</head>

<body>

@include('components.header')
@include('components.ticker')
@include('components.category-bar')

@if(request('newsletter') === 'ok')
<div id="newsletter-alert" style="max-width:1200px;margin:1rem auto 0;padding:.85rem 1.25rem;background:#d1fae5;color:#065f46;border:1px solid #a7f3d0;border-radius:10px;font-size:.9rem;font-weight:600;">
  ✅ Iscrizione ricevuta! Controlla la tua email per confermare.
</div>
@endif

@if(isset($errors) && $errors->has('email'))
<div id="newsletter-alert" style="max-width:1200px;margin:1rem auto 0;padding:.85rem 1.25rem;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:10px;font-size:.9rem;font-weight:600;">
  ❌ {{ $errors->first('email') }}
</div>
@endif

<main>
  @yield('content')
</main>

@include('components.footer')
@include('components.cookie-bar')
@include('components.newsletter-popup')

<script>
document.addEventListener("DOMContentLoaded", () => {
  const popup = document.getElementById("newsletter-popup");
  const closeBtn = document.getElementById("newsletter-popup-close");
  const overlay = document.getElementById("newsletter-popup-overlay");

  const alert = document.getElementById("newsletter-alert");
  if (alert) {
    setTimeout(() => {
      alert.style.transition = "opacity .5s";
      alert.style.opacity = "0";
      setTimeout(() => alert.remove(), 500);
    }, 5000);
  }

  if (!popup) return;

  const dismissed = localStorage.getItem('newsletter_dismissed');
  const subscribed = localStorage.getItem('newsletter_subscribed');

  if (!dismissed && !subscribed) {
    setTimeout(() => {
      popup.classList.add("visible");
    }, 30000);
  }

  function closePopup() {
    popup.classList.remove("visible");
    const expires = Date.now() + 7 * 24 * 60 * 60 * 1000;
    localStorage.setItem('newsletter_dismissed', expires);
  }

  if (closeBtn) closeBtn.addEventListener("click", closePopup);
  if (overlay) overlay.addEventListener("click", closePopup);
  document.addEventListener("keydown", e => {
    if (e.key === "Escape") closePopup();
  });

  @if(request('newsletter') === 'ok')
  localStorage.setItem('newsletter_subscribed', '1');
  @endif

  const dismissedUntil = localStorage.getItem('newsletter_dismissed');
  if (dismissedUntil && Date.now() > parseInt(dismissedUntil)) {
    localStorage.removeItem('newsletter_dismissed');
  }
});
</script>

@stack('scripts')

</body>
</html>
