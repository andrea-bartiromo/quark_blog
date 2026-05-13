{{-- Quark — Blog di divulgazione scientifica --}}
<!DOCTYPE html>
<html lang="it">
<head>
  @include('layouts.partials.head')
</head>

<body>

@include('components.header')
@include('components.ticker')
@include('components.category-bar')
@include('components.newsletter-alert')

<main>
  @yield('content')
</main>

@include('components.footer')
@include('components.cookie-bar')
@include('components.newsletter-popup')

@include('layouts.partials.newsletter-scripts')

@stack('scripts')

</body>
</html>
