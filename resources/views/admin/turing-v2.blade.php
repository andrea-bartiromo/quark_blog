@extends('layouts.admin')
@section('title', 'Speciale Turing')

@section('content')
@php
  $content = $page->content ?? [];
  $hero = $content['hero'] ?? [];
  $homeTeaser = $content['home_teaser'] ?? [];
  $intro = $content['intro'] ?? [];
  $cards = $content['cards'] ?? [];
  $editorialBlocks = $content['editorial_blocks'] ?? [];
  $internalLinks = $content['internal_links'] ?? [];
  $decorativeImages = $content['decorative_images'] ?? [];
  $why = $content['why'] ?? [];
  $whyItems = $why['items'] ?? [];
  $timeline = $content['timeline'] ?? [];
  $final = $content['final'] ?? [];

  $blankCard = ['label' => '', 'title' => '', 'text' => '', 'url' => '', 'style' => 'enigma', 'image' => ''];
  $blankBlock = ['enabled' => true, 'key' => '', 'title' => '', 'kicker' => '', 'layout' => 'image_left', 'text' => '', 'image' => '', 'background_image' => '', 'link_label' => '', 'link_url' => ''];
  $blankLink = ['title' => '', 'description' => '', 'url' => '', 'image' => ''];
  $blankDecor = ['label' => '', 'position' => '', 'image' => ''];
  $blankWhy = ['title' => '', 'text' => '', 'image' => ''];
  $blankEvent = ['year' => '', 'title' => '', 'text' => '', 'image' => ''];

  if (empty($cards)) $cards = [
    ['label' => '01 · Bletchley Park', 'title' => 'La guerra di Enigma', 'text' => 'Bombe, rotori, messaggi cifrati e una guerra combattuta anche con probabilità e logica.', 'url' => '/turing/enigma', 'style' => 'enigma', 'image' => ''],
    ['label' => '02 · Macchine intelligenti', 'title' => 'Dal Test di Turing agli LLM', 'text' => 'La domanda “le macchine possono pensare?” riletta nell’epoca dell’IA generativa.', 'url' => '/turing/ia', 'style' => 'ai', 'image' => ''],
    ['label' => '03 · Eredità', 'title' => 'Il genio inquieto', 'text' => 'La persecuzione, la riabilitazione e l’impatto culturale di una figura diventata simbolo.', 'url' => '', 'style' => 'legacy', 'image' => ''],
  ];

  if (empty($editorialBlocks)) $editorialBlocks = [
    ['enabled' => true, 'key' => 'enigma', 'title' => 'Il blocco Enigma', 'kicker' => 'Enigma', 'layout' => 'image_left', 'text' => '', 'image' => '', 'background_image' => '', 'link_label' => '', 'link_url' => '#enigma'],
    ['enabled' => true, 'key' => 'macchina-universale', 'title' => 'La macchina universale', 'kicker' => 'Computazione', 'layout' => 'image_right', 'text' => '', 'image' => '', 'background_image' => '', 'link_label' => '', 'link_url' => '#macchina-universale'],
    ['enabled' => true, 'key' => 'test-turing', 'title' => 'Il Test di Turing', 'kicker' => 'Intelligenza', 'layout' => 'dark_card', 'text' => '', 'image' => '', 'background_image' => '', 'link_label' => '', 'link_url' => '#test-turing'],
    ['enabled' => true, 'key' => 'ai-moderna', 'title' => 'Da Turing all’intelligenza artificiale contemporanea', 'kicker' => 'AI moderna', 'layout' => 'feature_grid', 'text' => '', 'image' => '', 'background_image' => '', 'link_label' => '', 'link_url' => '#ai-moderna'],
  ];

  if (empty($internalLinks)) $internalLinks = [$blankLink];
  if (empty($decorativeImages)) $decorativeImages = [['label' => 'Decorazione hero', 'position' => 'hero', 'image' => '']];
  if (empty($whyItems)) $whyItems = [['title' => 'Calcolo', 'text' => 'la macchina universale', 'image' => ''], ['title' => 'Sicurezza', 'text' => 'codici, cifrari, decrittazione', 'image' => ''], ['title' => 'IA', 'text' => 'imitazione, linguaggio, giudizio', 'image' => '']];
  if (empty($timeline)) $timeline = [['year' => '1912', 'title' => 'Nasce Alan Mathison Turing', 'text' => '', 'image' => ''], ['year' => '1936', 'title' => 'La macchina universale', 'text' => '', 'image' => ''], ['year' => 'Oggi', 'title' => 'L’era degli algoritmi', 'text' => '', 'image' => '']];

  $imageField = function ($name, $value = '', $label = 'Immagine') {
      $asset = $value ? asset('assets/img/'.$value) : null;
      $removeName = str_contains($name, '[') ? preg_replace('/\[([^\]]+)\]$/', '[$1_remove]', $name) : $name.'_remove';
      $html = '<div class="form-group turing-media-field"><label class="form-label">'.$label.'</label>';
      if ($asset) {
          $html .= '<div class="turing-current-image"><img src="'.$asset.'" alt=""><div><strong>Attuale</strong><small>'.e($value).'</small><label><input type="checkbox" name="'.$removeName.'" value="1"> Rimuovi immagine attuale</label></div></div>';
      }
      $html .= '<input type="file" name="'.$name.'_upload" accept="image/jpeg,image/png,image/webp" class="turing-file-input">';
      $html .= '<input class="form-input" name="'.$name.'" value="'.e($value).'" maxlength="500" placeholder="oppure nome file dalla libreria media">';
      $html .= '<a href="'.route('admin.media').'" target="_blank" class="turing-media-link">Apri libreria media →</a></div>';
      return $html;
  };
