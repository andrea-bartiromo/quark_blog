@extends('layouts.app')
@section('title', 'Disiscrizione effettuata — '.config('laboratorio.name'))

@section('content')
<div style="min-height:60vh;display:flex;align-items:center;justify-content:center;padding:2rem;">
  <div style="max-width:480px;width:100%;text-align:center;">

    @if($notFound)
      <div style="font-size:3rem;margin-bottom:1rem;">🤔</div>
      <h1 style="font-family:'Fraunces',Georgia,serif;font-size:1.8rem;font-weight:900;
                 color:#111827;margin-bottom:.75rem;">
        Link non valido
      </h1>
      <p style="color:#6b7280;line-height:1.65;margin-bottom:1.5rem;">
        Questo link di disiscrizione non è valido o è già stato utilizzato.
      </p>
    @else
      <div style="width:72px;height:72px;background:#f3f4f6;border-radius:50%;
                  display:flex;align-items:center;justify-content:center;
                  font-size:2rem;margin:0 auto 1.5rem;">
        👋
      </div>
      <h1 style="font-family:'Fraunces',Georgia,serif;font-size:1.8rem;font-weight:900;
                 color:#111827;margin-bottom:.75rem;">
        Disiscrizione effettuata
      </h1>
      <p style="color:#6b7280;line-height:1.65;margin-bottom:.75rem;">
        Non riceverai più aggiornamenti per il Percorso
        @if($cluster)“<strong>{{ $cluster->name }}</strong>”@else selezionato @endif.
      </p>
      <p style="color:#6b7280;font-size:.875rem;line-height:1.65;margin-bottom:2rem;">
        Resti iscritto agli altri Percorsi che segui e alla newsletter, se attiva:
        questa disiscrizione riguarda solo questo Percorso.
      </p>
    @endif

    <a href="{{ route('percorsi.index') }}"
       style="display:inline-flex;align-items:center;gap:.4rem;
              background:#0d9488;color:#fff;padding:.65rem 1.5rem;
              border-radius:8px;text-decoration:none;font-weight:600;font-size:.9rem;">
      ← Torna ai Percorsi
    </a>

  </div>
</div>
@endsection
