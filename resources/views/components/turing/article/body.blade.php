<main {{ $attributes->merge(['class' => 'turing-section']) }}>
  <div class="container container--wide">
    <article class="turing-copy-panel">
      {{ $slot }}
    </article>
  </div>
</main>
