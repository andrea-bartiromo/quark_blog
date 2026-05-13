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

  function gtag(){
    dataLayer.push(arguments);
  }

  gtag('consent', 'default', {
    analytics_storage: 'denied',
    ad_storage: 'denied',
    wait_for_update: 500
  });

  gtag('js', new Date());
  gtag('config', 'G-XXXXXXXXXX', {
    anonymize_ip: true
  });
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
<link rel="stylesheet" href="{{ asset('css/turing-overrides.css') }}">

@yield('head')