@endphp

<style>
  .turing-admin-shell{display:grid;grid-template-columns:260px minmax(0,1fr);gap:1.25rem;align-items:start}.turing-admin-nav{position:sticky;top:1rem;background:#fff;border:1px solid var(--admin-border);border-radius:18px;box-shadow:var(--admin-shadow);padding:.8rem}.turing-admin-nav button{width:100%;border:0;background:transparent;text-align:left;padding:.8rem .9rem;border-radius:12px;font-weight:800;color:#334155;cursor:pointer}.turing-admin-nav button:hover,.turing-admin-nav button.is-active{background:#ecfeff;color:#0f766e}.turing-admin-panel{display:none}.turing-admin-panel.is-active{display:block}.turing-admin-card{background:#fff;border:1px solid var(--admin-border);border-radius:20px;box-shadow:var(--admin-shadow);padding:1.25rem;margin-bottom:1rem}.turing-admin-card h2{margin:0 0 .35rem;font-size:1.15rem}.turing-admin-hint{margin:0 0 1.15rem;color:var(--admin-muted);line-height:1.7}.turing-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.turing-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem}.turing-row-card{border:1px solid #e5e7eb;background:#f8fafc;border-radius:16px;padding:1rem;margin-bottom:1rem}.turing-row-head{display:flex;justify-content:space-between;gap:1rem;align-items:center;margin-bottom:.85rem}.turing-current-image{display:grid;grid-template-columns:130px 1fr;gap:.75rem;align-items:center;background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:.65rem;margin-bottom:.6rem}.turing-current-image img{width:130px;height:82px;object-fit:cover;border-radius:10px}.turing-current-image small{display:block;color:#64748b;word-break:break-all;margin:.15rem 0 .35rem}.turing-current-image label{font-size:.78rem;font-weight:800;color:#b91c1c}.turing-file-input{display:block;width:100%;border:1px dashed #cbd5e1;border-radius:10px;background:#f8fafc;padding:.75rem;margin-bottom:.55rem}.turing-media-link{display:inline-block;margin-top:.35rem;font-size:.78rem;color:#0f766e;text-decoration:none;font-weight:800}.turing-savebar{position:sticky;bottom:1rem;z-index:10;display:flex;justify-content:flex-end;gap:.75rem;padding:1rem;border:1px solid var(--admin-border);border-radius:16px;background:rgba(255,255,255,.96);box-shadow:var(--admin-shadow)}@media(max-width:980px){.turing-admin-shell{grid-template-columns:1fr}.turing-admin-nav{position:static}.turing-grid,.turing-grid-3{grid-template-columns:1fr}}
</style>

<div class="admin-topbar">
  <div>
    <h1 class="admin-page-title">Speciale Turing</h1>
    <p style="margin:.35rem 0 0;color:var(--admin-muted);font-size:.9rem;">Dashboard ricostruita: home, hero, sezioni e immagini sono separati.</p>
  </div>
  <a href="{{ route('turing') }}" target="_blank" class="btn btn--secondary">Apri pagina pubblica</a>
</div>

@if($errors->any())
  <div class="admin-alert admin-alert--danger"><strong>Controlla i campi:</strong><ul style="margin:.5rem 0 0 1.1rem;">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ route('admin.turing.update') }}" enctype="multipart/form-data" id="turing-cms-form">
