@extends('layouts.app')
@section('title', 'Disiscrizione effettuata — Kairus')

@section('content')
<div style="min-height:60vh;display:flex;align-items:center;justify-content:center;padding:2rem;">
  <div style="max-width:480px;width:100%;text-align:center;">

    <div style="width:72px;height:72px;background:#f3f4f6;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2rem;margin:0 auto 1.5rem;">
      ✅
    </div>
    <h1 style="font-family:'Fraunces',Georgia,serif;font-size:1.8rem;font-weight:900;color:#111827;margin-bottom:.75rem;">
      Disiscrizione effettuata
    </h1>
    <p style="color:#6b7280;line-height:1.65;margin-bottom:2rem;">
      Non riceverai più email da Kairus.
    </p>

    <a href="{{ route('home') }}"
       style="display:inline-flex;align-items:center;gap:.4rem;background:#0d9488;color:#fff;padding:.65rem 1.5rem;border-radius:8px;text-decoration:none;font-weight:600;font-size:.9rem;">
      ← Torna a Kairus
    </a>

  </div>
</div>
@endsection
