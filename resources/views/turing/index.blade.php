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

@section('title', ($page->title ?? 'Alan Turing').' — Quark')
@section('description', $page->description ?? 'Una sezione speciale di Quark dedicata ad Alan Turing, alla crittografia, alla Seconda guerra mondiale e all’intelligenza artificiale moderna.')

@section('head')
<link rel="stylesheet" href="{{ asset('css/turing.css') }}">
@endsection

@section('content')
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
