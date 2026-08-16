@extends('layouts.app')
@section('title', 'Disiscrizione — Kairus')

@section('content')
<div style="min-height:60vh;display:flex;align-items:center;justify-content:center;padding:2rem;">
  <div style="max-width:480px;width:100%;text-align:center;">

    @if($alreadyUnsubscribed)
      <div style="font-size:3rem;margin-bottom:1rem;">✅</div>
      <h1 style="font-family:'Fraunces',Georgia,serif;font-size:1.8rem;font-weight:900;color:#111827;margin-bottom:.75rem;">
        Sei già disiscritto
      </h1>
      <p style="color:#6b7280;line-height:1.65;margin-bottom:1.5rem;">
        Non riceverai più email da Kairus. Non serve fare altro.
      </p>
    @else
      <div style="font-size:3rem;margin-bottom:1rem;">👋</div>
      <h1 style="font-family:'Fraunces',Georgia,serif;font-size:1.8rem;font-weight:900;color:#111827;margin-bottom:.75rem;">
        Vuoi disiscriverti?
      </h1>
      <p style="color:#6b7280;line-height:1.65;margin-bottom:1.5rem;">
        Stai per disiscrivere <strong>{{ $subscriber->email }}</strong> dalle comunicazioni di Kairus.
        Questa pagina non ha ancora fatto nulla: conferma qui sotto per procedere.
      </p>

      <form method="POST" action="{{ route('comunicazione.disiscrizione.submit', $subscriber->unsubscribe_token) }}">
        @csrf
        <button type="submit"
                style="display:inline-flex;align-items:center;gap:.4rem;background:#dc2626;color:#fff;
                       padding:.65rem 1.5rem;border-radius:8px;border:0;font-weight:600;font-size:.9rem;
                       cursor:pointer;">
          Conferma disiscrizione
        </button>
      </form>
    @endif

    <div style="margin-top:1.5rem;">
      <a href="{{ route('home') }}"
         style="display:inline-flex;align-items:center;gap:.4rem;color:#6b7280;text-decoration:none;font-size:.85rem;">
        ← Torna a Kairus senza disiscriverti
      </a>
    </div>

  </div>
</div>
@endsection
