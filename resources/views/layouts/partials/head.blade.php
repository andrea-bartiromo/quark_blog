<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>@yield('title', config('laboratorio.name').' — '.config('laboratorio.tagline'))</title>

<meta name="description" content="@yield('description', config('laboratorio.description'))">
<meta name="robots" content="@yield('robots', 'index,follow')">

@hasSection('canonical')
<link rel="canonical" href="@yield('canonical')">
@endif

<meta name="csrf-token" content="{{ csrf_token() }}">

{{-- Icone --}}
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/svg+xml" href="{{ asset('assets/icons/favicon.svg') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/icons/favicon-32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('assets/icons/favicon-16.png') }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">

<meta name="apple-mobile-web-app-title" content="{{ config('laboratorio.name') }}">
<meta name="application-name" content="{{ config('laboratorio.name') }}">
<meta name="theme-color" content="#111827">

{{-- Open Graph --}}
<meta property="og:site_name" content="{{ config('laboratorio.name') }}">
<meta property="og:type" content="@yield('og_type', 'website')">

<meta
    property="og:title"
    content="{!! $__env->hasSection('og_title')
        ? $__env->yieldContent('og_title')
        : $__env->yieldContent('title') !!}"
>

<meta
    property="og:description"
    content="{!! $__env->hasSection('og_description')
        ? $__env->yieldContent('og_description')
        : $__env->yieldContent('description') !!}"
>

<meta property="og:url" content="{{ url()->current() }}">

@hasSection('og_image')
<meta property="og:image" content="@yield('og_image')">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
@endif

{{-- Twitter --}}
<meta name="twitter:card" content="@yield('twitter_card', 'summary_large_image')">

<meta
    name="twitter:title"
    content="{!! $__env->hasSection('twitter_title')
        ? $__env->yieldContent('twitter_title')
        : $__env->yieldContent('title') !!}"
>

<meta
    name="twitter:description"
    content="{!! $__env->hasSection('twitter_description')
        ? $__env->yieldContent('twitter_description')
        : $__env->yieldContent('description') !!}"
>

@hasSection('twitter_image')
<meta name="twitter:image" content="@yield('twitter_image')">
@endif

{{-- Google Analytics --}}
<script async src="https://www.googletagmanager.com/gtag/js?id=G-Y1853N6FZP"></script>

<script>
window.dataLayer = window.dataLayer || [];

function gtag() {
    dataLayer.push(arguments);
}

gtag('consent', 'default', {
    analytics_storage: 'denied',
    ad_storage: 'denied',
    ad_user_data: 'denied',
    ad_personalization: 'denied',
    wait_for_update: 500
});

gtag('js', new Date());

gtag('config', 'G-Y1853N6FZP', {
    anonymize_ip: true
});
</script>

{{-- Google AdSense --}}
{{--
<script
    async
    src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-XXXXXXXXXXXXXXXX"
    crossorigin="anonymous">
</script>
--}}

{{-- Google Fonts --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link
    href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Fraunces:ital,wght@0,700;0,900;1,700&display=swap"
    rel="stylesheet"
>

{{-- CSS --}}
<link rel="stylesheet" href="{{ asset('css/style.css') }}">
<link rel="stylesheet" href="{{ asset('css/home-premium.css') }}">
<link rel="stylesheet" href="{{ asset('css/home-fix.css') }}">
<link rel="stylesheet" href="{{ asset('css/public-premium.css') }}">
<link rel="stylesheet" href="{{ asset('css/public-unified.css') }}">
<link rel="stylesheet" href="{{ asset('css/premium-fixes.css') }}?v=10">

@yield('head')