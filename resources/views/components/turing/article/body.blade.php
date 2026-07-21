{{-- Wrapper 'div', non 'main': layouts/app.blade.php avvolge gia' @yield('content')
     in un <main> proprio. Un secondo <main> qui annidato sarebbe HTML non
     valido e un landmark duplicato per la lettura assistiva. --}}
<div {{ $attributes->merge(['class' => 'turing-section']) }}>
  <div class="container container--wide">
    <article class="turing-copy-panel">
      {{ $slot }}
    </article>
  </div>
</div>
