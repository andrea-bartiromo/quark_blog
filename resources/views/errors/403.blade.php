@extends('layouts.app')
@section('title', 'Accesso negato — '.config('laboratorio.name'))
@section('content')
<div class="container" style="padding-block:4rem;text-align:center;">
  <p style="font-family:var(--font-display);font-size:5rem;font-weight:900;color:var(--color-accent);line-height:1;">403</p>
  <h1 style="font-family:var(--font-display);font-size:1.8rem;margin-bottom:1rem;">Accesso negato</h1>
  <p style="color:var(--color-ink-muted);margin-bottom:2rem;">Non hai i permessi per accedere a questa pagina.</p>
  <a href="{{ route('home') }}" class="btn btn--primary">Torna alla homepage</a>
</div>
@endsection
