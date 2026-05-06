@extends('layouts.admin')
@section('title', 'Anteprima newsletter')

@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Newsletter</h1>

  <div style="display:flex;gap:.5rem;">
    <a href="{{ route('admin.newsletter') }}" class="btn btn--secondary" style="font-size:.78rem;">
      ← Iscritti
    </a>

    <form method="POST"
          action="{{ route('admin.newsletter.send-now') }}"
          onsubmit="return confirm('Inviare la newsletter ora a tutti gli iscritti confermati?')">
      @csrf
      <button type="submit" class="btn btn--primary" style="font-size:.78rem;">
        📧 Invia ora
      </button>
    </form>
  </div>
</div>

{{-- Info articoli --}}
<div style="background:#f0fdfa;border:1px solid #99f6e4;border-radius:8px;
            padding:.85rem 1.1rem;margin-bottom:1.5rem;font-size:.82rem;color:#0f766e;">
  <strong>Articoli selezionati automaticamente:</strong>
  3 più letti degli ultimi 7 giorni + 2 ultimi pubblicati.
</div>

{{-- Dashboard newsletter --}}
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem;">

  <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:1rem;">
    <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:#6b7280;margin-bottom:.35rem;">
      Articoli inclusi
    </div>
    <div style="font-size:1.8rem;font-weight:900;color:#111827;">
      {{ $articles->count() }}
    </div>
  </div>

  <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:1rem;">
    <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:#6b7280;margin-bottom:.35rem;">
      Selezione
    </div>
    <div style="font-size:.9rem;font-weight:700;color:#111827;">
      3 più letti + 2 recenti
    </div>
  </div>

  <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:1rem;">
    <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:#6b7280;margin-bottom:.35rem;">
      Invio automatico
    </div>
    <div style="font-size:.9rem;font-weight:700;color:#0d9488;">
      Giovedì · 09:00
    </div>
  </div>

</div>

{{-- Oggetto email --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:1rem;margin-bottom:1.5rem;">
  <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:#6b7280;margin-bottom:.4rem;">
    Oggetto email
  </div>
  <div style="font-size:1rem;font-weight:700;color:#111827;">
    🧪 I migliori articoli di Quark — settimana {{ now()->weekOfYear }}/{{ now()->year }}
  </div>
</div>

{{-- Checklist --}}
<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:1rem;margin-bottom:1.5rem;color:#92400e;">
  <strong style="display:block;margin-bottom:.5rem;">Checklist prima dell’invio</strong>
  <ul style="margin-left:1.2rem;line-height:1.7;font-size:.85rem;">
    <li>Verifica titoli e sommari</li>
    <li>Controlla immagini e cover</li>
    <li>Assicurati che gli articoli siano pubblicati</li>
    <li>Invia solo agli iscritti confermati</li>
  </ul>
</div>

{{-- Anteprima email --}}
<div style="max-width:620px;margin:0 auto;background:#fff;border-radius:12px;
            box-shadow:0 4px 24px rgba(0,0,0,.1);overflow:hidden;">

  {{-- Header --}}
  <div style="background:linear-gradient(135deg,#0d9488,#0f766e);padding:2rem;text-align:center;">
    <div style="font-size:1.8rem;font-weight:900;color:#fff;margin-bottom:.25rem;">Quark.</div>
    <div style="color:rgba(255,255,255,.8);font-size:.82rem;">La scienza spiegata come si deve</div>
  </div>

  <div style="padding:1.5rem;">
    <h2 style="font-size:1.1rem;color:#111827;margin-bottom:.5rem;font-weight:700;">
      🧪 I migliori articoli — settimana {{ now()->weekOfYear }}/{{ now()->year }}
    </h2>

    <p style="font-size:.875rem;color:#6b7280;font-style:italic;border-left:3px solid #0d9488;
              padding-left:1rem;margin-bottom:1.5rem;line-height:1.7;">
      [Intro generata automaticamente al momento dell'invio]
    </p>

    {{-- Articoli --}}
    @foreach($articles as $i => $art)
    <div style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;margin-bottom:1rem;">

      <div style="background:#f5f5f4;height:120px;display:flex;align-items:center;justify-content:center;">
        @if($art->cover_image)
          <img src="{{ asset('assets/img/'.$art->cover_image) }}"
               style="width:100%;height:100%;object-fit:cover;">
        @else
          <span style="font-size:2rem;opacity:.3;">🖼</span>
        @endif
      </div>

      <div style="padding:1rem 1.25rem;">

        <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.4rem;">
          <span style="font-size:.65rem;font-weight:700;
                       color:{{ $i < 3 ? '#f97316' : '#0d9488' }};
                       text-transform:uppercase;">
            {{ $i < 3 ? '🔥 Più letto' : '🆕 Nuovo' }}
          </span>
        </div>

        <div style="font-size:.95rem;font-weight:700;color:#111827;margin-bottom:.4rem;">
          {{ $art->title }}
        </div>

        <div style="font-size:.82rem;color:#6b7280;margin-bottom:.85rem;">
          {{ Str::limit($art->excerpt, 140) }}
        </div>
        
<a href="{{ route('articolo', $art->slug) }}" target="_blank"
   style="display:inline-block;background:#0d9488;color:#fff;
          padding:.4rem 1rem;border-radius:6px;font-size:.8rem;
          text-decoration:none;font-weight:600;">
  Leggi →
</a>

      </div>
    </div>
    @endforeach

    {{-- CTA --}}
    <div style="text-align:center;margin:1.5rem 0;padding:1.5rem;
                background:#f0fdfa;border-radius:10px;">
      <p style="font-size:.875rem;color:#0f766e;margin-bottom:.75rem;font-weight:600;">
        Vuoi leggere altri articoli?
      </p>
      <span style="background:#0d9488;color:#fff;padding:.6rem 1.5rem;border-radius:8px;font-weight:700;">
        Vai su Quark →
      </span>
    </div>

    {{-- Footer --}}
    <div style="border-top:1px solid #e5e7eb;padding-top:1rem;text-align:center;">
      <p style="color:#9ca3af;font-size:.72rem;">
        Hai ricevuto questa email perché sei iscritto a Quark.<br>
        Disiscriviti quando vuoi.
      </p>
    </div>

  </div>
</div>

@endsection