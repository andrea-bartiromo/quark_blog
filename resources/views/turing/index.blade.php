@extends('layouts.app')

@php
  $normalizeMedia = function ($value) {
      if (empty($value)) return null;

      $value = trim((string) $value);
      if ($value === '') return null;

      $value = str_replace('\\', '/', $value);
      $value = preg_replace('#^.*?/public/assets/img/#', '', $value);
      $value = preg_replace('#^/?public/assets/img/#', '', $value);
      $value = preg_replace('#^/?assets/img/#', '', $value);
      $value = ltrim($value, '/');

      return $value === '' ? null : $value;
  };

  $img = function ($value) use ($normalizeMedia) {
      if (empty($value)) return null;

      $value = trim((string) $value);
      if ($value === '') return null;

      if (str_starts_with($value, 'http')) return $value;

      $normalized = $normalizeMedia($value);
      return $normalized ? asset('assets/img/'.$normalized) : null;
  };

  $bg = fn ($value) => $img($value) ? "background-image:url('".$img($value)."')" : '';

  $blockImage = function ($block) use ($sectionImageFallbacks, $normalizeMedia) {
      $key = $block['key'] ?? null;
      $image = $normalizeMedia($block['image'] ?? null);

      return $image ?: ($key && isset($sectionImageFallbacks[$key]) ? $sectionImageFallbacks[$key] : null);
  };

  $blockBackground = function ($block) use ($sectionBackgroundFallbacks, $normalizeMedia) {
      $key = $block['key'] ?? null;
      $background = $normalizeMedia($block['background_image'] ?? null);

      return $background ?: ($key && isset($sectionBackgroundFallbacks[$key]) ? $sectionBackgroundFallbacks[$key] : null);
  };
@endphp

@section('title', ($page->title ?? 'Alan Turing').' — Kairus')
@section('description', $page->description ?? 'Una sezione speciale di Kairus dedicata ad Alan Turing, alla crittografia, alla Seconda guerra mondiale e all’intelligenza artificiale moderna.')

@section('head')
{{--
    Cantiere 63 (programma "100 cantieri Kairus"): difesa in profondità,
    nel caso, remoto, in cui questa vista venga mai servita senza il gate
    auth+editor di Admin\TuringController::previewHub() — stesso principio
    già in uso per l'anteprima Percorso/Categoria (Cantieri 48/11).
--}}
@if($previewMode ?? false)<meta name="robots" content="noindex,nofollow">@endif
<link rel="stylesheet" href="{{ asset('css/turing.css') }}">
<link rel="stylesheet" href="{{ asset('css/special-project.css') }}">
@endsection

@section('content')
@if($previewMode ?? false)
{{--
    Banner di sola anteprima, visibile solo quando
    Admin\TuringController::previewHub() passa previewMode=true — mai
    sulla pagina pubblica reale (TuringPageController::index() non
    imposta mai questa variabile). Stessa convenzione già in uso in
    content-clusters/show.blade.php (Cantiere 48).
--}}
<div style="background:#fef3c7;color:#78350f;padding:.85rem 1rem;text-align:center;font-weight:700;font-size:.88rem;">
  Anteprima amministrativa — lo Speciale Turing non è ancora pubblico.
</div>
@endif
<div class="turing-page">
@include('turing.partials.hero')
@include('turing.partials.terminal-band')
@include('turing.partials.intro-section')
@include('turing.partials.editorial-blocks')
@include('turing.partials.legacy-section')
@include('turing.partials.timeline')
@include('turing.partials.final-card')
</div>
@endsection

@push('scripts')
  {{-- Controller condiviso di <x-special.modal> (Decision #009), usato dalle
       modali di approfondimento della Timeline. --}}
  <script src="{{ asset('js/special-modal.js') }}"></script>
@endpush
