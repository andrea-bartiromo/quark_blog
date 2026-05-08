@extends('layouts.app')
@section('title', 'Turing e l’IA moderna — Quark')
@section('content')
<div class="container" style="padding-block:4rem;max-width:1000px;">
<p style="text-transform:uppercase;letter-spacing:.15em;color:#2563eb;font-size:.78rem;">Turing Experience</p>
<h1 style="font-size:clamp(2.5rem,6vw,5rem);font-weight:900;letter-spacing:-.05em;line-height:.95;">Le macchine possono pensare?</h1>
<p style="margin-top:1.5rem;font-size:1.08rem;line-height:1.9;color:#475569;max-width:850px;">
Nel 1950 Alan Turing pose una domanda destinata a cambiare il futuro della tecnologia. Oggi, nell’epoca dei modelli linguistici, delle reti neurali e dell’intelligenza artificiale generativa, quella domanda è più viva che mai.
</p>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1.5rem;margin-top:3rem;">
<div style="padding:1.6rem;border-radius:24px;background:#eff6ff;">
<h3>Test di Turing</h3>
<p>Il gioco dell’imitazione e la nascita del dibattito moderno sull’intelligenza artificiale.</p>
</div>
<div style="padding:1.6rem;border-radius:24px;background:#f8fafc;">
<h3>LLM e ChatGPT</h3>
<p>I modelli linguistici contemporanei come evoluzione della visione teorica di Turing.</p>
</div>
<div style="padding:1.6rem;border-radius:24px;background:#ecfeff;">
<h3>Etica e società</h3>
<p>Algoritmi, potere, lavoro, sorveglianza e il futuro della relazione uomo-macchina.</p>
</div>
</div>
</div>
@endsection