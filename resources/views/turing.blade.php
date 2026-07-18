@extends('layouts.app')
@section('title', ($pageTitle ?? 'Alan Turing').' — Quark')
@section('description', $pageDescription ?? 'Speciale editoriale di Quark dedicato ad Alan Turing, Enigma e intelligenza artificiale.')

@section('head')
  <link rel="stylesheet" href="{{ asset('css/turing.css') }}">
@endsection

@section('content')
@php
  $hero = $content['hero'] ?? [];
  $intro = $content['intro'] ?? [];
  $cards = collect($content['cards'] ?? []);
  $editorialBlocks = collect($content['editorial_blocks'] ?? [
   [
  [
  'key' => 'enigma',
  'enabled' => true,
  'layout' => 'image_left',
  'kicker' => 'Enigma',
  'title' => 'Il blocco Enigma',
  'text' => 'La sfida dei codici cifrati e il lavoro a Bletchley Park.',
  'image' => 'turing/enigma.webp',
  'background_image' => '',
  'link_label' => '',
  'link_url' => '#enigma',
],
[
  'key' => 'macchina-universale',
  'enabled' => true,
  'layout' => 'image_right',
  'kicker' => 'Computazione',
  'title' => 'La macchina universale',
  'text' => 'Il modello teorico che anticipa il computer moderno.',
  'image' => 'turing/universal-machine.webp',
  'background_image' => '',
  'link_label' => '',
  'link_url' => '#macchina-universale',
],
[
  'key' => 'test-turing',
  'enabled' => true,
  'layout' => 'dark_card',
  'kicker' => 'Intelligenza',
  'title' => 'Il Test di Turing',
  'text' => 'Una domanda sul linguaggio, sulle macchine e sul significato del pensare.',
  'image' => 'turing/test-turing.jpg',
  'background_image' => '',
  'link_label' => '',
  'link_url' => '#test-turing',
],
[
  'key' => 'ai-moderna',
  'enabled' => true,
  'layout' => 'feature_grid',
  'kicker' => 'AI moderna',
  'title' => 'Da Turing all’intelligenza artificiale contemporanea',
  'text' => 'L’eredità di Turing attraversa algoritmi, modelli linguistici e responsabilità tecnologica.',
  'image' => 'turing/modern-ai.webp',
  'background_image' => '',
  'link_label' => '',
  'link_url' => '#ai-moderna',
],

  $internalLinks = collect($content['internal_links'] ?? []);
  $decorativeImages = collect($content['decorative_images'] ?? []);
  $why = $content['why'] ?? [];
  $whyItems = collect($why['items'] ?? []);
  $timeline = collect($content['timeline'] ?? []);
  $final = $content['final'] ?? [];

  $img = function ($value) {
      if (empty($value)) return null;
      return str_starts_with($value, 'http') || str_starts_with($value, '/') ? $value : asset('assets/img/'.$value);
  };
  $bg = fn ($value) => $img($value) ? "background-image:url('".$img($value)."')" : '';
  $terminalLines = collect($hero['terminal_lines'] ?? ['ENIGMA SIGNAL FOUND', 'MACHINE INTELLIGENCE: ACTIVE', 'QUESTION: CAN MACHINES THINK?', 'STATUS: STILL OPEN']);
  $heroBackgroundImage = $hero['background_image'] ?? null;
  $heroPortraitImage = $hero['portrait_image'] ?? $heroBackgroundImage;
@endphp

<div class="public-page turing-page">
  <section class="turing-hero" style="{{ $bg($heroBackgroundImage) }}">
    <div class="container turing-hero__grid">
      <div>
        <span class="turing-kicker">{{ $hero['kicker'] ?? 'Quark Special Project' }}</span>
        <h1>{{ $hero['title'] ?? 'Alan Turing' }}</h1>
        <p class="turing-lead">{{ $hero['lead'] ?? 'Una mente che attraversa guerra, matematica, computer e intelligenza artificiale. Turing non è solo una biografia: è una chiave per capire il nostro presente digitale.' }}</p>
        <div class="turing-actions">
          <a href="#enigma">{{ $hero['primary_label'] ?? 'Esplora Enigma' }}</a>
          <a href="#ai-moderna">{{ $hero['secondary_label'] ?? 'Vai all’IA moderna' }}</a>
        </div>
      </div>
      <figure class="turing-portrait-card">
        <div class="turing-portrait-card__image" style="{{ $bg($heroPortraitImage) }}">
          @if(empty($heroPortraitImage))<span class="turing-portrait-initials">{{ $hero['portrait_initials'] ?? 'AT' }}</span>@endif
          <span class="turing-portrait-years">{{ $hero['portrait_years'] ?? '1912 / 1954' }}</span>
        </div>
        <figcaption>
          <strong>{{ $hero['portrait_title'] ?? 'Alan Mathison Turing' }}</strong>
          <span>{{ $hero['portrait_text'] ?? '1912–1954 · Matematico, logico, pioniere dell’informatica' }}</span>
        </figcaption>
      </figure>
    </div>
  </section>

  <section class="turing-terminal-band">
    <div class="container">
      <div class="turing-terminal-card">
        <span>{{ $hero['terminal_title'] ?? 'Turing Archive' }}</span>
        @foreach($terminalLines as $line)<code>{{ $line }}</code>@endforeach
      </div>
    </div>
  </section>

  <section class="turing-section {{ !empty($intro['background_image']) ? 'has-bg' : '' }}" id="intro" style="{{ $bg($intro['background_image'] ?? null) }}">
    <div class="container">
      <div class="turing-section__head">
        <span class="turing-kicker">{{ $intro['kicker'] ?? 'Il contesto' }}</span>
        <h2>{{ $intro['title'] ?? 'Dal messaggio cifrato alla macchina universale' }}</h2>
        <p>{{ $intro['text'] ?? 'La storia di Turing attraversa matematica, guerra, informatica e filosofia.' }}</p>
      </div>
    </div>
  </section>

  @if($cards->isNotEmpty())
    <section class="turing-section">
      <div class="container turing-route-grid">
        @foreach($cards as $card)
          <a class="turing-route-card turing-route-card--{{ $card['style'] ?? 'enigma' }}" href="{{ $card['url'] ?? '#timeline' }}" style="{{ $bg($card['image'] ?? null) }}">
            <span>{{ $card['label'] ?? 'Percorso' }}</span>
            <h3>{{ $card['title'] ?? 'Approfondimento Turing' }}</h3>
            <p>{{ $card['text'] ?? 'Un tassello dello speciale editoriale dedicato a Turing.' }}</p>
          </a>
        @endforeach
      </div>
    </section>
  @endif

  @foreach($editorialBlocks->where('enabled', true) as $block)
    @php $layout = $block['layout'] ?? 'image_left'; $blockBg = $bg($block['background_image'] ?? null); @endphp
    <section class="turing-section turing-editorial-block {{ !empty($block['background_image']) ? 'has-bg' : '' }} turing-layout--{{ $layout }}" id="{{ $block['key'] ?? Str::slug($block['title'] ?? 'sezione') }}" style="{{ $blockBg }}">
      <div class="container turing-split">
        @if(in_array($layout, ['image_left','feature_grid']))
          <div class="turing-image-panel" style="{{ $bg($block['image'] ?? null) }}"></div>
        @endif
        <div class="turing-copy-panel">
          <span class="turing-kicker">{{ $block['kicker'] ?? 'Turing' }}</span>
          <h2>{{ $block['title'] ?? 'Sezione editoriale' }}</h2>
          <p>{{ $block['text'] ?? '' }}</p>
          @if(!empty($block['link_label']) && !empty($block['link_url']))
            <div class="turing-actions"><a href="{{ $block['link_url'] }}">{{ $block['link_label'] }}</a></div>
          @endif
        </div>
        @if($layout === 'image_right')
          <div class="turing-image-panel" style="{{ $bg($block['image'] ?? null) }}"></div>
        @endif
      </div>
    </section>
  @endforeach

  @if($internalLinks->isNotEmpty())
    <section class="turing-section">
      <div class="container">
        <div class="turing-section__head"><span class="turing-kicker">Approfondimenti</span><h2>Leggi anche</h2></div>
        <div class="turing-route-grid">
          @foreach($internalLinks as $link)
            <a class="turing-route-card turing-route-card--legacy" href="{{ $link['url'] ?? '#' }}" style="{{ $bg($link['image'] ?? null) }}">
              <span>Approfondimento</span><h3>{{ $link['title'] ?? 'Articolo collegato' }}</h3><p>{{ $link['description'] ?? '' }}</p>
            </a>
          @endforeach
        </div>
      </div>
    </section>
  @endif

  <section class="turing-section {{ !empty($why['background_image']) ? 'has-bg' : '' }}" id="eredita" style="{{ $bg($why['background_image'] ?? null) }}">
    <div class="container">
      <div class="turing-section__head">
        <span class="turing-kicker">{{ $why['kicker'] ?? 'Perché oggi' }}</span>
        <h2>{{ $why['title'] ?? 'Turing non è solo storia dell’informatica' }}</h2>
        <p>{{ $why['text'] ?? 'Le sue idee parlano ancora di algoritmi, intelligenza, responsabilità e limiti delle macchine.' }}</p>
      </div>
      @if($whyItems->isNotEmpty())
        <div class="turing-mini-grid">
          @foreach($whyItems as $item)<div>@if(!empty($item['image']))<img src="{{ $img($item['image']) }}" alt="">@endif<strong>{{ $item['title'] ?? 'Idea chiave' }}</strong><span>{{ $item['text'] ?? '' }}</span></div>@endforeach
        </div>
      @endif
    </div>
  </section>

  @if($timeline->isNotEmpty())
    <section class="turing-section turing-section--dark" id="timeline">
      <div class="container"><div class="turing-section__head"><span class="turing-kicker">Timeline</span><h2>Le tappe dello speciale</h2></div><div class="turing-timeline">
        @foreach($timeline as $event)<article class="turing-timeline__item"><div class="turing-timeline__year">{{ $event['year'] ?? '' }}</div><div><h3>{{ $event['title'] ?? 'Evento Turing' }}</h3><p>{{ $event['text'] ?? '' }}</p>@if(!empty($event['image']))<img class="turing-timeline__media" src="{{ $img($event['image']) }}" alt="">@endif</div></article>@endforeach
      </div></div>
    </section>
  @endif

  <section class="turing-section">
    <div class="container">
      <div class="turing-final-card {{ !empty($final['background_image']) ? 'has-bg' : '' }}" style="{{ $bg($final['background_image'] ?? null) }}">
        <span class="turing-kicker">{{ $final['kicker'] ?? 'Domanda aperta' }}</span><h2>{{ $final['title'] ?? 'La domanda resta viva' }}</h2><p>{{ $final['text'] ?? 'Ogni nuova macchina intelligente ci riporta alla domanda di Turing.' }}</p><div class="turing-actions turing-actions--center"><a href="{{ route('notizie') }}">Leggi altri articoli scientifici</a></div>
      </div>
    </div>
  </section>
</div>
@endsection
