@extends('layouts.app')
@section('title', 'Errore interno — '.config('laboratorio.name'))
@section('content')
<div class="container" style="padding-block:4rem;text-align:center;">
  <p style="font-family:var(--font-display);font-size:5rem;font-weight:900;color:var(--color-accent);line-height:1;">500</p>
  <h1 style="font-family:var(--font-display);font-size:1.8rem;margin-bottom:1rem;">Errore interno del server</h1>
  <p style="color:var(--color-ink-muted);margin-bottom:2rem;">Si è verificato un errore tecnico. Stiamo lavorando per risolverlo.</p>
  <a href="{{ route('home') }}" class="btn btn--primary">Torna alla homepage</a>
</div>
@endsection
