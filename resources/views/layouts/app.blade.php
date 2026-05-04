{{-- Quark — Blog di divulgazione scientifica
     Fondatore: Andrea Bartiromo
     https://quark.blog --}}
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', config('laboratorio.name').' — '.config('laboratorio.tagline'))</title>
  <meta name="description" content="@yield('description', config('laboratorio.description', 'Quark è il blog di divulgazione scientifica che spiega la scienza in modo semplice e curioso.'))">
  <meta name="author" content="Andrea Bartiromo">
  <meta name="copyright" content="© {{ date('Y') }} Andrea Bartiromo — Quark">
  <meta name="publisher" content="Quark">
  <link rel="canonical" href="{{ url()->current() }}">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  {{-- Open Graph --}}
  <meta property="og:site_name" content="{{ config('laboratorio.name') }}">
  <meta property="og:type"      content="@yield('og_type','website')">
  <meta property="og:title"     content="@yield('title', config('laboratorio.name'))">
  <meta property="og:description" content="@yield('description', config('laboratorio.tagline'))">
  <meta property="og:url"       content="{{ url()->current() }}">
  @yield('og_image')
  <meta name="twitter:card" content="summary_large_image">

  {{-- Favicon --}}
  <link rel="icon" type="image/svg+xml" href="{{ asset('assets/icons/favicon.svg') }}">

  {{-- Feed RSS --}}
  <link rel="alternate" type="application/rss+xml"
        title="{{ config('laboratorio.name') }} — Feed RSS"
        href="{{ route('feed') }}">

  {{-- Fonts + CSS --}}
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Fraunces:ital,wght@0,700;0,900;1,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">

  {{-- Google AdSense
       Sostituire ca-pub-XXXXXXXXXXXXXXXXX con il tuo Publisher ID
       ottenuto da https://adsense.google.com
       Decommentare quando il sito è online con dominio reale
  --}}
  {{-- <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-XXXXXXXXXXXXXXXXX" crossorigin="anonymous"></script> --}}

  @yield('head')
</head>
<body>

@include('components.header')
@include('components.ticker')
@include('components.category-bar')

<main>
  @yield('content')
</main>

@include('components.footer')
@include('components.cookie-bar')
@include('components.newsletter-popup')

@stack('scripts')
</body>
</html>