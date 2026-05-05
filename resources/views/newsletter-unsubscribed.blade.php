@extends('layouts.app')
@section('title', 'Disiscrizione effettuata — Quark')

@section('content')
<div style="min-height:60vh;display:flex;align-items:center;justify-content:center;padding:2rem;">
  <div style="max-width:480px;width:100%;text-align:center;">

    @if(isset($notFound) && $notFound)
      <div style="font-size:3rem;margin-bottom:1rem;">🤔</div>
      <h1 style="font-family:'Fraunces',Georgia,serif;font-size:1.8rem;font-weight:900;
                 color:#111827;margin-bottom:.75rem;">
        Link non valido
      </h1>
      <p style="color:#6b7280;line-height:1.65;margin-bottom:1.5rem;">
        Questo link di disiscrizione non è valido o è già stato utilizzato.
        Se vuoi essere rimosso dalla newsletter scrivici tramite il
        <a href="{{ route('contatti') }}" style="color:#0d9488;">form di contatto</a>.
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
        Sei stato rimosso dalla newsletter di Quark. Non riceverai più email da noi.
      </p>
      <p style="color:#6b7280;font-size:.875rem;line-height:1.65;margin-bottom:2rem;">
        Se ti sei disiscritto per errore o vuoi tornare,
        puoi reiscriverti in qualsiasi momento dal sito.
      </p>
    @endif

    <a href="{{ route('home') }}"
       style="display:inline-flex;align-items:center;gap:.4rem;
              background:#0d9488;color:#fff;padding:.65rem 1.5rem;
              border-radius:8px;text-decoration:none;font-weight:600;font-size:.9rem;">
      ← Torna a Quark
    </a>

  </div>
</div>
@endsection