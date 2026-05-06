@extends('layouts.admin')
@section('title', 'Articoli in revisione')

@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Articoli in revisione</h1>
  <span style="font-size:.78rem;color:#6b7280;">
    {{ $articles->count() }} {{ $articles->count() === 1 ? 'articolo' : 'articoli' }} da revisionare
  </span>
</div>

@if($articles->isEmpty())
<div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);
            padding:3rem;text-align:center;color:#6b7280;">
  <p style="font-size:1.5rem;margin-bottom:.5rem;">✅</p>
  <p>Nessun articolo in attesa di revisione.</p>
</div>
@else
@foreach($articles as $article)
<div style="background:#fff;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,.08);
            padding:1.5rem;margin-bottom:1rem;">
  <div style="display:flex;gap:1.5rem;align-items:flex-start;">

    {{-- Copertina --}}
    @if($article->cover_image)
    <img src="{{ asset('assets/img/'.$article->cover_image) }}" alt=""
         style="width:120px;height:80px;object-fit:cover;border-radius:8px;flex-shrink:0;"
         onerror="this.style.display='none'">
    @endif

    <div style="flex:1;min-width:0;">
      <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.4rem;">
        <span class="badge badge--{{ $article->category }}">
          {{ config('laboratorio.categories.'.$article->category) }}
        </span>
        <span style="font-size:.72rem;color:#6b7280;">
          Inviato {{ $article->updated_at->diffForHumans() }}
          da <strong>{{ $article->author->name }}</strong>
        </span>
      </div>

      <h2 style="font-size:1rem;font-weight:700;color:#111827;margin-bottom:.35rem;">
        {{ $article->title }}
      </h2>

      @if($article->excerpt)
      <p style="font-size:.82rem;color:#6b7280;line-height:1.55;margin-bottom:.75rem;">
        {{ $article->excerpt }}
      </p>
      @endif

      <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
        <a href="{{ route('admin.articles.edit', $article) }}"
           class="btn btn--secondary btn--sm">👁 Leggi e modifica</a>

        {{-- Form approva --}}
        <form method="POST" action="{{ route('admin.review.approve', $article) }}">
          @csrf @method('PATCH')
          <button type="submit" class="btn btn--primary btn--sm"
                  onclick="return confirm('Pubblicare questo articolo?')">
            ✅ Approva e pubblica
          </button>
        </form>

        {{-- Form rifiuta --}}
        <button type="button" class="btn btn--danger btn--sm"
                onclick="document.getElementById('reject-{{ $article->id }}').style.display='block'">
          ✕ Rimanda con nota
        </button>
      </div>

      {{-- Form rifiuto con nota --}}
      <div id="reject-{{ $article->id }}" style="display:none;margin-top:1rem;
           background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:1rem;">
        <form method="POST" action="{{ route('admin.review.reject', $article) }}">
          @csrf @method('PATCH')
          <label style="font-size:.78rem;font-weight:600;color:#991b1b;display:block;margin-bottom:.4rem;">
            Nota per il collaboratore (opzionale):
          </label>
          <textarea name="note" class="form-textarea"
                    style="min-height:70px;margin-bottom:.5rem;"
                    placeholder="Spiega cosa deve modificare..."></textarea>
          <div style="display:flex;gap:.5rem;">
            <button type="submit" class="btn btn--danger btn--sm">Rimanda in bozza</button>
            <button type="button" class="btn btn--secondary btn--sm"
                    onclick="document.getElementById('reject-{{ $article->id }}').style.display='none'">
              Annulla
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endforeach
@endif

@endsection