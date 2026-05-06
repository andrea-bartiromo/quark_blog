@extends('layouts.redazione')
@section('title', 'Dashboard')

@section('content')

<div class="admin-topbar">
  <div>
    <h1 class="admin-page-title">Benvenuto, {{ auth()->user()->name }} 👋</h1>
    <p style="font-size:.82rem;color:#6b7280;margin:0;">
      Area collaboratori di Quark — scrivi articoli e monitorane le performance.
    </p>
  </div>
  <a href="{{ route('redazione.articles.create') }}" class="btn btn--primary">
    ✍️ Scrivi articolo
  </a>
</div>

{{-- Statistiche --}}
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem;margin-bottom:1.5rem;">
  @foreach([
    ['📝', 'Articoli totali', $stats['total'], '#111827'],
    ['✅', 'Pubblicati', $stats['published'], '#065f46'],
    ['⏳', 'In revisione', $stats['review'], '#854d0e'],
    ['📄', 'Bozze', $stats['draft'], '#6b7280'],
    ['👁', 'Visualizzazioni', number_format($stats['views'], 0, ',', '.'), '#1e40af'],
    ['💬', 'Commenti', $stats['comments'], '#5b21b6'],
  ] as [$icon, $label, $value, $color])
  <div style="background:#fff;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,.08);
              padding:1.1rem;text-align:center;">
    <div style="font-size:1.4rem;margin-bottom:.25rem;">{{ $icon }}</div>
    <div style="font-size:1.5rem;font-weight:900;color:{{ $color }};">{{ $value }}</div>
    <div style="font-size:.7rem;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;font-weight:600;">{{ $label }}</div>
  </div>
  @endforeach
</div>

{{-- Info revisione --}}
@if($stats['review'] > 0)
<div style="background:#fef9c3;border:1px solid #fde68a;border-radius:10px;
            padding:.85rem 1.1rem;margin-bottom:1.5rem;font-size:.85rem;color:#854d0e;">
  ⏳ <strong>{{ $stats['review'] }} {{ $stats['review'] === 1 ? 'articolo è' : 'articoli sono' }} in attesa di revisione.</strong>
  L'editor li esaminerà e riceverai una email con l'esito.
</div>
@endif

{{-- Ultimi articoli --}}
<div style="background:#fff;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,.08);overflow:hidden;">
  <div style="padding:.85rem 1.1rem;border-bottom:1px solid #e5e7eb;
              display:flex;justify-content:space-between;align-items:center;">
    <span style="font-size:.7rem;font-weight:700;text-transform:uppercase;
                 letter-spacing:.1em;color:#6b7280;">Ultimi articoli</span>
    <a href="{{ route('redazione.articles') }}" style="font-size:.75rem;color:#0d9488;">Vedi tutti →</a>
  </div>

  @forelse($myArticles as $article)
  <div style="display:flex;align-items:center;gap:1rem;padding:.85rem 1.1rem;
              border-bottom:1px solid #f3f4f6;">
    <div style="flex:1;min-width:0;">
      <div style="font-size:.85rem;font-weight:600;color:#111827;
                  overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
        {{ $article->title }}
      </div>
      <div style="font-size:.72rem;color:#6b7280;margin-top:.15rem;">
        {{ config('laboratorio.categories.'.$article->category) }}
        · {{ $article->updated_at->diffForHumans() }}
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:.75rem;flex-shrink:0;">
      <span class="status status--{{ $article->status }}">
        @if($article->status === 'published') Pubblicato
        @elseif($article->status === 'review') In revisione
        @else Bozza @endif
      </span>
      @if($article->status !== 'published')
      <a href="{{ route('redazione.articles.edit', $article) }}"
         class="btn btn--secondary btn--sm">Modifica</a>
      @else
      <a href="{{ route('articolo', $article->slug) }}" target="_blank"
         class="btn btn--secondary btn--sm">Leggi</a>
      @endif
    </div>
  </div>
  @empty
  <div style="padding:2rem;text-align:center;color:#6b7280;">
    <p style="font-size:1.2rem;margin-bottom:.5rem;">✍️</p>
    <p>Non hai ancora scritto nessun articolo.</p>
    <a href="{{ route('redazione.articles.create') }}" class="btn btn--primary" style="margin-top:.75rem;">
      Scrivi il primo articolo
    </a>
  </div>
  @endforelse
</div>

{{-- Guida rapida --}}
<div style="background:#f0fdfa;border:1px solid #99f6e4;border-radius:10px;
            padding:1.25rem;margin-top:1.5rem;">
  <div style="font-size:.82rem;font-weight:700;color:#0f766e;margin-bottom:.75rem;">
    💡 Come funziona il processo di pubblicazione
  </div>
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem;">
    @foreach([
      ['1️⃣', 'Scrivi', 'Crea il tuo articolo con l\'editor. Puoi salvarlo come bozza o inviarlo subito.'],
      ['2️⃣', 'Revisione', 'L\'editor di Quark controlla il contenuto, le fonti e la qualità.'],
      ['3️⃣', 'Pubblicazione', 'Se approvato, ricevi una email e l\'articolo va online. Se richiede modifiche, ti viene rimandato in bozza con note.'],
    ] as [$num, $title, $desc])
    <div style="background:#fff;border-radius:8px;padding:.85rem;border:1px solid #99f6e4;">
      <div style="font-size:1.1rem;margin-bottom:.3rem;">{{ $num }} <strong style="color:#0f766e;">{{ $title }}</strong></div>
      <div style="font-size:.75rem;color:#374151;line-height:1.5;">{{ $desc }}</div>
    </div>
    @endforeach
  </div>
</div>

@endsection