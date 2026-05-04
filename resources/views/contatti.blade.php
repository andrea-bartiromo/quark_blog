@extends('layouts.app')

@section('title', 'Contatti — '.config('laboratorio.name'))
@section('description', 'Contatta la redazione de Il Laboratorio per comunicati stampa, segnalazioni o collaborazioni.')

@section('content')
<div class="container" style="padding-block:2.5rem;">

  <div style="max-width:700px;margin-bottom:2.5rem;">
    <hr style="border:none;border-top:3px solid var(--color-ink);margin:0 0 .5rem;">
    <h1 style="font-family:var(--font-display);font-size:clamp(1.8rem,4vw,2.6rem);font-weight:900;margin-bottom:.75rem;">
      Contatti
    </h1>
    <p style="font-size:1.05rem;color:var(--color-ink-soft);line-height:1.7;">
      Sei un ricercatore, un ufficio stampa o un lettore con una segnalazione?
      Scrivici — leggiamo tutto.
    </p>
  </div>

  <div style="display:grid;grid-template-columns:1fr 340px;gap:2.5rem;align-items:start;">

    {{-- Form contatto --}}
    <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:2rem;">
      <h2 style="font-family:var(--font-display);font-size:1.2rem;font-weight:700;margin-bottom:1.25rem;">
        Inviaci un messaggio
      </h2>

      @if(session('contact_sent'))
        <div style="background:#e8f5e9;border:1px solid #a5d6a7;border-radius:var(--radius);
                    padding:1rem;font-family:var(--font-ui);font-size:.88rem;color:#2e7d32;margin-bottom:1rem;">
          ✓ Messaggio inviato. Ti risponderemo entro 24 ore lavorative.
        </div>
      @endif

      <form method="POST" action="{{ route('contatti.send') }}" novalidate>
        @csrf

        <div class="form-group">
          <label class="form-label" for="nome">Nome e cognome *</label>
          <input class="form-input" type="text" id="nome" name="nome"
                 value="{{ old('nome') }}" required maxlength="100"
                 placeholder="Mario Rossi">
          @error('nome') <span style="color:var(--color-accent);font-size:.78rem;">{{ $message }}</span> @enderror
        </div>

        <div class="form-group">
          <label class="form-label" for="email">Email *</label>
          <input class="form-input" type="email" id="email" name="email"
                 value="{{ old('email') }}" required
                 placeholder="mario@esempio.it">
          @error('email') <span style="color:var(--color-accent);font-size:.78rem;">{{ $message }}</span> @enderror
        </div>

        <div class="form-group">
          <label class="form-label" for="oggetto">Oggetto *</label>
          <select class="form-select" id="oggetto" name="oggetto" required>
            <option value="">Seleziona…</option>
            <option value="comunicato" {{ old('oggetto') === 'comunicato' ? 'selected' : '' }}>Comunicato stampa</option>
            <option value="segnalazione" {{ old('oggetto') === 'segnalazione' ? 'selected' : '' }}>Segnalazione notizia</option>
            <option value="collaborazione" {{ old('oggetto') === 'collaborazione' ? 'selected' : '' }}>Proposta di collaborazione</option>
            <option value="pubblicita" {{ old('oggetto') === 'pubblicita' ? 'selected' : '' }}>Informazioni pubblicità</option>
            <option value="altro" {{ old('oggetto') === 'altro' ? 'selected' : '' }}>Altro</option>
          </select>
          @error('oggetto') <span style="color:var(--color-accent);font-size:.78rem;">{{ $message }}</span> @enderror
        </div>

        <div class="form-group">
          <label class="form-label" for="messaggio">Messaggio *</label>
          <textarea class="form-textarea" id="messaggio" name="messaggio"
                    required minlength="20" maxlength="2000"
                    placeholder="Scrivi il tuo messaggio…">{{ old('messaggio') }}</textarea>
          @error('messaggio') <span style="color:var(--color-accent);font-size:.78rem;">{{ $message }}</span> @enderror
        </div>

        <div class="form-group">
          <label class="form-checkbox">
            <input type="checkbox" name="privacy" required
                   {{ old('privacy') ? 'checked' : '' }}>
            Ho letto e accetto la <a href="{{ route('privacy') }}" style="color:var(--color-accent);">Privacy Policy</a> *
          </label>
          @error('privacy') <span style="color:var(--color-accent);font-size:.78rem;">{{ $message }}</span> @enderror
        </div>

        {{-- Honeypot --}}
        <div style="position:absolute;left:-9999px;" aria-hidden="true">
          <input type="text" name="website" tabindex="-1" autocomplete="off">
        </div>

        <button type="submit" class="btn btn--primary">Invia messaggio</button>
      </form>
    </div>

    {{-- Info contatti diretti --}}
    <div style="display:flex;flex-direction:column;gap:1rem;">

      <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.5rem;">
        <div style="font-family:var(--font-ui);font-size:.68rem;font-weight:700;text-transform:uppercase;
                    letter-spacing:.1em;border-bottom:2px solid var(--color-ink);padding-bottom:.5rem;margin-bottom:1rem;">
          Email dirette
        </div>
        <ul style="list-style:none;display:flex;flex-direction:column;gap:.6rem;font-family:var(--font-ui);font-size:.84rem;">
          <li>
            <div style="font-weight:600;color:var(--color-ink);margin-bottom:.1rem;">Redazione</div>
            <a href="mailto:redazione@illaboratorio.it" style="color:var(--color-accent);">redazione@illaboratorio.it</a>
          </li>
          <li>
            <div style="font-weight:600;color:var(--color-ink);margin-bottom:.1rem;">Pubblicità</div>
            <a href="mailto:pubblicita@illaboratorio.it" style="color:var(--color-accent);">pubblicita@illaboratorio.it</a>
          </li>
          <li>
            <div style="font-weight:600;color:var(--color-ink);margin-bottom:.1rem;">Privacy & GDPR</div>
            <a href="mailto:privacy@illaboratorio.it" style="color:var(--color-accent);">privacy@illaboratorio.it</a>
          </li>
          <li>
            <div style="font-weight:600;color:var(--color-ink);margin-bottom:.1rem;">Rettifiche</div>
            <a href="mailto:rettifiche@illaboratorio.it" style="color:var(--color-accent);">rettifiche@illaboratorio.it</a>
          </li>
        </ul>
      </div>

      <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.5rem;">
        <div style="font-family:var(--font-ui);font-size:.68rem;font-weight:700;text-transform:uppercase;
                    letter-spacing:.1em;border-bottom:2px solid var(--color-ink);padding-bottom:.5rem;margin-bottom:1rem;">
          Seguici
        </div>
        <div class="social-list">
          <a href="{{ config('laboratorio.social.facebook') }}"  class="s-fb" target="_blank" rel="noopener">👤 Facebook</a>
          <a href="{{ config('laboratorio.social.twitter') }}"   class="s-tw" target="_blank" rel="noopener">𝕏 X / Twitter</a>
          <a href="{{ config('laboratorio.social.instagram') }}" class="s-ig" target="_blank" rel="noopener">📷 Instagram</a>
          <a href="{{ config('laboratorio.social.telegram') }}"  class="s-tg" target="_blank" rel="noopener">✈️ Telegram</a>
        </div>
      </div>

      <div style="background:var(--color-paper-warm);border-radius:var(--radius);padding:1.25rem;
                  font-family:var(--font-ui);font-size:.82rem;color:var(--color-ink-soft);line-height:1.6;">
        <strong style="color:var(--color-ink);">Tempi di risposta:</strong><br>
        Rispondiamo a tutte le email entro 24-48 ore lavorative. Per richieste urgenti
        usa l'oggetto "URGENTE" nella riga del messaggio.
      </div>

    </div>

  </div>

</div>
@endsection
