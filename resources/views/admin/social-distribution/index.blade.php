@extends('layouts.admin')
@section('title','Distribuzione social')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Distribuzione social</h1>
</div>

<div style="background:#f0fdfa;border:1px solid #99f6e4;border-radius:8px;
            padding:.85rem 1.1rem;margin-bottom:1.25rem;font-size:.82rem;color:#0f766e;">
  ℹ️ Genera un link con parametri UTM per un post <strong>ufficiale</strong>
  pubblicato dall'account Facebook di Kairus. I pulsanti di condivisione
  che i lettori vedono sulla pagina articolo (X, WhatsApp, LinkedIn, Copia
  link) restano volontariamente senza UTM: non sono campagne ufficiali e
  non devono mai essere confusi con una. Nessun dato viene salvato — questo
  è solo un generatore, non pubblica nulla automaticamente.
</div>

@if($error)
<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;
            padding:.75rem 1rem;margin-bottom:1rem;color:#991b1b;font-size:.85rem;">
  {{ $error }}
</div>
@endif

<div style="max-width:640px;">
  <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1.5rem;margin-bottom:1.5rem;">
    <form method="GET" action="{{ route('admin.social-distribution') }}">
      <div class="form-group">
        <label class="form-label" for="slug">Slug articolo *</label>
        <input type="text" id="slug" name="slug" value="{{ $slug }}" required class="form-input"
               placeholder="es. onde-gravitazionali-osservate-di-nuovo">
        <small style="font-size:.72rem;color:var(--admin-muted);">Lo slug visibile nell'URL pubblico dell'articolo (solo articoli pubblicati).</small>
      </div>

      <div class="form-group">
        <label class="form-label" for="channel">Canale</label>
        <select id="channel" class="form-select" disabled>
          @foreach($channelOptions as $value => $label)
            <option value="{{ $value }}" selected>{{ $label }}</option>
          @endforeach
        </select>
        <small style="font-size:.72rem;color:var(--admin-muted);">Solo Facebook in questa versione — architettura pensata per aggiungere altri canali in futuro.</small>
      </div>

      <div class="form-group">
        <label class="form-label" for="campaign">Nome campagna (opzionale)</label>
        <input type="text" id="campaign" name="campaign" value="{{ $campaignInput }}" class="form-input"
               placeholder="es. lancio-fisica-2026">
        <small style="font-size:.72rem;color:var(--admin-muted);">Solo lettere minuscole, cifre e trattini. Se vuoto, viene generato automaticamente da slug + data.</small>
      </div>

      <button type="submit" class="btn btn--primary">Genera link</button>
    </form>
  </div>

  @if($link)
  <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1.5rem;">
    <h3 style="margin-top:0;">Link per {{ $article->title }}</h3>
    <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
      <input type="text" readonly value="{{ $link }}" class="form-input" style="flex:1;min-width:280px;" onclick="this.select()">
      <button type="button" class="btn btn--secondary" onclick="navigator.clipboard.writeText('{{ $link }}')">Copia</button>
    </div>
  </div>
  @endif
</div>

@endsection
