@extends('layouts.app')
@section('title', 'Iscrizione confermata — '.config('laboratorio.name'))
@section('content')
<div class="container" style="padding-block:4rem;text-align:center;">
  <p style="font-size:3rem;margin-bottom:1rem;">✅</p>
  <h1 style="font-family:var(--font-display);font-size:1.8rem;margin-bottom:1rem;">Iscrizione confermata!</h1>
  <p style="color:var(--color-ink-muted);margin-bottom:2rem;">
    Benvenuto nella newsletter de Il Laboratorio. Riceverai aggiornamenti ogni settimana.
  </p>
  <a href="{{ route('home') }}" class="btn btn--primary">Vai alla homepage</a>
</div>
@endsection
