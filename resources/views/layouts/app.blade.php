{{-- Kairus — Blog di divulgazione scientifica --}}
<!DOCTYPE html>
<html lang="it">
<head>
  @include('layouts.partials.head')
</head>

<body>

<a href="#main-content" class="skip-link">Vai al contenuto principale</a>

@include('components.header')
@include('components.ticker')
@include('components.category-bar')
@include('components.newsletter-alert')

<main id="main-content" tabindex="-1">
  @yield('content')
</main>

@include('components.footer')
@include('components.cookie-bar')
@include('components.newsletter-popup')

@include('layouts.partials.newsletter-scripts')

@stack('scripts')

</body>
</html>
