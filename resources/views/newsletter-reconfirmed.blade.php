@extends('layouts.app')
@section('title', ($confirmed ?? false) ? 'Iscrizione confermata — '.config('laboratorio.name') : 'Link non valido — '.config('laboratorio.name'))

@section('content')
<div style="min-height:60vh;display:flex;align-items:center;justify-content:center;padding:2rem;">
  <div style="max-width:480px;width:100%;text-align:center;">

    @if($confirmed ?? false)
      <p style="font-size:3rem;margin-bottom:1rem;">✅</p>
      <h1 style="font-family:var(--font-display);font-size:1.8rem;margin-bottom:1rem;">Iscrizione confermata!</h1>
      <p style="color:var(--color-ink-muted);margin-bottom:2rem;">
        Benvenuto nella newsletter di "Kairus". Riceverai aggiornamenti ogni settimana.
      </p>
    @else
      <div style="font-size:3rem;margin-bottom:1rem;">🤔</div>
      <h1 style="font-family:'Fraunces',Georgia,serif;font-size:1.8rem;font-weight:900;
                 color:#111827;margin-bottom:.75rem;">
        Link non valido o scaduto
      </h1>
      <p style="color:#6b7280;line-height:1.65;margin-bottom:1.5rem;">
        Questo link di riconferma non è più valido — potrebbe essere già stato usato,
        sostituito da un invio più recente, o semplicemente scaduto.
        Se vuoi ricevere Kairus puoi iscriverti di nuovo dalla homepage.
      </p>
    @endif

    <a href="{{ route('home') }}" class="btn btn--primary">Vai alla homepage</a>
  </div>
</div>
@endsection
