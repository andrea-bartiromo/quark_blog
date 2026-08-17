@extends('layouts.app')
@section('title', 'Link non valido — Kairus')

@section('content')
<div style="min-height:60vh;display:flex;align-items:center;justify-content:center;padding:2rem;">
  <div style="max-width:480px;width:100%;text-align:center;">

    <div style="font-size:3rem;margin-bottom:1rem;">🤔</div>
    <h1 style="font-family:'Fraunces',Georgia,serif;font-size:1.8rem;font-weight:900;color:#111827;margin-bottom:.75rem;">
      Link non valido
    </h1>
    <p style="color:#6b7280;line-height:1.65;margin-bottom:1.5rem;">
      Questo link di disiscrizione non è valido. Se vuoi essere rimosso dalle comunicazioni Kairus scrivici tramite il
      <a href="{{ route('contatti') }}" style="color:#0d9488;">form di contatto</a>.
    </p>

    <a href="{{ route('home') }}"
       style="display:inline-flex;align-items:center;gap:.4rem;background:#0d9488;color:#fff;padding:.65rem 1.5rem;border-radius:8px;text-decoration:none;font-weight:600;font-size:.9rem;">
      ← Torna a Kairus
    </a>

  </div>
</div>
@endsection
