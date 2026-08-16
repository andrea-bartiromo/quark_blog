@extends('layouts.app')
@section('title', 'Iscrizione confermata — '.config('laboratorio.name'))

@section('content')
<div style="min-height:60vh;display:flex;align-items:center;justify-content:center;padding:2rem;">
  <div style="max-width:480px;width:100%;text-align:center;">
    <div style="width:72px;height:72px;background:#f0fdfa;border-radius:50%;
                display:flex;align-items:center;justify-content:center;
                font-size:2rem;margin:0 auto 1.5rem;">
      🧭
    </div>
    <h1 style="font-family:'Fraunces',Georgia,serif;font-size:1.8rem;font-weight:900;
               color:#111827;margin-bottom:.75rem;">
      Email confermata
    </h1>

    @if($clusters->isNotEmpty())
      <p style="color:#6b7280;line-height:1.65;margin-bottom:.75rem;">
        Ti avviseremo quando {{ $clusters->count() === 1 ? 'questo Percorso continua' : 'questi Percorsi continuano' }}:
      </p>
      <ul style="list-style:none;padding:0;margin:0 0 1.5rem;color:#111827;font-weight:600;">
        @foreach($clusters as $cluster)
          <li style="padding:.35rem 0;">{{ $cluster->name }}</li>
        @endforeach
      </ul>
    @else
      <p style="color:#6b7280;line-height:1.65;margin-bottom:2rem;">
        La tua email è confermata. Riceverai un avviso quando un Percorso che segui continua.
      </p>
    @endif

    <a href="{{ route('percorsi.index') }}"
       style="display:inline-flex;align-items:center;gap:.4rem;
              background:#0d9488;color:#fff;padding:.65rem 1.5rem;
              border-radius:8px;text-decoration:none;font-weight:600;font-size:.9rem;">
      Scopri tutti i Percorsi →
    </a>
  </div>
</div>
@endsection
