@extends('layouts.app')
@section('title', 'Pagina non trovata — '.config('laboratorio.name'))
@section('content')
<div class="container" style="padding-block:4rem;text-align:center;">
  <p style="font-family:var(--font-display);font-size:5rem;font-weight:900;color:var(--color-accent);line-height:1;">404</p>
  <h1 style="font-family:var(--font-display);font-size:1.8rem;margin-bottom:1rem;">Pagina non trovata</h1>
  <p style="color:var(--color-ink-muted);margin-bottom:2rem;">La pagina che cerchi non esiste o è stata spostata.</p>
  <a href="{{ route('home') }}" class="btn btn--primary">Torna alla homepage</a>
</div>
@endsection
