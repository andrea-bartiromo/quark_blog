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

  $imageField = function ($name, $value = '', $label = 'Immagine') {
      $asset = $value ? asset('assets/img/'.$value) : null;
      $html = '<div class="form-group"><label class="form-label">'.$label.'</label>';
      if ($asset) {
          $removeName = str_contains($name, '[') ? preg_replace('/\\[([^\\]]+)\\]$/', '[$1_remove]', $name) :
           $name.'_remove';
          $html .= '<div style="margin-bottom:.65rem"><img src="'.$asset.'" alt="" style="width:100%;max-height:
          130px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb" onerror="this.style.display=\'none\'"
          ><div style="font-size:.65rem;color:#6b7280;margin-top:.25rem">Attuale: '.e($value).'</div><label style="
          display:flex;gap:.45rem;align-items:center;margin-top:.45rem;color:#b91c1c;font-size:.78rem;font-weight:
          700;"><input type="checkbox" name="'.$removeName.'" value="1"> Rimuovi immagine attuale</label></div>';
      }
      $html .= '<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:.85rem;margin-
      bottom:.5rem"><input type="file" name="'.$name.'_upload" accept="image/jpeg,image/png,image/webp" style="font-
      size:.82rem;padding:.4rem;border:1px solid #e5e7eb;border-radius:6px;background:#fff;width:100%"><div style="
      font-size:.68rem;color:#6b7280;margin-top:.35rem">Carica nuova immagine, max 16 MB.</div></div>';
      $html .= '<input class="form-input" name="'.$name.'" value="'.e($value).'" maxlength="500" placeholder="oppure 
      nome file dalla libreria media"><div style="margin-top:.35rem"><a href="'.route('admin.media').'" target="
      _blank" style="font-size:.72rem;color:#0d9488">📁 Libreria media →</a></div></div>';
      return $html;
  };

  if (empty($cards)) {
      $cards = [
          ['label' => 'Enigma', 'title' => '', 'text' => '', 'url' => '#enigma', 'style' => 'enigma', 'image' => ''],
          ['label' => 'IA', 'title' => '', 'text' => '', 'url' => '#intelligenza-artificiale', 'style' => 'ai', 
          'image' => ''],
          ['label' => 'Eredità', 'title' => '', 'text' => '', 'url' => '#eredita', 'style' => 'legacy', 'image' => ''],
      ];
  }

  if (empty($editorialBlocks)) {
      $editorialBlocks = [
          ['key' => 'enigma', 'enabled' => true, 'layout' => 'image_left', 'kicker' => 'Enigma', 'title' => 
          'Il blocco Enigma', 'text' => '', 'image' => '', 'background_image' => '', 'link_label' => '', 'link_url'
           => '#enigma'],
          ['key' => 'macchina-universale', 'enabled' => true, 'layout' => 'image_right', 'kicker' => 'Computazione', 
          'title' => 'La macchina universale', 'text' => '', 'image' => '', 'background_image' => '', 'link_label' =>
           '', 'link_url' => '#macchina-universale'],
          ['key' => 'test-turing', 'enabled' => true, 'layout' => 'dark_card', 'kicker' => 'Intelligenza', 'title' =>
           'Il Test di Turing', 'text' => '', 'image' => '', 'background_image' => '', 'link_label' => '', 'link_url' 
           => '#test-turing'],
          ['key' => 'ai-moderna', 'enabled' => true, 'layout' => 'feature_grid', 'kicker' => 'AI moderna', 'title' => 
          'Da Turing all’intelligenza artificiale contemporanea', 'text' => '', 'image' => '', 'background_image' =>
           '', 'link_label' => '', 'link_url' => '#ai-moderna'],
      ];
  }

  if (empty($internalLinks)) {
      $internalLinks = [['title' => '', 'description' => '', 'url' => '', 'image' => '']];
  }

  if (empty($decorativeImages)) {
      $decorativeImages = [['label' => 'Decorazione hero', 'position' => 'hero', 'image' => '']];
  }

  if (empty($whyItems)) {
      $whyItems = [['title' => '', 'text' => '', 'image' => ''], ['title' => '', 'text' => '', 'image' => ''], 
      ['title' => '', 'text' => '', 'image' => '']];
  }

  if (empty($timeline)) {
      $timeline = [['year' => '', 'title' => '', 'text' => '', 'image' => ''], ['year' => '', 'title' => '', 'text' 
      => '', 'image' => ''], ['year' => '', 'title' => '', 'text' => '', 'image' => '']];
  }
