@extends('layouts.app')

@php
  $img = function ($value) {
      if (empty($value)) return null;
      return str_starts_with($value, 'http') || str_starts_with($value, '/') ? $value : asset('assets/img/'.$value);
  };

  $bg = fn ($value) => $img($value) ? "background-image:url('".$img($value)."')" : '';

  $blockImage = function ($block) use ($sectionImageFallbacks) {
      $key = $block['key'] ?? null;
      $image = $block['image'] ?? null;

      return !empty($image)
          ? $image
          : ($key && isset($sectionImageFallbacks[$key]) ? $sectionImageFallbacks[$key] : null);
  };

  $blockBackground = function ($block) use ($sectionBackgroundFallbacks) {
      $key = $block['key'] ?? null;
      $background = $block['background_image'] ?? null;

      return !empty($background)
          ? $background
          : ($key && isset($sectionBackgroundFallbacks[$key]) ? $sectionBackgroundFallbacks[$key] : null);
  };
@endphp

@section('title', ($page->title ?? 'Alan Turing').' — Quark')
@section('description', $page->description ?? 'Una sezione speciale di Quark dedicata ad Alan Turing, alla crittografia, alla Seconda guerra mondiale e all’intelligenza artificiale moderna.')

@section('head')
<link rel="stylesheet" href="{{ asset('css/turing.css') }}">
@endsection

@section('content')
@include('turing.partials.hero')
@include('turing.partials.terminal-band')
@include('turing.partials.intro-section')
@include('turing.partials.editorial-blocks')
@include('turing.partials.legacy-section')
@include('turing.partials.timeline')
@include('turing.partials.final-card')
@endsection
