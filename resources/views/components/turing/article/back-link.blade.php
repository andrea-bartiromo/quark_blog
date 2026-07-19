@props([
  'href' => null,
  'label' => 'Torna allo speciale',
])

@php
  $url = $href ?: route('turing');
@endphp

<nav {{ $attributes->merge(['class' => 'turing-actions turing-actions--center']) }} aria-label="Navigazione Turing">
  <a href="{{ $url }}">{{ $label }}</a>
</nav>