@endphp

<div class="admin-topbar">
  <div>
    <h1 class="admin-page-title">Speciale Turing</h1>
    <p style="margin:.35rem 0 0;color:var(--admin-muted);font-size:.9rem;">Gestisci tutta l’area Turing: contenuti, 
      blocchi editoriali, approfondimenti, immagini e sfondi.</p>
  </div>
  <a href="{{ route('turing') }}" target="_blank" class="btn btn--secondary">Apri pagina pubblica</a>
</div>

@if($errors->any())
  <div class="admin-alert admin-alert--danger"><strong>Controlla i campi:</strong><ul style="margin:.5rem 0 0 1.1rem;
  ">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ route('admin.turing.update') }}" id="turing-cms-form" enctype="multipart/form-data">
  @csrf

  <div class="admin-alert" style="margin-bottom:1rem;background:#ecfeff;border:1px solid #99f6e4;color:#0f766e;">
    <strong>Guida rapida immagini Turing</strong>
    <div style="margin-top:.45rem;line-height:1.7;color:#334155;">
      <div><strong>Box Turing in homepage</strong>: modifica solo il riquadro Turing nella home.</div>
      <div><strong>Hero pagina /turing</strong>: modifica il blocco iniziale della pagina Turing.</div>
      <div><strong>Immagine di sfondo hero /turing</strong>: immagine dietro il testo della hero.</div>
      <div><strong>Immagine riquadro laterale / portrait</strong>: sostituisce il riquadro grigio “AT”.</div>
    </div>
  </div>

  @include('admin.partials.turing-hero-advanced')

  <div class="admin-card" style="margin-bottom:1rem;">
    <h2 style="font-size:1rem;margin-bottom:.35rem;">Box Turing in homepage</h2>
    <p style="margin:0 0 1rem;color:var(--admin-muted);font-size:.85rem;">
      Gestisce il riquadro Turing mostrato nella home. È separato dalla Hero principale della pagina /turing.
    </p>

    <div class="form-group">
      <label class="form-label">Kicker home</label>
      <input class="form-input" name="home_teaser_kicker" value="{{ old('home_teaser_kicker', $homeTeaser['kicker'] ?? 'Special Project') }}" maxlength="120">
    </div>

    <div class="form-group">
      <label class="form-label">Titolo box home</label>
      <input class="form-input" name="home_teaser_title" value="{{ old('home_teaser_title', $homeTeaser['title'] ?? 'Alan Turing: l’uomo che ha decifrato il futuro.') }}" maxlength="180">
    </div>

    <div class="form-group">
      <label class="form-label">Descrizione box home</label>
      <textarea class="form-textarea" name="home_teaser_text" maxlength="700" style="min-height:100px;">{{ old('home_teaser_text', $homeTeaser['text'] ?? 'Una nuova area speciale di Quark dedicata a Enigma, alla nascita del computer, al Test di Turing e al legame con l’intelligenza artificiale moderna.') }}</textarea>
    </div>

    <div class="form-group">
      <label class="form-label">CTA box home</label>
      <input class="form-input" name="home_teaser_cta_label" value="{{ old('home_teaser_cta_label', $homeTeaser['cta_label'] ?? 'Entra nella Turing Experience') }}" maxlength="100">
    </div>

    {!! $imageField('home_teaser_background_image', old('home_teaser_background_image', $homeTeaser['background_image'] ?? ''), 'Immagine di sfondo box homepage') !!}

    <div class="form-group">
      <label class="form-label">Titolo terminale home</label>
      <input class="form-input" name="home_teaser_terminal_title" value="{{ old('home_teaser_terminal_title', $homeTeaser['terminal_title'] ?? 'TURING ARCHIVE') }}" maxlength="120">
    </div>

    <div class="form-group">
      <label class="form-label">Righe terminale home</label>
      @php
        $homeTerminalLines = old('home_teaser_terminal_lines', $homeTeaser['terminal_lines'] ?? ['ENIGMA SIGNAL FOUND', 'MACHINE INTELLIGENCE: ACTIVE', 'QUESTION: CAN MACHINES THINK?', 'STATUS: STILL OPEN']);
      @endphp
      <div style="display:grid;gap:.5rem;">
        @foreach($homeTerminalLines as $line)
          <input class="form-input" name="home_teaser_terminal_lines[]" value="{{ $line }}" maxlength="160">
        @endforeach
        <input class="form-input" name="home_teaser_terminal_lines[]" value="" maxlength="160" placeholder="+ Aggiungi riga">
      </div>
    </div>
  </div>

  <div class="admin-card" style="margin-bottom:1rem;">
    <h2 style="font-size:1rem;margin-bottom:1rem;">Impostazioni pagina</h2>
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;align-items:start;">
      <div><div class="form-group"><label class="form-label">Titolo CMS</label><input class="form-input" name="title" value="{{ old('title', $page->title) }}" required maxlength="150"></div><div class="form-group"><label class="form-label">Descrizione SEO</label><textarea class="form-textarea" name="description" maxlength="500" style="min-height:90px;">{{ old('description', $page->description) }}</textarea></div></div>
      <label style="display:flex;gap:.65rem;align-items:center;margin-top:1.8rem;padding:1rem;border:1px solid var(--admin-border);border-radius:12px;background:#f9fafb;"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $page->is_active))><span><strong style="display:block;">Pagina attiva</strong><small style="color:var(--admin-muted);">Se disattivata, il frontend mostra i fallback.</small></span></label>
    </div>
  </div>

  <div class="admin-card" style="margin-bottom:1rem;"><h2 style="font-size:1rem;margin-bottom:1rem;">Introduzione</h2>
    <div class="form-group"><label class="form-label">Kicker</label><input class="form-input" name="intro_kicker" value="{{ old('intro_kicker', $intro['kicker'] ?? 'Il contesto') }}" maxlength="120"></div>
    <div class="form-group"><label class="form-label">Titolo</label><input class="form-input" name="intro_title" value="{{ old('intro_title', $intro['title'] ?? 'Dal messaggio cifrato alla macchina universale') }}" required maxlength="180"></div>
    <div class="form-group"><label class="form-label">Testo</label><textarea class="form-textarea" name="intro_text" required maxlength="900">{{ old('intro_text', $intro['text'] ?? '') }}</textarea></div>
    {!! $imageField('intro_background_image', old('intro_background_image', $intro['background_image'] ?? ''), 'Immagine di sfondo introduzione /turing') !!}
  </div>

  <div class="admin-card" style="margin-bottom:1rem;">
    <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;margin-bottom:1rem;"><h2 style="font-size:1rem;margin:0;">Blocchi editoriali Turing</h2><button type="button" class="btn btn--secondary btn--sm" data-add-row="editorial_blocks">+ Aggiungi sezione</button></div>
    <p style="color:var(--admin-muted);font-size:.85rem;margin-top:-.5rem;">Gestisci Enigma, Macchina universale, Test di Turing, AI moderna o nuove sezioni future.</p>
    <div id="editorial_blocks-list" class="js-list" data-name="editorial_blocks">
      @foreach(old('editorial_blocks', $editorialBlocks) as $i => $block)
        <div class="admin-box js-row" style="padding:1rem;margin-bottom:.8rem;">
          <div style="display:grid;grid-template-columns:130px 120px 1fr auto;gap:.75rem;align-items:end;">
            <div class="form-group"><label class="form-label">Attiva</label><label style="display:flex;gap:.45rem;align-items:center;padding:.65rem;background:#f9fafb;border-radius:8px;"><input type="checkbox" name="editorial_blocks[{{ $i }}][enabled]" value="1" @checked($block['enabled'] ?? true)> Visibile</label></div>
            <div class="form-group"><label class="form-label">Chiave</label><input class="form-input" name="editorial_blocks[{{ $i }}][key]" value="{{ $block['key'] ?? '' }}" maxlength="80"></div>
            <div class="form-group"><label class="form-label">Titolo</label><input class="form-input" name="editorial_blocks[{{ $i }}][title]" value="{{ $block['title'] ?? '' }}" maxlength="180"></div>
            <button type="button" class="btn btn--danger btn--sm js-remove-row">Rimuovi</button>
          </div>
          <div style="display:grid;grid-template-columns:1fr 220px;gap:.75rem;"><div class="form-group"><label class="form-label">Kicker</label><input class="form-input" name="editorial_blocks[{{ $i }}][kicker]" value="{{ $block['kicker'] ?? '' }}" maxlength="120"></div><div class="form-group"><label class="form-label">Layout</label><select class="form-select" name="editorial_blocks[{{ $i }}][layout]"><option value="text" @selected(($block['layout'] ?? '')==='text')>Solo testo</option><option value="image_left" @selected(($block['layout'] ?? '')==='image_left')>Immagine sinistra</option><option value="image_right" @selected(($block['layout'] ?? '')==='image_right')>Immagine destra</option><option value="dark_card" @selected(($block['layout'] ?? '')==='dark_card')>Card scura</option><option value="feature_grid" @selected(($block['layout'] ?? '')==='feature_grid')>Feature grid</option></select></div></div>
          <div class="form-group"><label class="form-label">Testo</label><textarea class="form-textarea" name="editorial_blocks[{{ $i }}][text]" maxlength="1400" style="min-height:120px;">{{ $block['text'] ?? '' }}</textarea></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">{!! $imageField("editorial_blocks[$i][image]", $block['image'] ?? '', 'Immagine sezione') !!}{!! $imageField("editorial_blocks[$i][background_image]", $block['background_image'] ?? '', 'Immagine di sfondo sezione') !!}</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;"><div class="form-group"><label class="form-label">Label CTA</label><input class="form-input" name="editorial_blocks[{{ $i }}][link_label]" value="{{ $block['link_label'] ?? '' }}" maxlength="100"></div><div class="form-group"><label class="form-label">URL CTA / anchor</label><input class="form-input" name="editorial_blocks[{{ $i }}][link_url]" value="{{ $block['link_url'] ?? '' }}" maxlength="255"></div></div>
        </div>
      @endforeach
    </div>
  </div>

  <div class="admin-card" style="margin-bottom:1rem;">
    <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;margin-bottom:1rem;"><h2 style="font-size:1rem;margin:0;">Approfondimenti interni</h2><button type="button" class="btn btn--secondary btn--sm" data-add-row="internal_links">+ Aggiungi approfondimento</button></div>
    <div id="internal_links-list" class="js-list" data-name="internal_links">
      @foreach(old('internal_links', $internalLinks) as $i => $link)
        <div class="admin-box js-row" style="padding:1rem;margin-bottom:.8rem;"><div style="display:grid;grid-template-columns:1fr 1fr auto;gap:.75rem;align-items:end;"><div class="form-group"><label class="form-label">Titolo</label><input class="form-input" name="internal_links[{{ $i }}][title]" value="{{ $link['title'] ?? '' }}" maxlength="150"></div><div class="form-group"><label class="form-label">URL articolo/pagina</label><input class="form-input" name="internal_links[{{ $i }}][url]" value="{{ $link['url'] ?? '' }}" maxlength="255"></div><button type="button" class="btn btn--danger btn--sm js-remove-row">Rimuovi</button></div><div class="form-group"><label class="form-label">Descrizione</label><textarea class="form-textarea" name="internal_links[{{ $i }}][description]" maxlength="300" style="min-height:80px;">{{ $link['description'] ?? '' }}</textarea></div>{!! $imageField("internal_links[$i][image]", $link['image'] ?? '', 'Immagine approfondimento') !!}</div>
      @endforeach
    </div>
  </div>

  <div class="admin-card" style="margin-bottom:1rem;">
    <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;margin-bottom:1rem;"><h2 style="font-size:1rem;margin:0;">Immagini decorative</h2><button type="button" class="btn btn--secondary btn--sm" data-add-row="decorative_images">+ Aggiungi immagine</button></div>
    <div id="decorative_images-list" class="js-list" data-name="decorative_images">
      @foreach(old('decorative_images', $decorativeImages) as $i => $image)
        <div class="admin-box js-row" style="padding:1rem;margin-bottom:.8rem;"><div style="display:grid;grid-template-columns:1fr 180px auto;gap:.75rem;align-items:end;"><div class="form-group"><label class="form-label">Label</label><input class="form-input" name="decorative_images[{{ $i }}][label]" value="{{ $image['label'] ?? '' }}" maxlength="120"></div><div class="form-group"><label class="form-label">Posizione</label><input class="form-input" name="decorative_images[{{ $i }}][position]" value="{{ $image['position'] ?? '' }}" maxlength="80" placeholder="hero, timeline, footer..."></div><button type="button" class="btn btn--danger btn--sm js-remove-row">Rimuovi</button></div>{!! $imageField("decorative_images[$i][image]", $image['image'] ?? '', 'Immagine decorativa') !!}</div>
      @endforeach
    </div>
  </div>

  <div class="admin-card" style="margin-bottom:1rem;">
    <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;margin-bottom:1rem;"><h2 style="font-size:1rem;margin:0;">Cards dinamiche</h2><button type="button" class="btn btn--secondary btn--sm" data-add-row="cards">+ Aggiungi card</button></div>
    <div id="cards-list" class="js-list" data-name="cards">@foreach(old('cards', $cards) as $i => $card)<div class="admin-box js-row" style="padding:1rem;margin-bottom:.8rem;"><div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:.75rem;align-items:end;"><div class="form-group"><label class="form-label">Label</label><input class="form-input" name="cards[{{ $i }}][label]" value="{{ $card['label'] ?? '' }}" maxlength="120"></div><div class="form-group"><label class="form-label">Titolo</label><input class="form-input" name="cards[{{ $i }}][title]" value="{{ $card['title'] ?? '' }}" maxlength="150"></div><div class="form-group"><label class="form-label">Stile</label><select class="form-select" name="cards[{{ $i }}][style]"><option value="enigma" @selected(($card['style'] ?? '') === 'enigma')>Enigma</option><option value="ai" @selected(($card['style'] ?? '') === 'ai')>AI</option><option value="legacy" @selected(($card['style'] ?? '') === 'legacy')>Legacy</option></select></div><button type="button" class="btn btn--danger btn--sm js-remove-row">Rimuovi</button></div><div class="form-group"><label class="form-label">Testo</label><textarea class="form-textarea" name="cards[{{ $i }}][text]" maxlength="500" style="min-height:90px;">{{ $card['text'] ?? '' }}</textarea></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;"><div class="form-group"><label class="form-label">URL / anchor</label><input class="form-input" name="cards[{{ $i }}][url]" value="{{ $card['url'] ?? '' }}" maxlength="255"></div>{!! $imageField("cards[$i][image]", $card['image'] ?? '', 'Immagine card percorso') !!}</div></div>@endforeach</div>
  </div>

  <div class="admin-card" style="margin-bottom:1rem;"><h2 style="font-size:1rem;margin-bottom:1rem;">Perché conta oggi</h2><div class="form-group"><label class="form-label">Kicker</label><input class="form-input" name="why_kicker" value="{{ old('why_kicker', $why['kicker'] ?? 'Perché oggi') }}" maxlength="120"></div><div class="form-group"><label class="form-label">Titolo</label><input class="form-input" name="why_title" value="{{ old('why_title', $why['title'] ?? 'Turing non è solo storia dell’informatica') }}" required maxlength="180"></div><div class="form-group"><label class="form-label">Testo</label><textarea class="form-textarea" name="why_text" required maxlength="1000">{{ old('why_text', $why['text'] ?? '') }}</textarea></div>{!! $imageField('why_background_image', old('why_background_image', $why['background_image'] ?? ''), 'Immagine di sfondo sezione') !!}<div style="display:flex;justify-content:space-between;align-items:center;margin:.5rem 0 1rem;"><h3 style="font-size:.9rem;margin:0;">Punti chiave</h3><button type="button" class="btn btn--secondary btn--sm" data-add-row="why_items">+ Aggiungi punto</button></div><div id="why_items-list" class="js-list" data-name="why_items">@foreach(old('why_items', $whyItems) as $i => $item)<div class="admin-box js-row" style="padding:1rem;margin-bottom:.8rem;display:grid;grid-template-columns:1fr 2fr 1fr auto;gap:.75rem;align-items:end;"><div class="form-group"><label class="form-label">Titolo</label><input class="form-input" name="why_items[{{ $i }}][title]" value="{{ $item['title'] ?? '' }}" maxlength="100"></div><div class="form-group"><label class="form-label">Testo</label><input class="form-input" name="why_items[{{ $i }}][text]" value="{{ $item['text'] ?? '' }}" maxlength="200"></div>{!! $imageField("why_items[$i][image]", $item['image'] ?? '', 'Immagine') !!}<button type="button" class="btn btn--danger btn--sm js-remove-row">Rimuovi</button></div>@endforeach</div></div>

  <div class="admin-card" style="margin-bottom:1rem;"><div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;margin-bottom:1rem;"><h2 style="font-size:1rem;margin:0;">Timeline</h2><button type="button" class="btn btn--secondary btn--sm" data-add-row="timeline">+ Aggiungi evento</button></div><div id="timeline-list" class="js-list" data-name="timeline">@foreach(old('timeline', $timeline) as $i => $event)<div class="admin-box js-row" style="padding:1rem;margin-bottom:.8rem;"><div style="display:grid;grid-template-columns:120px 1fr auto;gap:.75rem;align-items:end;"><div class="form-group"><label class="form-label">Anno</label><input class="form-input" name="timeline[{{ $i }}][year]" value="{{ $event['year'] ?? '' }}" maxlength="30"></div><div class="form-group"><label class="form-label">Titolo</label><input class="form-input" name="timeline[{{ $i }}][title]" value="{{ $event['title'] ?? '' }}" maxlength="150"></div><button type="button" class="btn btn--danger btn--sm js-remove-row">Rimuovi</button></div><div class="form-group"><label class="form-label">Testo</label><textarea class="form-textarea" name="timeline[{{ $i }}][text]" maxlength="600" style="min-height:90px;">{{ $event['text'] ?? '' }}</textarea></div>{!! $imageField("timeline[$i][image]", $event['image'] ?? '', 'Immagine evento timeline') !!}</div>@endforeach</div></div>

  <div class="admin-card" style="margin-bottom:1rem;"><h2 style="font-size:1rem;margin-bottom:1rem;">Chiusura</h2><div class="form-group"><label class="form-label">Kicker</label><input class="form-input" name="final_kicker" value="{{ old('final_kicker', $final['kicker'] ?? 'Domanda aperta') }}" maxlength="120"></div><div class="form-group"><label class="form-label">Titolo</label><input class="form-input" name="final_title" value="{{ old('final_title', $final['title'] ?? 'La domanda resta viva') }}" required maxlength="180"></div><div class="form-group"><label class="form-label">Testo</label><textarea class="form-textarea" name="final_text" required maxlength="500">{{ old('final_text', $final['text'] ?? '') }}</textarea></div>{!! $imageField('final_background_image', old('final_background_image', $final['background_image'] ?? ''), 'Immagine di sfondo chiusura') !!}</div>

  <div style="position:sticky;bottom:1rem;z-index:5;display:flex;justify-content:flex-end;gap:.75rem;padding:1rem;border:1px solid var(--admin-border);border-radius:14px;background:rgba(255,255,255,.94);box-shadow:var(--admin-shadow);"><a href="{{ route('turing') }}" target="_blank" class="btn btn--secondary">Anteprima</a><button type="submit" class="btn btn--primary">Salva speciale Turing</button></div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const mediaField = (name, label = 'Immagine') => `<div class="form-group"><label class="form-label">${label}</label><div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:.85rem;margin-bottom:.5rem"><input type="file" name="${name}_upload" accept="image/jpeg,image/png,image/webp" style="font-size:.82rem;padding:.4rem;border:1px solid #e5e7eb;border-radius:6px;background:#fff;width:100%"><div style="font-size:.68rem;color:#6b7280;margin-top:.35rem">Carica nuova immagine, max 16 MB.</div></div><input class="form-input" name="${name}" maxlength="500" placeholder="oppure nome file dalla libreria media"><div style="margin-top:.35rem"><a href="{{ route('admin.media') }}" target="_blank" style="font-size:.72rem;color:#0d9488">📁 Libreria media →</a></div></div>`;
  const templates = {
    editorial_blocks: index => `<div class="admin-box js-row" style="padding:1rem;margin-bottom:.8rem;"><div style="display:grid;grid-template-columns:130px 120px 1fr auto;gap:.75rem;align-items:end;"><div class="form-group"><label class="form-label">Attiva</label><label style="display:flex;gap:.45rem;align-items:center;padding:.65rem;background:#f9fafb;border-radius:8px;"><input type="checkbox" name="editorial_blocks[${index}][enabled]" value="1" checked> Visibile</label></div><div class="form-group"><label class="form-label">Chiave</label><input class="form-input" name="editorial_blocks[${index}][key]" maxlength="80"></div><div class="form-group"><label class="form-label">Titolo</label><input class="form-input" name="editorial_blocks[${index}][title]" maxlength="180"></div><button type="button" class="btn btn--danger btn--sm js-remove-row">Rimuovi</button></div><div style="display:grid;grid-template-columns:1fr 220px;gap:.75rem;"><div class="form-group"><label class="form-label">Kicker</label><input class="form-input" name="editorial_blocks[${index}][kicker]" maxlength="120"></div><div class="form-group"><label class="form-label">Layout</label><select class="form-select" name="editorial_blocks[${index}][layout]"><option value="text">Solo testo</option><option value="image_left">Immagine sinistra</option><option value="image_right">Immagine destra</option><option value="dark_card">Card scura</option><option value="feature_grid">Feature grid</option></select></div></div><div class="form-group"><label class="form-label">Testo</label><textarea class="form-textarea" name="editorial_blocks[${index}][text]" maxlength="1400" style="min-height:120px;"></textarea></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">${mediaField(`editorial_blocks[${index}][image]`, 'Immagine sezione')}${mediaField(`editorial_blocks[${index}][background_image]`, 'Immagine di sfondo sezione')}</div><div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;"><div class="form-group"><label class="form-label">Label CTA</label><input class="form-input" name="editorial_blocks[${index}][link_label]" maxlength="100"></div><div class="form-group"><label class="form-label">URL CTA / anchor</label><input class="form-input" name="editorial_blocks[${index}][link_url]" maxlength="255"></div></div></div>`,
    internal_links: index => `<div class="admin-box js-row" style="padding:1rem;margin-bottom:.8rem;"><div style="display:grid;grid-template-columns:1fr 1fr auto;gap:.75rem;align-items:end;"><div class="form-group"><label class="form-label">Titolo</label><input class="form-input" name="internal_links[${index}][title]" maxlength="150"></div><div class="form-group"><label class="form-label">URL articolo/pagina</label><input class="form-input" name="internal_links[${index}][url]" maxlength="255"></div><button type="button" class="btn btn--danger btn--sm js-remove-row">Rimuovi</button></div><div class="form-group"><label class="form-label">Descrizione</label><textarea class="form-textarea" name="internal_links[${index}][description]" maxlength="300" style="min-height:80px;"></textarea></div>${mediaField(`internal_links[${index}][image]`, 'Immagine approfondimento')}</div>`,
    decorative_images: index => `<div class="admin-box js-row" style="padding:1rem;margin-bottom:.8rem;"><div style="display:grid;grid-template-columns:1fr 180px auto;gap:.75rem;align-items:end;"><div class="form-group"><label class="form-label">Label</label><input class="form-input" name="decorative_images[${index}][label]" maxlength="120"></div><div class="form-group"><label class="form-label">Posizione</label><input class="form-input" name="decorative_images[${index}][position]" maxlength="80" placeholder="hero, timeline, footer..."></div><button type="button" class="btn btn--danger btn--sm js-remove-row">Rimuovi</button></div>${mediaField(`decorative_images[${index}][image]`, 'Immagine decorativa')}</div>`,
    cards: index => `<div class="admin-box js-row" style="padding:1rem;margin-bottom:.8rem;"><div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:.75rem;align-items:end;"><div class="form-group"><label class="form-label">Label</label><input class="form-input" name="cards[${index}][label]" maxlength="120"></div><div class="form-group"><label class="form-label">Titolo</label><input class="form-input" name="cards[${index}][title]" maxlength="150"></div><div class="form-group"><label class="form-label">Stile</label><select class="form-select" name="cards[${index}][style]"><option value="enigma">Enigma</option><option value="ai">AI</option><option value="legacy">Legacy</option></select></div><button type="button" class="btn btn--danger btn--sm js-remove-row">Rimuovi</button></div><div class="form-group"><label class="form-label">Testo</label><textarea class="form-textarea" name="cards[${index}][text]" maxlength="500" style="min-height:90px;"></textarea></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;"><div class="form-group"><label class="form-label">URL / anchor</label><input class="form-input" name="cards[${index}][url]" maxlength="255"></div>${mediaField(`cards[${index}][image]`, 'Immagine card percorso')}</div></div>`,
    why_items: index => `<div class="admin-box js-row" style="padding:1rem;margin-bottom:.8rem;display:grid;grid-template-columns:1fr 2fr 1fr auto;gap:.75rem;align-items:end;"><div class="form-group"><label class="form-label">Titolo</label><input class="form-input" name="why_items[${index}][title]" maxlength="100"></div><div class="form-group"><label class="form-label">Testo</label><input class="form-input" name="why_items[${index}][text]" maxlength="200"></div>${mediaField(`why_items[${index}][image]`, 'Immagine')}<button type="button" class="btn btn--danger btn--sm js-remove-row">Rimuovi</button></div>`,
    timeline: index => `<div class="admin-box js-row" style="padding:1rem;margin-bottom:.8rem;"><div style="display:grid;grid-template-columns:120px 1fr auto;gap:.75rem;align-items:end;"><div class="form-group"><label class="form-label">Anno</label><input class="form-input" name="timeline[${index}][year]" maxlength="30"></div><div class="form-group"><label class="form-label">Titolo</label><input class="form-input" name="timeline[${index}][title]" maxlength="150"></div><button type="button" class="btn btn--danger btn--sm js-remove-row">Rimuovi</button></div><div class="form-group"><label class="form-label">Testo</label><textarea class="form-textarea" name="timeline[${index}][text]" maxlength="600" style="min-height:90px;"></textarea></div>${mediaField(`timeline[${index}][image]`, 'Immagine evento timeline')}</div>`
  };
  document.querySelectorAll('[data-add-row]').forEach(button => button.addEventListener('click', () => { const name = button.dataset.addRow; const list = document.querySelector(`#${name}-list`); const index = list.querySelectorAll('.js-row').length; list.insertAdjacentHTML('beforeend', templates[name](index)); }));
  document.addEventListener('click', event => { const remove = event.target.closest('.js-remove-row'); if (!remove) return; remove.closest('.js-row')?.remove(); });
});
</script>
@endpush
