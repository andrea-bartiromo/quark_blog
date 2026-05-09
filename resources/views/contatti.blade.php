@extends('layouts.app')
@section('title', 'Contatti — Quark')
@section('description', 'Contatta la redazione di Quark per segnalazioni, proposte di collaborazione o domande.')

@section('content')
<div class="public-page public-page--contacts">
  <div class="container premium-static premium-static--wide">

    <section class="public-hero public-hero--light public-hero--compact">
      <span class="public-hero__kicker">Scrivici</span>
      <h1>Contatti</h1>
      <p>Hai trovato un errore? Vuoi proporre un argomento? Hai domande sulla redazione? Scrivici: leggiamo tutto.</p>
    </section>

    @if(request('sent') === '1' || session('contact_sent'))
      <div class="premium-alert premium-alert--success">
        ✅ Messaggio inviato! Ti risponderemo entro 48 ore.
      </div>
    @endif

    @if($errors->any())
      <div class="premium-alert premium-alert--error">
        <strong>Controlla il form:</strong>
        <ul>
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="premium-contact-layout">
      <section class="premium-form-card">
        <form method="POST" action="{{ route('contatti.send') }}" class="premium-form-grid" novalidate>
          @csrf

          {{-- Honeypot antispam: deve restare vuoto --}}
          <input type="text" name="website" value="" class="sr-only" tabindex="-1" autocomplete="off" aria-hidden="true">

          <div class="premium-field">
            <label for="contact-name">Nome *</label>
            <input id="contact-name" type="text" name="nome" value="{{ old('nome') }}" required maxlength="100" placeholder="Il tuo nome">
            @error('nome')<div class="premium-error">{{ $message }}</div>@enderror
          </div>

          <div class="premium-field">
            <label for="contact-email">Email *</label>
            <input id="contact-email" type="email" name="email" value="{{ old('email') }}" required maxlength="150" placeholder="La tua email">
            @error('email')<div class="premium-error">{{ $message }}</div>@enderror
          </div>

          <div class="premium-field">
            <label for="contact-subject">Oggetto *</label>
            <select id="contact-subject" name="oggetto" required>
              <option value="">Seleziona...</option>
              @foreach([
                'Segnalazione errore',
                'Proposta articolo',
                'Collaborazione',
                'Domanda editoriale',
                'Richiesta rettifica',
                'Altro',
              ] as $opt)
                <option value="{{ $opt }}" {{ old('oggetto') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
              @endforeach
            </select>
            @error('oggetto')<div class="premium-error">{{ $message }}</div>@enderror
          </div>

          <div class="premium-field">
            <label for="contact-message">Messaggio * <span>(minimo 20 caratteri)</span></label>
            <textarea id="contact-message" name="messaggio" required maxlength="2000" placeholder="Scrivi qui il tuo messaggio...">{{ old('messaggio') }}</textarea>
            @error('messaggio')<div class="premium-error">{{ $message }}</div>@enderror
          </div>

          <label class="premium-checkbox" for="contact-privacy">
            <input id="contact-privacy" type="checkbox" name="privacy" value="1" {{ old('privacy') ? 'checked' : '' }}>
            <span>Ho letto e accetto la <a href="{{ route('privacy') }}">Privacy Policy</a></span>
          </label>
          @error('privacy')<div class="premium-error">{{ $message }}</div>@enderror

          <button type="submit" class="premium-submit">Invia messaggio</button>
        </form>
      </section>

      <aside class="premium-side-list">
        <section class="premium-widget">
          <span class="premium-widget__kicker">Risposte</span>
          <h3>Tempi medi</h3>
          <div class="premium-info-list">
            @foreach([
              ['Segnalazioni errori', '24 ore'],
              ['Proposte articoli', '3-5 giorni'],
              ['Collaborazioni', '5-7 giorni'],
              ['Altro', '48 ore'],
            ] as [$tipo, $tempo])
              <div class="premium-info-row">
                <span>{{ $tipo }}</span>
                <span>{{ $tempo }}</span>
              </div>
            @endforeach
          </div>
        </section>

        <section class="premium-widget">
          <span class="premium-widget__kicker">Link utili</span>
          <h3>Altre opzioni</h3>
          <div class="premium-link-list">
            <a href="{{ route('rettifiche') }}">🔄 Richiedere una rettifica</a>
            <a href="{{ route('pubblicita') }}">📢 Pubblicità e sponsorizzazioni</a>
          </div>
        </section>
      </aside>
    </div>

  </div>
</div>
@endsection
