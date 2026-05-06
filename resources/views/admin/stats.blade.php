@extends('layouts.admin')
@section('title', 'Statistiche')

@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Statistiche</h1>
  <span style="font-size:.78rem;color:#6b7280;">
    Dati aggiornati in tempo reale
  </span>
</div>

{{-- Top articoli per views --}}
<div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);
            padding:1.25rem;margin-bottom:1.5rem;">
  <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;
              letter-spacing:.1em;color:#6b7280;margin-bottom:1rem;">
    🏆 Top articoli per visualizzazioni
  </div>
  <table class="admin-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Titolo</th>
        <th>Categoria</th>
        <th>Views</th>
        <th>Lettura</th>
        <th>Pubblicato</th>
      </tr>
    </thead>
    <tbody>
      @foreach($articles->take(10) as $i => $art)
      <tr>
        <td style="font-weight:900;color:#e5e7eb;font-size:1.1rem;">{{ $i+1 }}</td>
        <td>
          <a href="{{ route('articolo', $art->slug) }}" target="_blank"
             style="font-weight:600;color:#111827;text-decoration:none;font-size:.82rem;">
            {{ Str::limit($art->title, 55) }}
          </a>
        </td>
        <td><span class="badge badge--{{ $art->category }}">{{ config('laboratorio.categories.'.$art->category) }}</span></td>
        <td style="font-weight:700;color:#0d9488;">{{ number_format($art->views, 0, ',', '.') }}</td>
        <td style="font-size:.78rem;color:#6b7280;">{{ $art->read_minutes }} min</td>
        <td style="font-size:.78rem;color:#6b7280;">{{ $art->published_at->format('d/m/Y') }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>

{{-- Due colonne: per categoria + più commentati --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">

  {{-- Per categoria --}}
  <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1.25rem;">
    <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;
                letter-spacing:.1em;color:#6b7280;margin-bottom:1rem;">
      📊 Views per categoria
    </div>
    @php $maxViews = collect($byCategory)->max('total_views') ?: 1; @endphp
    @foreach($byCategory as $slug => $data)
    <div style="margin-bottom:.85rem;">
      <div style="display:flex;justify-content:space-between;margin-bottom:.3rem;">
        <span class="badge badge--{{ $slug }}">{{ $data['label'] }}</span>
        <span style="font-size:.78rem;font-weight:600;color:#0d9488;">
          {{ number_format($data['total_views'], 0, ',', '.') }} views
        </span>
      </div>
      <div style="background:#f3f4f6;border-radius:4px;height:6px;">
        <div style="background:#0d9488;height:100%;border-radius:4px;
                    width:{{ round(($data['total_views']/$maxViews)*100) }}%;
                    transition:width .5s;"></div>
      </div>
    </div>
    @endforeach
  </div>

  {{-- Più commentati --}}
  <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1.25rem;">
    <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;
                letter-spacing:.1em;color:#6b7280;margin-bottom:1rem;">
      💬 Più commentati
    </div>
    @forelse($topCommented as $i => $art)
    <div style="display:flex;align-items:center;gap:.75rem;padding:.5rem 0;
                border-bottom:1px solid #f3f4f6;">
      <span style="font-size:1.1rem;font-weight:900;color:#e5e7eb;width:1.5rem;text-align:center;">
        {{ $i+1 }}
      </span>
      <div style="flex:1;min-width:0;">
        <a href="{{ route('admin.articles.edit', $art) }}"
           style="font-size:.82rem;font-weight:600;color:#111827;text-decoration:none;
                  overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;">
          {{ Str::limit($art->title, 45) }}
        </a>
      </div>
      <span style="font-size:.85rem;font-weight:700;color:#6b7280;flex-shrink:0;">
        {{ $art->comments_count }} 💬
      </span>
    </div>
    @empty
    <p style="font-size:.82rem;color:#6b7280;text-align:center;padding:1rem 0;">
      Nessun commento ancora.
    </p>
    @endforelse
  </div>
</div>

{{-- Newsletter growth --}}
<div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);
            padding:1.25rem;margin-bottom:1.5rem;">
  <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;
              letter-spacing:.1em;color:#6b7280;margin-bottom:1rem;">
    📧 Crescita iscritti newsletter
  </div>
  @if($newsletterGrowth->isEmpty())
  <p style="font-size:.82rem;color:#6b7280;text-align:center;padding:1rem 0;">
    Nessun iscritto ancora.
  </p>
  @else
  <div style="display:flex;gap:1rem;flex-wrap:wrap;">
    @foreach($newsletterGrowth as $m)
    <div style="background:#f0fdfa;border-radius:8px;padding:.75rem 1rem;text-align:center;min-width:80px;">
      <div style="font-size:1.4rem;font-weight:900;color:#0d9488;">{{ $m->count }}</div>
      <div style="font-size:.65rem;color:#6b7280;margin-top:.2rem;">{{ $m->month }}</div>
    </div>
    @endforeach
  </div>
  @endif
</div>

{{-- Articoli pubblicati per mese --}}
<div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1.25rem;">
  <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;
              letter-spacing:.1em;color:#6b7280;margin-bottom:1rem;">
    📝 Articoli pubblicati per mese
  </div>
  @php $maxArt = $articlesByMonth->max('count') ?: 1; @endphp
  <div style="display:flex;gap:.5rem;align-items:flex-end;height:80px;flex-wrap:wrap;">
    @foreach($articlesByMonth as $m)
    <div style="display:flex;flex-direction:column;align-items:center;gap:.25rem;flex:1;min-width:40px;">
      <span style="font-size:.65rem;font-weight:700;color:#0d9488;">{{ $m->count }}</span>
      <div style="background:#0d9488;border-radius:4px 4px 0 0;width:100%;
                  height:{{ round(($m->count/$maxArt)*60) }}px;min-height:4px;"></div>
      <span style="font-size:.55rem;color:#9ca3af;transform:rotate(-45deg);white-space:nowrap;">
        {{ substr($m->month, 5) }}/{{ substr($m->month, 2, 2) }}
      </span>
    </div>
    @endforeach
  </div>
</div>

@endsection