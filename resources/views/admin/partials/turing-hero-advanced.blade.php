@php
  $terminalLines = $hero['terminal_lines'] ?? [
      'ENIGMA SIGNAL FOUND',
      'MACHINE INTELLIGENCE: ACTIVE',
      'QUESTION: CAN MACHINES THINK?',
      'STATUS: STILL OPEN',
  ];
@endphp

<div class="admin-card" style="margin-bottom:1rem;">
  <h2 style="font-size:1rem;margin-bottom:.35rem;">Hero principale / area iniziale</h2>
  <p style="color:var(--admin-muted);font-size:.85rem;margin:0 0 1rem;">
    Gestisce la hero principale della pagina /turing. Nota: lo sfondo hero e il riquadro laterale AT sono due immagini diverse.
  </p>

  <div class="form-group">
    <label class="form-label">Kicker</label>
    <input class="form-input" name="hero_kicker" value="{{ old('hero_kicker', $hero['kicker'] ?? 'QUARK SPECIAL PROJECT') }}" maxlength="120">
  </div>

  <div class="form-group">
    <label class="form-label">Titolo hero</label>
    <input class="form-input" name="hero_title" value="{{ old('hero_title', $hero['title'] ?? 'Alan Turing: l’uomo che ha decifrato il futuro') }}" required maxlength="150">
  </div>

  <div class="form-group">
    <label class="form-label">Lead / descrizione iniziale</label>
    <textarea class="form-textarea" name="hero_lead" required maxlength="900">{{ old('hero_lead', $hero['lead'] ?? 'Una nuova area speciale di Quark dedicata a Enigma, alla nascita del computer, al Test di Turing e al legame con l’intelligenza artificiale moderna.') }}</textarea>
  </div>

  <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;">
    <div class="form-group">
      <label class="form-label">CTA primaria</label>
      <input class="form-input" name="hero_primary_label" value="{{ old('hero_primary_label', $hero['primary_label'] ?? 'Esplora Enigma') }}" maxlength="80">
    </div>
    <div class="form-group">
      <label class="form-label">CTA secondaria</label>
      <input class="form-input" name="hero_secondary_label" value="{{ old('hero_secondary_label', $hero['secondary_label'] ?? 'Vai all’IA moderna') }}" maxlength="80">
    </div>
    <div class="form-group">
      <label class="form-label">Iniziali riquadro</label>
      <input class="form-input" name="hero_portrait_initials" value="{{ old('hero_portrait_initials', $hero['portrait_initials'] ?? 'AT') }}" maxlength="12">
    </div>
    <div class="form-group">
      <label class="form-label">Anni riquadro</label>
      <input class="form-input" name="hero_portrait_years" value="{{ old('hero_portrait_years', $hero['portrait_years'] ?? '1912 / 1954') }}" maxlength="40">
    </div>
    <div class="form-group">
      <label class="form-label">Nome / titolo riquadro</label>
      <input class="form-input" name="hero_portrait_title" value="{{ old('hero_portrait_title', $hero['portrait_title'] ?? 'Alan Mathison Turing') }}" maxlength="150">
    </div>
    <div class="form-group">
      <label class="form-label">Descrizione biografica</label>
      <input class="form-input" name="hero_portrait_text" value="{{ old('hero_portrait_text', $hero['portrait_text'] ?? '1912–1954 · Matematico, logico, pioniere dell’informatica') }}" maxlength="220">
    </div>
    {!! $imageField('hero_background_image', old('hero_background_image', $hero['background_image'] ?? ''), 'Immagine di sfondo hero /turing') !!}
    {!! $imageField('hero_portrait_image', old('hero_portrait_image', $hero['portrait_image'] ?? ''), 'Immagine riquadro laterale / portrait, sostituisce il box AT') !!}
  </div>
</div>

<div class="admin-card" style="margin-bottom:1rem;">
  <h2 style="font-size:1rem;margin-bottom:.35rem;">Terminale / Turing Archive</h2>
  <p style="color:var(--admin-muted);font-size:.85rem;margin:0 0 1rem;">
    Gestisce le righe tipo “ENIGMA SIGNAL FOUND” e “MACHINE INTELLIGENCE: ACTIVE”.
  </p>

  <div class="form-group">
    <label class="form-label">Titolo terminale</label>
    <input class="form-input" name="hero_terminal_title" value="{{ old('hero_terminal_title', $hero['terminal_title'] ?? 'TURING ARCHIVE') }}" maxlength="120">
  </div>

  <div style="display:flex;justify-content:space-between;align-items:center;margin:1rem 0 .75rem;">
    <h3 style="font-size:.9rem;margin:0;">Righe terminale</h3>
    <button type="button" class="btn btn--secondary btn--sm" data-add-row="hero_terminal_lines">+ Aggiungi riga</button>
  </div>

  <div id="hero_terminal_lines-list" class="js-list" data-name="hero_terminal_lines">
    @foreach(old('hero_terminal_lines', $terminalLines) as $i => $line)
      <div class="admin-box js-row" style="padding:.75rem;margin-bottom:.5rem;display:grid;grid-template-columns:1fr auto;gap:.75rem;align-items:center;">
        <input class="form-input" name="hero_terminal_lines[{{ $i }}]" value="{{ $line }}" maxlength="160">
        <button type="button" class="btn btn--danger btn--sm js-remove-row">Rimuovi</button>
      </div>
    @endforeach
  </div>
</div>
