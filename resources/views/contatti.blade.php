@extends('layouts.app')
@section('title', 'Contatti — Quark')
@section('description', 'Contatta la redazione di Quark per segnalazioni, proposte di collaborazione o domande.')

@section('content')
<div class="container" style="padding-block:3rem;max-width:780px;">

  <div style="margin-bottom:2.5rem;">
    <div class="hero-eyebrow" style="margin-bottom:1rem;">Scrivici</div>
    <h1 style="font-family:var(--font-display);font-size:2.2rem;font-weight:900;
               color:var(--ink);letter-spacing:-.02em;margin-bottom:.75rem;">Contatti</h1>
    <p style="font-size:1rem;color:var(--ink-soft);line-height:1.7;">
      Hai trovato un errore? Vuoi proporre un argomento? Hai domande sulla redazione?
      Scrivici — leggiamo tutto.
    </p>
  </div>

  @if(session('contact_sent'))
  <div style="background:#d1fae5;border:1px solid #6ee7b7;border-radius:10px;
              padding:1rem 1.25rem;margin-bottom:1.5rem;color:#065f46;font-size:.875rem;">
    ✅ Messaggio inviato! Ti risponderemo entro 48 ore.
  </div>
  @endif

  <div style="display:grid;grid-template-columns:1fr 280px;gap:2rem;align-items:start;">

    {{-- Form --}}
    <div style="background:white;border:1px solid var(--border);border-radius:16px;padding:1.75rem;">
      <form method="POST" action="{{ route('contatti.send') }}">
        @csrf
        {{-- Honeypot --}}
        <input type="text" name="website" style="display:none" tabindex="-1" autocomplete="off">

        <div class="form-group">
          <label class="form-label">Nome *</label>
          <input class="form-input" type="text" name="nome"
                 value="{{ old('nome') }}" required maxlength="100"
                 placeholder="Il tuo nome">
          @error('nome')<div style="color:#dc2626;font-size:.75rem;margin-top:.25rem;">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
          <label class="form-label">Email *</label>
          <input class="form-input" type="email" name="email"
                 value="{{ old('email') }}" required maxlength="150"
                 placeholder="La tua email">
          @error('email')<div style="color:#dc2626;font-size:.75rem;margin-top:.25rem;">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
          <label class="form-label">Oggetto *</label>
          <select class="form-select" name="oggetto" required>
            <option value="">Seleziona...</option>
            @foreach([
              'Segnalazione errore',
              'Proposta articolo',
              'Collaborazione',
              'Domanda editoriale',
              'Richiesta rettifica',
              'Altro',
            ] as $opt)
            <option value="{{ $opt }}" {{ old('oggetto') === $opt ? 'selected' : '' }}>
              {{ $opt }}
            </option>
            @endforeach
          </select>
          @error('oggetto')<div style="color:#dc2626;font-size:.75rem;margin-top:.25rem;">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
          <label class="form-label">Messaggio * <span style="color:var(--ink-muted);font-weight:400;">(min 20 caratteri)</span></label>
          <textarea class="form-textarea" name="messaggio" required
                    minlength="20" maxlength="2000"
                    placeholder="Scrivi qui il tuo messaggio...">{{ old('messaggio') }}</textarea>
          @error('messaggio')<div style="color:#dc2626;font-size:.75rem;margin-top:.25rem;">{{ $message }}</div>@enderror
        </div>

        <div class="form-group" style="display:flex;align-items:flex-start;gap:.5rem;">
          <input type="checkbox" name="privacy" id="privacy"
                 style="margin-top:3px;accent-color:var(--primary);"
                 {{ old('privacy') ? 'checked' : '' }} required>
          <label for="privacy" style="font-size:.78rem;color:var(--ink-muted);cursor:pointer;">
            Ho letto e accetto la
            <a href="{{ route('privacy') }}" style="color:var(--primary);">Privacy Policy</a>
          </label>
          @error('privacy')<div style="color:#dc2626;font-size:.75rem;margin-top:.25rem;">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn btn--primary" style="width:100%;">
          Invia messaggio
        </button>
      </form>
    </div>

    {{-- Info laterale --}}
    <div style="display:flex;flex-direction:column;gap:1rem;">

      <div style="background:var(--paper-warm);border-radius:12px;padding:1.25rem;">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;
                    letter-spacing:.1em;color:var(--ink-muted);margin-bottom:.85rem;">
          Tempi di risposta
        </div>
        @foreach([
          ['Segnalazioni errori', '24 ore'],
          ['Proposte articoli', '3-5 giorni'],
          ['Collaborazioni', '5-7 giorni'],
          ['Altro', '48 ore'],
        ] as [$tipo, $tempo])
        <div style="display:flex;justify-content:space-between;font-size:.78rem;
                    padding:.35rem 0;border-bottom:1px solid var(--border);">
          <span style="color:var(--ink-soft);">{{ $tipo }}</span>
          <span style="font-weight:600;color:var(--primary);">{{ $tempo }}</span>
        </div>
        @endforeach
      </div>

      <div style="background:var(--paper-warm);border-radius:12px;padding:1.25rem;">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;
                    letter-spacing:.1em;color:var(--ink-muted);margin-bottom:.85rem;">
          Altre opzioni
        </div>
        <a href="{{ route('rettifiche') }}"
           style="display:flex;align-items:center;gap:.5rem;font-size:.82rem;
                  color:var(--ink);text-decoration:none;padding:.4rem 0;">
          🔄 Richiedere una rettifica
        </a>
        <a href="{{ route('pubblicita') }}"
           style="display:flex;align-items:center;gap:.5rem;font-size:.82rem;
                  color:var(--ink);text-decoration:none;padding:.4rem 0;">
          📢 Pubblicità e sponsorizzazioni
        </a>
      </div>

    </div>
  </div>

</div>
@endsection