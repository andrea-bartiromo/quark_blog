@extends('layouts.admin')
@section('title', 'Dashboard')

@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Dashboard</h1>
  <span class="text-muted" style="font-size:.78rem;">
    {{ now()->locale('it')->isoFormat('dddd D MMMM YYYY') }}
  </span>
</div>

@if($stats['unverified'] > 0)
<div class="alert-warning" style="background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;padding:.85rem 1.1rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:.75rem;">
  <span>⚠️</span>
  <div style="flex:1;font-size:.82rem;">
    <strong style="color:#dc2626;">{{ $stats['unverified'] }} articoli pubblicati</strong>
    <span style="color:#7f1d1d;"> non ancora verificati sulla fonte primaria.</span>
  </div>
  <a href="{{ route('admin.verification') }}" style="background:#dc2626;color:#fff;padding:.35rem .85rem;border-radius:4px;font-size:.75rem;font-weight:700;text-decoration:none;white-space:nowrap;">
    Verifica ora
  </a>
</div>
@endif

{{-- Griglia contatori --}}
<div class="dash-grid-5" style="display:grid;grid-template-columns:repeat(5,1fr);gap:1rem;margin-bottom:1.5rem;">
  @foreach([
    ['label'=>'Pubblicati', 'value'=>$stats['published'], 'icon'=>'📝', 'color'=>'#0d9488', 'href'=>route('admin.articles')],
    ['label'=>'Bozze', 'value'=>$stats['drafts'], 'icon'=>'📄', 'color'=>'#6b7280', 'href'=>route('admin.articles')],
    ['label'=>'Newsletter', 'value'=>$stats['newsletter'], 'icon'=>'📧', 'color'=>'#2563eb', 'href'=>route('admin.newsletter')],
    ['label'=>'Commenti', 'value'=>$stats['comments'], 'icon'=>'💬', 'color'=>'#d97706', 'href'=>route('admin.comments')],
    ['label'=>'Letture totali', 'value'=>number_format($stats['total_views'],0,',','.'), 'icon'=>'👁', 'color'=>'#16a34a', 'href'=>'#'],
  ] as $c)
  <a href="{{ $c['href'] }}" style="display:block;background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.08);padding:1rem;text-decoration:none;border-top:3px solid {{ $c['color'] }};">
    <div style="font-size:1.3rem;margin-bottom:.3rem;">{{ $c['icon'] }}</div>
    <div style="font-size:1.8rem;font-weight:900;color:{{ $c['color'] }};line-height:1;">
      {{ $c['value'] }}
    </div>
    <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#6b7280;margin-top:.2rem;">
      {{ $c['label'] }}
    </div>
  </a>
  @endforeach
</div>

{{-- Newsletter analytics --}}
<div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.08);padding:1.25rem;margin-bottom:1rem;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
    <div>
      <h2 style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#6b7280;margin:0;">
        📧 Newsletter analytics
      </h2>
      <p style="font-size:.75rem;color:#6b7280;margin:.35rem 0 0;">
        Aperture, click e performance della newsletter.
      </p>
    </div>
    <a href="{{ route('admin.newsletter.preview') }}" style="font-size:.72rem;color:#0d9488;text-decoration:none;">
      Anteprima newsletter →
    </a>
  </div>

  <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:.75rem;margin-bottom:1.25rem;">
    <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:.85rem;">
      <div style="font-size:.62rem;font-weight:700;text-transform:uppercase;color:#6b7280;">Iscritti</div>
      <div style="font-size:1.45rem;font-weight:900;color:#111827;">{{ $newsletterSubscribers }}</div>
    </div>

    <div style="background:#f0fdfa;border:1px solid #99f6e4;border-radius:8px;padding:.85rem;">
      <div style="font-size:.62rem;font-weight:700;text-transform:uppercase;color:#0f766e;">Aperture</div>
      <div style="font-size:1.45rem;font-weight:900;color:#0f766e;">{{ $newsletterOpens }}</div>
    </div>

    <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:.85rem;">
      <div style="font-size:.62rem;font-weight:700;text-transform:uppercase;color:#c2410c;">Click</div>
      <div style="font-size:1.45rem;font-weight:900;color:#c2410c;">{{ $newsletterClicks }}</div>
    </div>

    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:.85rem;">
      <div style="font-size:.62rem;font-weight:700;text-transform:uppercase;color:#1d4ed8;">Open rate</div>
      <div style="font-size:1.45rem;font-weight:900;color:#1d4ed8;">{{ $newsletterOpenRate }}%</div>
    </div>

    <div style="background:#f5f3ff;border:1px solid #ddd6fe;border-radius:8px;padding:.85rem;">
      <div style="font-size:.62rem;font-weight:700;text-transform:uppercase;color:#6d28d9;">CTR</div>
      <div style="font-size:1.45rem;font-weight:900;color:#6d28d9;">{{ $newsletterClickRate }}%</div>
    </div>
  </div>

  <h3 style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#6b7280;margin:0 0 .75rem;">
    Articoli più cliccati
  </h3>

  @forelse($topClickedArticles as $row)
    <div style="display:flex;justify-content:space-between;gap:1rem;padding:.6rem 0;border-bottom:1px solid #f3f4f6;">
      <div style="font-size:.8rem;font-weight:600;color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
        {{ $row->article?->title ?? 'Articolo eliminato' }}
      </div>
      <div style="font-size:.78rem;font-weight:800;color:#0d9488;white-space:nowrap;">
        {{ $row->clicks }} click
      </div>
    </div>
  @empty
    <p style="font-size:.78rem;color:#6b7280;margin:0;">
      Nessun click registrato ancora.
    </p>
  @endforelse
