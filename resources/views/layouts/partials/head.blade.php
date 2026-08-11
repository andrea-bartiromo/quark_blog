<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>@yield('title', config('laboratorio.name').' — '.config('laboratorio.tagline'))</title>

<meta name="description" content="@yield('description', config('laboratorio.description'))">
<meta name="robots" content="@yield('robots', 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1')">

@hasSection('canonical')
{{-- Canonical sections are already escaped by Blade's two-argument @section. --}}
<link rel="canonical" href="@yield('canonical')">
@endif

<link rel="alternate" type="application/rss+xml" title="{{ config('laboratorio.name') }}" href="{{ route('feed') }}">
<meta name="csrf-token" content="{{ csrf_token() }}">

<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/svg+xml" href="{{ asset('assets/icons/favicon.svg') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/icons/favicon-32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('assets/icons/favicon-16.png') }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<meta name="apple-mobile-web-app-title" content="{{ config('laboratorio.name') }}">
<meta name="application-name" content="{{ config('laboratorio.name') }}">
<meta name="theme-color" content="#111827">

<meta property="og:site_name" content="{{ config('laboratorio.name') }}">
<meta property="og:type" content="@yield('og_type', 'website')">
<meta property="og:title" content="{!! $__env->hasSection('og_title') ? $__env->yieldContent('og_title') : $__env->yieldContent('title') !!}">
<meta property="og:description" content="{!! $__env->hasSection('og_description') ? $__env->yieldContent('og_description') : $__env->yieldContent('description') !!}">
@hasSection('canonical')
<meta property="og:url" content="@yield('canonical')">
@else
<meta property="og:url" content="{{ url()->current() }}">
@endif

@php
    $ogImage = trim((string) ($__env->hasSection('og_image') ? $__env->yieldContent('og_image') : ''));
    $ogImage = $ogImage !== '' ? $ogImage : asset(config('laboratorio.default_share_image'));
    $twitterImage = trim((string) ($__env->hasSection('twitter_image') ? $__env->yieldContent('twitter_image') : ''));
    $twitterImage = $twitterImage !== '' ? $twitterImage : $ogImage;
@endphp
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">

<meta name="twitter:card" content="@yield('twitter_card', 'summary_large_image')">
<meta name="twitter:title" content="{!! $__env->hasSection('twitter_title') ? $__env->yieldContent('twitter_title') : $__env->yieldContent('title') !!}">
<meta name="twitter:description" content="{!! $__env->hasSection('twitter_description') ? $__env->yieldContent('twitter_description') : $__env->yieldContent('description') !!}">
<meta name="twitter:image" content="{{ $twitterImage }}">

@php
    $shouldLoadAnalytics = app(\App\Services\AnalyticsExclusionService::class)->shouldLoadAnalytics(request());
@endphp
@if($shouldLoadAnalytics)
<script async src="https://www.googletagmanager.com/gtag/js?id={{ config('analytics.measurement_id') }}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag() { dataLayer.push(arguments); }
gtag('consent', 'default', {
    analytics_storage: 'denied',
    ad_storage: 'denied',
    ad_user_data: 'denied',
    ad_personalization: 'denied',
    wait_for_update: 500
});
gtag('js', new Date());
gtag('config', '{{ config('analytics.measurement_id') }}', { anonymize_ip: true });
</script>
@endif

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Fraunces:ital,wght@0,700;0,900;1,700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="{{ \App\Support\VersionedAsset::url('css/style.css') }}">
<link rel="stylesheet" href="{{ \App\Support\VersionedAsset::url('css/frontend-hardening.css') }}">
@yield('home_css')
<link rel="stylesheet" href="{{ \App\Support\VersionedAsset::url('css/public-premium.css') }}">
<link rel="stylesheet" href="{{ \App\Support\VersionedAsset::url('css/public-unified.css') }}">
<link rel="stylesheet" href="{{ \App\Support\VersionedAsset::url('css/premium-fixes.css') }}">

@yield('head')