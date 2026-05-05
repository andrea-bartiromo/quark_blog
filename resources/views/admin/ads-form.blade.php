{{-- Form fields riutilizzabile per nuovo/modifica annuncio --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
  <div class="form-group">
    <label class="form-label">Nome interno *</label>
    <input class="form-input" type="text" name="name"
           value="{{ old('name', $ad->name ?? '') }}"
           placeholder="es. AdSense Sidebar" required>
  </div>
  <div class="form-group">
    <label class="form-label">Posizione *</label>
    <select class="form-select" name="position" required>
      @foreach(\App\Models\Ad::POSITIONS as $slug => $label)
        <option value="{{ $slug }}"
                {{ old('position', $ad->position ?? '') === $slug ? 'selected' : '' }}>
          {{ $label }}
        </option>
      @endforeach
    </select>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.75rem;">
  <div class="form-group">
    <label class="form-label">Tipo *</label>
    <select class="form-select" name="type" required>
      @foreach(\App\Models\Ad::TYPES as $value => $label)
        <option value="{{ $value }}"
                {{ old('type', $ad->type ?? 'adsense') === $value ? 'selected' : '' }}>
          {{ $label }}
        </option>
      @endforeach
    </select>
  </div>
  <div class="form-group">
    <label class="form-label">Priorità (0-100)</label>
    <input class="form-input" type="number" name="priority" min="0" max="100"
           value="{{ old('priority', $ad->priority ?? 0) }}">
  </div>
  <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:.25rem;">
    <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.82rem;font-weight:600;color:#111827;">
      <input type="checkbox" name="active" value="1"
             {{ old('active', $ad->active ?? false) ? 'checked' : '' }}
             style="width:16px;height:16px;accent-color:#0d9488;">
      Attivo
    </label>
  </div>
</div>

{{-- Campi AdSense --}}
<div class="fields-adsense">
  <div style="background:#eff6ff;border-radius:6px;padding:.85rem;margin-bottom:.75rem;">
    <div style="font-size:.7rem;font-weight:700;color:#1e40af;margin-bottom:.65rem;">
      Google AdSense
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.65rem;">
      <div class="form-group" style="margin:0;">
        <label class="form-label">Publisher ID</label>
        <input class="form-input" type="text" name="adsense_publisher_id"
               value="{{ old('adsense_publisher_id', $ad->adsense_publisher_id ?? '') }}"
               placeholder="ca-pub-XXXXXXXXXXXXXXXX" style="font-size:.78rem;">
      </div>
      <div class="form-group" style="margin:0;">
        <label class="form-label">Slot ID</label>
        <input class="form-input" type="text" name="adsense_slot_id"
               value="{{ old('adsense_slot_id', $ad->adsense_slot_id ?? '') }}"
               placeholder="1234567890" style="font-size:.78rem;">
      </div>
      <div class="form-group" style="margin:0;">
        <label class="form-label">Formato</label>
        <select class="form-select" name="adsense_format" style="font-size:.78rem;">
          @foreach(['auto' => 'Auto', 'horizontal' => 'Orizzontale', 'rectangle' => 'Rettangolo', 'vertical' => 'Verticale'] as $v => $l)
            <option value="{{ $v }}" {{ old('adsense_format', $ad->adsense_format ?? 'auto') === $v ? 'selected' : '' }}>{{ $l }}</option>
          @endforeach
        </select>
      </div>
    </div>
  </div>
</div>

{{-- Campi Banner --}}
<div class="fields-banner">
  <div style="background:#fefce8;border-radius:6px;padding:.85rem;margin-bottom:.75rem;">
    <div style="font-size:.7rem;font-weight:700;color:#854d0e;margin-bottom:.65rem;">
      Banner immagine
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.65rem;">
      <div class="form-group" style="margin:0;">
        <label class="form-label">Nome file immagine</label>
        <input class="form-input" type="text" name="banner_image"
               value="{{ old('banner_image', $ad->banner_image ?? '') }}"
               placeholder="banner-sponsor.jpg" style="font-size:.78rem;">
        <div style="font-size:.65rem;color:#6b7280;margin-top:.2rem;">
          Carica prima dalla <a href="{{ route('admin.media') }}" target="_blank" style="color:#0d9488;">libreria media</a>
        </div>
      </div>
      <div class="form-group" style="margin:0;">
        <label class="form-label">URL di destinazione</label>
        <input class="form-input" type="url" name="banner_url"
               value="{{ old('banner_url', $ad->banner_url ?? '') }}"
               placeholder="https://www.sponsor.it" style="font-size:.78rem;">
      </div>
    </div>
    <div class="form-group" style="margin:.65rem 0 0;">
      <label class="form-label">Testo alternativo</label>
      <input class="form-input" type="text" name="banner_alt"
             value="{{ old('banner_alt', $ad->banner_alt ?? '') }}"
             placeholder="Descrizione del banner" style="font-size:.78rem;">
    </div>
  </div>
</div>

{{-- Campi HTML personalizzato --}}
<div class="fields-html">
  <div style="background:#f5f3ff;border-radius:6px;padding:.85rem;margin-bottom:.75rem;">
    <div style="font-size:.7rem;font-weight:700;color:#5b21b6;margin-bottom:.65rem;">
      Codice HTML personalizzato
    </div>
    <textarea class="form-textarea" name="html_code"
              style="font-family:monospace;font-size:.78rem;min-height:100px;"
              placeholder="Incolla qui il codice HTML del banner o script pubblicitario...">{{ old('html_code', $ad->html_code ?? '') }}</textarea>
  </div>
</div>

<div class="form-group">
  <label class="form-label">Note interne (opzionale)</label>
  <input class="form-input" type="text" name="notes"
         value="{{ old('notes', $ad->notes ?? '') }}"
         placeholder="es. Contratto attivo fino a dicembre 2026">
</div>