@csrf
<div class="turing-admin-shell">
  <nav class="turing-admin-nav" aria-label="Sezioni speciale Turing">
    <button type="button" class="is-active" data-turing-tab="home">Box homepage</button>
    <button type="button" data-turing-tab="hero">Hero /turing</button>
    <button type="button" data-turing-tab="settings">Impostazioni</button>
    <button type="button" data-turing-tab="intro">Introduzione</button>
    <button type="button" data-turing-tab="cards">Cards</button>
    <button type="button" data-turing-tab="blocks">Blocchi editoriali</button>
    <button type="button" data-turing-tab="why">Perché conta</button>
    <button type="button" data-turing-tab="timeline">Timeline</button>
    <button type="button" data-turing-tab="final">Chiusura</button>
    <button type="button" data-turing-tab="extra">Extra</button>
  </nav>

  <div>
    <section class="turing-admin-panel is-active" data-turing-panel="home">
      <div class="turing-admin-card"><h2>Box Turing in homepage</h2><p class="turing-admin-hint">Appare nella home. Non modifica la pagina pubblica /turing.</p>
        <div class="turing-grid"><div class="form-group"><label class="form-label">Kicker</label><input class="form-input" name="home_teaser_kicker" value="{{ old('home_teaser_kicker', $homeTeaser['kicker'] ?? 'Special Project') }}" maxlength="120"></div><div class="form-group"><label class="form-label">CTA</label><input class="form-input" name="home_teaser_cta_label" value="{{ old('home_teaser_cta_label', $homeTeaser['cta_label'] ?? 'Entra nella Turing Experience') }}" maxlength="100"></div></div>
        <div class="form-group"><label class="form-label">Titolo</label><input class="form-input" name="home_teaser_title" value="{{ old('home_teaser_title', $homeTeaser['title'] ?? 'Alan Turing: l’uomo che ha decifrato il futuro.') }}" maxlength="180"></div>
        <div class="form-group"><label class="form-label">Descrizione</label><textarea class="form-textarea" name="home_teaser_text" maxlength="700">{{ old('home_teaser_text', $homeTeaser['text'] ?? 'Una nuova area speciale di Quark dedicata a Enigma, alla nascita del computer, al Test di Turing e al legame con l’intelligenza artificiale moderna.') }}</textarea></div>
        {!! $imageField('home_teaser_background_image', old('home_teaser_background_image', $homeTeaser['background_image'] ?? ''), 'Immagine di sfondo box homepage') !!}
        <div class="form-group"><label class="form-label">Titolo terminale</label><input class="form-input" name="home_teaser_terminal_title" value="{{ old('home_teaser_terminal_title', $homeTeaser['terminal_title'] ?? 'TURING ARCHIVE') }}" maxlength="120"></div>
        @php $homeLines = old('home_teaser_terminal_lines', $homeTeaser['terminal_lines'] ?? ['ENIGMA SIGNAL FOUND','MACHINE INTELLIGENCE: ACTIVE','QUESTION: CAN MACHINES THINK?','STATUS: STILL OPEN']); @endphp
        <div class="form-group"><label class="form-label">Righe terminale</label><div style="display:grid;gap:.5rem;">@foreach($homeLines as $line)<input class="form-input" name="home_teaser_terminal_lines[]" value="{{ $line }}" maxlength="160">@endforeach<input class="form-input" name="home_teaser_terminal_lines[]" value="" placeholder="+ Aggiungi riga" maxlength="160"></div></div>
      </div>
    </section>

    <section class="turing-admin-panel" data-turing-panel="hero">
      <div class="turing-admin-card"><h2>Hero pagina /turing</h2><p class="turing-admin-hint">Appare solo nella pagina /turing. Lo sfondo e il riquadro laterale sono due immagini diverse.</p>
        <div class="turing-grid"><div class="form-group"><label class="form-label">Kicker</label><input class="form-input" name="hero_kicker" value="{{ old('hero_kicker', $hero['kicker'] ?? 'Quark Special Project') }}" maxlength="120"></div><div class="form-group"><label class="form-label">Titolo hero</label><input class="form-input" name="hero_title" value="{{ old('hero_title', $hero['title'] ?? 'Alan Turing') }}" maxlength="150"></div></div>
        <div class="form-group"><label class="form-label">Lead</label><textarea class="form-textarea" name="hero_lead" maxlength="900">{{ old('hero_lead', $hero['lead'] ?? 'Una mente che attraversa guerra, matematica, computer e intelligenza artificiale. Turing non è solo una biografia: è una chiave per capire il nostro presente digitale.') }}</textarea></div>
        <div class="turing-grid"><div class="form-group"><label class="form-label">CTA primaria</label><input class="form-input" name="hero_primary_label" value="{{ old('hero_primary_label', $hero['primary_label'] ?? 'Esplora Enigma') }}" maxlength="80"></div><div class="form-group"><label class="form-label">CTA secondaria</label><input class="form-input" name="hero_secondary_label" value="{{ old('hero_secondary_label', $hero['secondary_label'] ?? 'Vai all’IA moderna') }}" maxlength="80"></div></div>
        <div class="turing-grid">{!! $imageField('hero_background_image', old('hero_background_image', $hero['background_image'] ?? ''), 'Immagine di sfondo hero /turing') !!}{!! $imageField('hero_portrait_image', old('hero_portrait_image', $hero['portrait_image'] ?? ''), 'Immagine riquadro laterale / portrait') !!}</div>
        <h3>Riquadro biografico</h3><div class="turing-grid-3"><div class="form-group"><label class="form-label">Iniziali</label><input class="form-input" name="hero_portrait_initials" value="{{ old('hero_portrait_initials', $hero['portrait_initials'] ?? 'AT') }}" maxlength="12"></div><div class="form-group"><label class="form-label">Anni</label><input class="form-input" name="hero_portrait_years" value="{{ old('hero_portrait_years', $hero['portrait_years'] ?? '1912 / 1954') }}" maxlength="40"></div><div class="form-group"><label class="form-label">Nome</label><input class="form-input" name="hero_portrait_title" value="{{ old('hero_portrait_title', $hero['portrait_title'] ?? 'Alan Mathison Turing') }}" maxlength="150"></div></div>
        <div class="form-group"><label class="form-label">Descrizione biografica</label><input class="form-input" name="hero_portrait_text" value="{{ old('hero_portrait_text', $hero['portrait_text'] ?? '1912–1954 · Matematico, logico, pioniere dell’informatica') }}" maxlength="220"></div>
        <h3>Terminale</h3><div class="form-group"><label class="form-label">Titolo terminale</label><input class="form-input" name="hero_terminal_title" value="{{ old('hero_terminal_title', $hero['terminal_title'] ?? 'TURING ARCHIVE') }}" maxlength="120"></div>
        @php $heroLines = old('hero_terminal_lines', $hero['terminal_lines'] ?? ['ENIGMA SIGNAL FOUND','MACHINE INTELLIGENCE: ACTIVE','QUESTION: CAN MACHINES THINK?','STATUS: STILL OPEN']); @endphp
        <div class="form-group"><label class="form-label">Righe terminale</label><div style="display:grid;gap:.5rem;">@foreach($heroLines as $line)<input class="form-input" name="hero_terminal_lines[]" value="{{ $line }}" maxlength="160">@endforeach<input class="form-input" name="hero_terminal_lines[]" value="" placeholder="+ Aggiungi riga" maxlength="160"></div></div>
      </div>
    </section>

    <section class="turing-admin-panel" data-turing-panel="settings"><div class="turing-admin-card"><h2>Impostazioni pagina</h2><div class="form-group"><label class="form-label">Titolo CMS</label><input class="form-input" name="title" value="{{ old('title', $page->title) }}" maxlength="150"></div><div class="form-group"><label class="form-label">Descrizione SEO</label><textarea class="form-textarea" name="description" maxlength="500">{{ old('description', $page->description) }}</textarea></div><label style="display:flex;gap:.65rem;align-items:center;padding:1rem;border:1px solid var(--admin-border);border-radius:12px;background:#f9fafb;"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $page->is_active))><span><strong>Pagina attiva</strong><small style="display:block;color:var(--admin-muted);">Se disattivata, il frontend mostra i fallback.</small></span></label></div></section>

    <section class="turing-admin-panel" data-turing-panel="intro"><div class="turing-admin-card"><h2>Introduzione</h2><div class="form-group"><label class="form-label">Kicker</label><input class="form-input" name="intro_kicker" value="{{ old('intro_kicker', $intro['kicker'] ?? 'Il filo rosso') }}" maxlength="120"></div><div class="form-group"><label class="form-label">Titolo</label><input class="form-input" name="intro_title" value="{{ old('intro_title', $intro['title'] ?? 'Dalla crittografia alla coscienza artificiale') }}" maxlength="180"></div><div class="form-group"><label class="form-label">Testo</label><textarea class="form-textarea" name="intro_text" maxlength="900">{{ old('intro_text', $intro['text'] ?? '') }}</textarea></div>{!! $imageField('intro_background_image', old('intro_background_image', $intro['background_image'] ?? ''), 'Immagine di sfondo introduzione') !!}</div></section>

    <section class="turing-admin-panel" data-turing-panel="cards"><div class="turing-admin-card"><h2>Cards / percorsi</h2><p class="turing-admin-hint">L’ultima riga vuota serve per aggiungere una nuova card.</p>@foreach(array_merge(old('cards', $cards), [$blankCard]) as $i => $card)<div class="turing-row-card js-row"><div class="turing-row-head"><strong>Card {{ $i + 1 }}</strong><button type="button" class="btn btn--danger btn--sm js-remove-row">Rimuovi</button></div><div class="turing-grid-3"><div class="form-group"><label class="form-label">Label</label><input class="form-input" name="cards[{{ $i }}][label]" value="{{ $card['label'] ?? '' }}" maxlength="120"></div><div class="form-group"><label class="form-label">Titolo</label><input class="form-input" name="cards[{{ $i }}][title]" value="{{ $card['title'] ?? '' }}" maxlength="150"></div><div class="form-group"><label class="form-label">Stile</label><select class="form-select" name="cards[{{ $i }}][style]"><option value="enigma" @selected(($card['style'] ?? '') === 'enigma')>Enigma</option><option value="ai" @selected(($card['style'] ?? '') === 'ai')>AI</option><option value="legacy" @selected(($card['style'] ?? '') === 'legacy')>Legacy</option></select></div></div><div class="form-group"><label class="form-label">Testo</label><textarea class="form-textarea" name="cards[{{ $i }}][text]" maxlength="500">{{ $card['text'] ?? '' }}</textarea></div><div class="turing-grid"><div class="form-group"><label class="form-label">URL</label><input class="form-input" name="cards[{{ $i }}][url]" value="{{ $card['url'] ?? '' }}" maxlength="255"></div>{!! $imageField("cards[$i][image]", $card['image'] ?? '', 'Immagine card') !!}</div></div>@endforeach</div></section>

    <section class="turing-admin-panel" data-turing-panel="blocks"><div class="turing-admin-card"><h2>Blocchi editoriali</h2><p class="turing-admin-hint">L’ultima riga vuota serve per aggiungere una nuova sezione.</p>@foreach(array_merge(old('editorial_blocks', $editorialBlocks), [$blankBlock]) as $i => $block)<div class="turing-row-card js-row"><div class="turing-row-head"><strong>{{ $block['title'] ?: 'Nuovo blocco' }}</strong><button type="button" class="btn btn--danger btn--sm js-remove-row">Rimuovi</button></div><label style="display:flex;gap:.45rem;align-items:center;margin-bottom:.75rem;"><input type="checkbox" name="editorial_blocks[{{ $i }}][enabled]" value="1" @checked($block['enabled'] ?? true)> Visibile</label><div class="turing-grid-3"><div class="form-group"><label class="form-label">Chiave</label><input class="form-input" name="editorial_blocks[{{ $i }}][key]" value="{{ $block['key'] ?? '' }}" maxlength="80"></div><div class="form-group"><label class="form-label">Kicker</label><input class="form-input" name="editorial_blocks[{{ $i }}][kicker]" value="{{ $block['kicker'] ?? '' }}" maxlength="120"></div><div class="form-group"><label class="form-label">Layout</label><select class="form-select" name="editorial_blocks[{{ $i }}][layout]"><option value="text" @selected(($block['layout'] ?? '') === 'text')>Solo testo</option><option value="image_left" @selected(($block['layout'] ?? '') === 'image_left')>Immagine sinistra</option><option value="image_right" @selected(($block['layout'] ?? '') === 'image_right')>Immagine destra</option><option value="dark_card" @selected(($block['layout'] ?? '') === 'dark_card')>Card scura</option><option value="feature_grid" @selected(($block['layout'] ?? '') === 'feature_grid')>Feature grid</option></select></div></div><div class="form-group"><label class="form-label">Titolo</label><input class="form-input" name="editorial_blocks[{{ $i }}][title]" value="{{ $block['title'] ?? '' }}" maxlength="180"></div><div class="form-group"><label class="form-label">Testo</label><textarea class="form-textarea" name="editorial_blocks[{{ $i }}][text]" maxlength="1400">{{ $block['text'] ?? '' }}</textarea></div><div class="turing-grid">{!! $imageField("editorial_blocks[$i][image]", $block['image'] ?? '', 'Immagine sezione') !!}{!! $imageField("editorial_blocks[$i][background_image]", $block['background_image'] ?? '', 'Sfondo sezione') !!}</div><div class="turing-grid"><div class="form-group"><label class="form-label">Label CTA</label><input class="form-input" name="editorial_blocks[{{ $i }}][link_label]" value="{{ $block['link_label'] ?? '' }}" maxlength="100"></div><div class="form-group"><label class="form-label">URL CTA / anchor</label><input class="form-input" name="editorial_blocks[{{ $i }}][link_url]" value="{{ $block['link_url'] ?? '' }}" maxlength="255"></div></div></div>@endforeach</div></section>

    <section class="turing-admin-panel" data-turing-panel="why"><div class="turing-admin-card"><h2>Perché conta oggi</h2><div class="form-group"><label class="form-label">Kicker</label><input class="form-input" name="why_kicker" value="{{ old('why_kicker', $why['kicker'] ?? 'Perché conta ancora') }}" maxlength="120"></div><div class="form-group"><label class="form-label">Titolo</label><input class="form-input" name="why_title" value="{{ old('why_title', $why['title'] ?? 'Ogni volta che parliamo di algoritmo, torniamo a Turing.') }}" maxlength="180"></div><div class="form-group"><label class="form-label">Testo</label><textarea class="form-textarea" name="why_text" maxlength="1000">{{ old('why_text', $why['text'] ?? '') }}</textarea></div>{!! $imageField('why_background_image', old('why_background_image', $why['background_image'] ?? ''), 'Immagine di sfondo sezione') !!}<h3>Punti chiave</h3>@foreach(array_merge(old('why_items', $whyItems), [$blankWhy]) as $i => $item)<div class="turing-row-card js-row"><div class="turing-row-head"><strong>Punto {{ $i + 1 }}</strong><button type="button" class="btn btn--danger btn--sm js-remove-row">Rimuovi</button></div><div class="turing-grid-3"><div class="form-group"><label class="form-label">Titolo</label><input class="form-input" name="why_items[{{ $i }}][title]" value="{{ $item['title'] ?? '' }}" maxlength="100"></div><div class="form-group"><label class="form-label">Testo</label><input class="form-input" name="why_items[{{ $i }}][text]" value="{{ $item['text'] ?? '' }}" maxlength="200"></div>{!! $imageField("why_items[$i][image]", $item['image'] ?? '', 'Immagine') !!}</div></div>@endforeach</div></section>

    <section class="turing-admin-panel" data-turing-panel="timeline"><div class="turing-admin-card"><h2>Timeline</h2>@foreach(array_merge(old('timeline', $timeline), [$blankEvent]) as $i => $event)<div class="turing-row-card js-row"><div class="turing-row-head"><strong>{{ $event['year'] ?? 'Nuovo evento' }}</strong><button type="button" class="btn btn--danger btn--sm js-remove-row">Rimuovi</button></div><div class="turing-grid"><div class="form-group"><label class="form-label">Anno</label><input class="form-input" name="timeline[{{ $i }}][year]" value="{{ $event['year'] ?? '' }}" maxlength="30"></div><div class="form-group"><label class="form-label">Titolo</label><input class="form-input" name="timeline[{{ $i }}][title]" value="{{ $event['title'] ?? '' }}" maxlength="150"></div></div><div class="form-group"><label class="form-label">Testo</label><textarea class="form-textarea" name="timeline[{{ $i }}][text]" maxlength="600">{{ $event['text'] ?? '' }}</textarea></div>{!! $imageField("timeline[$i][image]", $event['image'] ?? '', 'Immagine evento') !!}</div>@endforeach</div></section>

    <section class="turing-admin-panel" data-turing-panel="final"><div class="turing-admin-card"><h2>Chiusura</h2><div class="form-group"><label class="form-label">Kicker</label><input class="form-input" name="final_kicker" value="{{ old('final_kicker', $final['kicker'] ?? 'Prossima lettura') }}" maxlength="120"></div><div class="form-group"><label class="form-label">Titolo</label><input class="form-input" name="final_title" value="{{ old('final_title', $final['title'] ?? 'Scegli da dove iniziare') }}" maxlength="180"></div><div class="form-group"><label class="form-label">Testo</label><textarea class="form-textarea" name="final_text" maxlength="500">{{ old('final_text', $final['text'] ?? '') }}</textarea></div>{!! $imageField('final_background_image', old('final_background_image', $final['background_image'] ?? ''), 'Immagine di sfondo chiusura') !!}</div></section>

    <section class="turing-admin-panel" data-turing-panel="extra"><div class="turing-admin-card"><h2>Approfondimenti interni</h2>@foreach(array_merge(old('internal_links', $internalLinks), [$blankLink]) as $i => $link)<div class="turing-row-card js-row"><div class="turing-row-head"><strong>Approfondimento {{ $i + 1 }}</strong><button type="button" class="btn btn--danger btn--sm js-remove-row">Rimuovi</button></div><div class="turing-grid"><div class="form-group"><label class="form-label">Titolo</label><input class="form-input" name="internal_links[{{ $i }}][title]" value="{{ $link['title'] ?? '' }}" maxlength="150"></div><div class="form-group"><label class="form-label">URL</label><input class="form-input" name="internal_links[{{ $i }}][url]" value="{{ $link['url'] ?? '' }}" maxlength="255"></div></div><div class="form-group"><label class="form-label">Descrizione</label><textarea class="form-textarea" name="internal_links[{{ $i }}][description]" maxlength="300">{{ $link['description'] ?? '' }}</textarea></div>{!! $imageField("internal_links[$i][image]", $link['image'] ?? '', 'Immagine approfondimento') !!}</div>@endforeach</div><div class="turing-admin-card"><h2>Immagini decorative</h2>@foreach(array_merge(old('decorative_images', $decorativeImages), [$blankDecor]) as $i => $image)<div class="turing-row-card js-row"><div class="turing-row-head"><strong>Decorazione {{ $i + 1 }}</strong><button type="button" class="btn btn--danger btn--sm js-remove-row">Rimuovi</button></div><div class="turing-grid"><div class="form-group"><label class="form-label">Label</label><input class="form-input" name="decorative_images[{{ $i }}][label]" value="{{ $image['label'] ?? '' }}" maxlength="120"></div><div class="form-group"><label class="form-label">Posizione</label><input class="form-input" name="decorative_images[{{ $i }}][position]" value="{{ $image['position'] ?? '' }}" maxlength="80"></div></div>{!! $imageField("decorative_images[$i][image]", $image['image'] ?? '', 'Immagine decorativa') !!}</div>@endforeach</div></section>

    <div class="turing-savebar"><a href="{{ route('turing') }}" target="_blank" class="btn btn--secondary">Anteprima</a><button type="submit" class="btn btn--primary">Salva speciale Turing</button></div>
  </div>
</div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const navButtons = document.querySelectorAll('[data-turing-tab]');
  const panels = document.querySelectorAll('[data-turing-panel]');
  navButtons.forEach(button => {
    button.addEventListener('click', () => {
      const tab = button.dataset.turingTab;
      navButtons.forEach(item => item.classList.toggle('is-active', item === button));
      panels.forEach(panel => panel.classList.toggle('is-active', panel.dataset.turingPanel === tab));
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  });
  document.addEventListener('click', event => {
    const remove = event.target.closest('.js-remove-row');
    if (!remove) return;
    const row = remove.closest('.js-row');
    if (row) row.remove();
  });
});
</script>
@endpush
