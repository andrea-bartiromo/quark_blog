{{--
    EDITORIAL TRUST (Missione 27) — una riga dell'editor delle fonti.

    Usato in due modi con lo stesso markup: renderizzato per ogni fonte
    esistente, e una volta dentro <template> con $index = '__INDEX__' come
    modello per "Aggiungi fonte". Un solo file, così una modifica al markup
    non può far divergere le righe esistenti da quelle nuove.

    $index può quindi essere un intero O la stringa '__INDEX__': non va mai
    usato in calcoli, solo concatenato.
--}}
@php
    $fieldId = fn (string $name) => 'source-'.$index.'-'.$name;
    $errorKey = fn (string $name) => 'sources.'.$index.'.'.$name;
    $value = fn (string $name) => $row[$name] ?? '';
    // Numerazione umana solo quando l'indice è reale: nel <template> resta
    // un segnaposto che il JS sostituisce al primo reindex().
    $legend = is_int($index) ? 'Fonte '.($index + 1) : 'Nuova fonte';
@endphp

<li data-source-row data-source-index="{{ $index }}"
    style="border:1px solid var(--color-border, #e5e7eb);border-radius:var(--radius);padding:1rem;background:var(--color-white,#fff);">
  <fieldset style="border:0;padding:0;margin:0;min-width:0;">
    <legend data-source-legend
            style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--color-ink-muted);padding:0;">
      {{ $legend }}
    </legend>

    <input type="hidden" name="sources[{{ $index }}][id]" value="{{ $value('id') }}">

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:.75rem;margin-top:.65rem;">

      <div style="grid-column:1/-1;">
        <label class="form-label" data-field-for="title" for="{{ $fieldId('title') }}">
          Titolo <span aria-hidden="true">*</span><span class="sr-only">(obbligatorio)</span>
        </label>
        <input class="form-input" type="text"
               data-field-id="title" id="{{ $fieldId('title') }}"
               name="sources[{{ $index }}][title]"
               value="{{ $value('title') }}"
               maxlength="255" required
               @error($errorKey('title')) aria-invalid="true" aria-describedby="{{ $fieldId('title') }}-error" @enderror>
        @error($errorKey('title'))
          <p class="form-error" id="{{ $fieldId('title') }}-error" style="color:#dc2626;font-size:.76rem;margin:.25rem 0 0;">{{ $message }}</p>
        @enderror
      </div>

      <div>
        <label class="form-label" data-field-for="author_or_org" for="{{ $fieldId('author_or_org') }}">Autore o ente</label>
        <input class="form-input" type="text"
               data-field-id="author_or_org" id="{{ $fieldId('author_or_org') }}"
               name="sources[{{ $index }}][author_or_org]"
               value="{{ $value('author_or_org') }}"
               maxlength="255"
               placeholder="Es. ESA, Nature, M. Rossi">
        @error($errorKey('author_or_org'))
          <p class="form-error" style="color:#dc2626;font-size:.76rem;margin:.25rem 0 0;">{{ $message }}</p>
        @enderror
      </div>

      <div>
        <label class="form-label" data-field-for="source_type" for="{{ $fieldId('source_type') }}">Tipo di fonte</label>
        <select class="form-select"
                data-field-id="source_type" id="{{ $fieldId('source_type') }}"
                name="sources[{{ $index }}][source_type]">
          @foreach($sourceTypeLabels as $typeValue => $typeLabel)
            <option value="{{ $typeValue }}" @selected($value('source_type') === $typeValue)>{{ $typeLabel }}</option>
          @endforeach
        </select>
        <small style="display:block;font-size:.72rem;color:var(--color-ink-muted);margin-top:.2rem;">
          «Non specificato» non viene mostrato al lettore.
        </small>
      </div>

      <div style="grid-column:1/-1;">
        <label class="form-label" data-field-for="url" for="{{ $fieldId('url') }}">URL</label>
        <input class="form-input" type="url"
               data-field-id="url" id="{{ $fieldId('url') }}"
               name="sources[{{ $index }}][url]"
               value="{{ $value('url') }}"
               maxlength="2048"
               inputmode="url"
               placeholder="https://…"
               @error($errorKey('url')) aria-invalid="true" aria-describedby="{{ $fieldId('url') }}-error" @enderror>
        @error($errorKey('url'))
          <p class="form-error" id="{{ $fieldId('url') }}-error" style="color:#dc2626;font-size:.76rem;margin:.25rem 0 0;">{{ $message }}</p>
        @enderror
      </div>

      <div>
        <label class="form-label" data-field-for="doi" for="{{ $fieldId('doi') }}">DOI</label>
        <input class="form-input" type="text"
               data-field-id="doi" id="{{ $fieldId('doi') }}"
               name="sources[{{ $index }}][doi]"
               value="{{ $value('doi') }}"
               maxlength="255"
               placeholder="10.1234/identificativo"
               @error($errorKey('doi')) aria-invalid="true" aria-describedby="{{ $fieldId('doi') }}-error" @enderror>
        @error($errorKey('doi'))
          <p class="form-error" id="{{ $fieldId('doi') }}-error" style="color:#dc2626;font-size:.76rem;margin:.25rem 0 0;">{{ $message }}</p>
        @enderror
      </div>

      <div>
        <label class="form-label" data-field-for="published_on" for="{{ $fieldId('published_on') }}">Data di pubblicazione</label>
        <input class="form-input" type="date"
               data-field-id="published_on" id="{{ $fieldId('published_on') }}"
               name="sources[{{ $index }}][published_on]"
               value="{{ $value('published_on') }}">
        @error($errorKey('published_on'))
          <p class="form-error" style="color:#dc2626;font-size:.76rem;margin:.25rem 0 0;">{{ $message }}</p>
        @enderror
      </div>

      <div>
        <label class="form-label" data-field-for="accessed_on" for="{{ $fieldId('accessed_on') }}">Data di consultazione</label>
        <input class="form-input" type="date"
               data-field-id="accessed_on" id="{{ $fieldId('accessed_on') }}"
               name="sources[{{ $index }}][accessed_on]"
               value="{{ $value('accessed_on') }}">
        @error($errorKey('accessed_on'))
          <p class="form-error" style="color:#dc2626;font-size:.76rem;margin:.25rem 0 0;">{{ $message }}</p>
        @enderror
      </div>

      <div style="grid-column:1/-1;">
        <label class="form-label" data-field-for="editorial_note" for="{{ $fieldId('editorial_note') }}">Nota editoriale (facoltativa)</label>
        <textarea class="form-textarea" rows="2"
                  data-field-id="editorial_note" id="{{ $fieldId('editorial_note') }}"
                  name="sources[{{ $index }}][editorial_note]"
                  maxlength="1000"
                  placeholder="Es. Studio preliminare, non ancora sottoposto a revisione.">{{ $value('editorial_note') }}</textarea>
        @error($errorKey('editorial_note'))
          <p class="form-error" style="color:#dc2626;font-size:.76rem;margin:.25rem 0 0;">{{ $message }}</p>
        @enderror
      </div>
    </div>

    <p style="font-size:.76rem;color:var(--color-ink-muted);margin:.65rem 0 0;">
      <span style="font-weight:700;">Anteprima:</span>
      <span data-source-preview>L’anteprima compare appena compili la fonte.</span>
    </p>

    <div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-top:.65rem;">
      <button type="button" class="action-btn" data-source-up>↑ Su</button>
      <button type="button" class="action-btn" data-source-down>↓ Giù</button>
      <button type="button" class="action-btn" data-source-remove>Rimuovi</button>
    </div>
  </fieldset>
</li>