</div>

{{-- 2 colonne: top articoli + categorie --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
  <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.08);padding:1.25rem;">
    <h2 style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#6b7280;margin:0 0 1rem;">
      🏆 Più letti
    </h2>
    @foreach($topArticles as $i => $art)
    <div style="display:flex;align-items:center;gap:.6rem;padding:.55rem 0;border-bottom:1px solid #f3f4f6;">
      <div style="font-size:1.1rem;font-weight:900;color:#e5e7eb;width:1.3rem;text-align:center;flex-shrink:0;">
        {{ $i + 1 }}
      </div>
      <div style="flex:1;min-width:0;">
        <a href="{{ route('articolo', $art->slug) }}" target="_blank" style="font-size:.78rem;font-weight:600;color:#111827;text-decoration:none;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
          {{ $art->title }}
        </a>
        <span class="badge badge--{{ $art->category }}" style="font-size:.57rem;margin-top:.15rem;display:inline-block;">
          {{ config('laboratorio.categories.'.$art->category) }}
        </span>
      </div>
      <span style="font-size:.82rem;font-weight:700;color:#6b7280;flex-shrink:0;">
        {{ number_format($art->views, 0, ',', '.') }}
      </span>
    </div>
    @endforeach
  </div>

  <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.08);padding:1.25rem;">
    <h2 style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#6b7280;margin:0 0 1rem;">
      📊 Distribuzione
    </h2>
    @php $maxC = $byCategory->max('count') ?: 1; @endphp
    @foreach($byCategory as $cat)
    <div style="margin-bottom:.7rem;">
      <div style="display:flex;justify-content:space-between;margin-bottom:.2rem;">
        <span style="font-size:.75rem;font-weight:600;color:#111827;">
          {{ config('laboratorio.categories.'.$cat->category) }}
        </span>
        <span style="font-size:.68rem;color:#6b7280;">
          {{ $cat->count }} · {{ number_format($cat->views, 0, ',', '.') }}
        </span>
      </div>
      <div style="background:#f3f4f6;border-radius:4px;height:5px;">
        <div style="background:#0d9488;height:100%;border-radius:4px;width:{{ round(($cat->count/$maxC)*100) }}%;"></div>
      </div>
    </div>
    @endforeach
  </div>
</div>

{{-- Attività recente --}}
<div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.08);padding:1.25rem;margin-bottom:1rem;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.85rem;">
    <h2 style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#6b7280;margin:0;">
      🕐 Attività recente
    </h2>
    <a href="{{ route('admin.articles') }}" style="font-size:.72rem;color:#0d9488;text-decoration:none;">
      Vedi tutti →
    </a>
  </div>

  <div style="display:flex;flex-direction:column;gap:.4rem;">
    @foreach($recentArticles as $art)
    <div style="display:flex;align-items:center;gap:.6rem;padding:.45rem .6rem;border-radius:4px;background:#f9fafb;">
      <span style="font-size:.85rem;">{{ $art->status === 'published' ? '✅' : '📝' }}</span>
      <div style="flex:1;min-width:0;">
        <a href="{{ route('admin.articles.edit', $art) }}" style="font-size:.78rem;font-weight:600;color:#111827;text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;">
          {{ $art->title }}
        </a>
        <span style="font-size:.63rem;color:#6b7280;">
          {{ $art->author->name }} · {{ $art->created_at->locale('it')->diffForHumans() }}
          @if($art->status==='published' && in_array($art->verification_status,['unverified','in_progress']))
            · <span style="color:#ef4444;font-weight:700;">⚠ da verificare</span>
          @endif
        </span>
      </div>
      <span class="badge badge--{{ $art->category }}" style="font-size:.57rem;flex-shrink:0;">
        {{ config('laboratorio.categories.'.$art->category) }}
      </span>
    </div>
    @endforeach
  </div>
</div>

{{-- Azioni rapide --}}
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.65rem;">
  @foreach([
    ['label'=>'Nuovo articolo', 'icon'=>'✏️', 'route'=>'admin.articles.create', 'primary'=>true],
    ['label'=>'Verifica editoriale', 'icon'=>'✅', 'route'=>'admin.verification', 'primary'=>false],
    ['label'=>'Commenti', 'icon'=>'💬', 'route'=>'admin.comments', 'primary'=>false],
    ['label'=>'Newsletter', 'icon'=>'📧', 'route'=>'admin.newsletter', 'primary'=>false],
    ['label'=>'Libreria media', 'icon'=>'🖼', 'route'=>'admin.media', 'primary'=>false],
    ['label'=>'Suggerimenti AI', 'icon'=>'🤖', 'route'=>'admin.suggestions', 'primary'=>false],
  ] as $a)
  <a href="{{ route($a['route']) }}" style="display:flex;align-items:center;gap:.5rem;padding:.7rem .9rem;border-radius:8px;text-decoration:none;font-size:.8rem;font-weight:600;box-shadow:0 1px 3px rgba(0,0,0,0.08);background:{{ $a['primary'] ? '#0d9488' : '#ffffff' }};color:{{ $a['primary'] ? '#ffffff' : '#111827' }};border:1px solid {{ $a['primary'] ? '#0d9488' : '#e5e7eb' }};">
    <span>{{ $a['icon'] }}</span> {{ $a['label'] }}
  </a>
  @endforeach
</div>

@endsection