@extends('layouts.admin')
@section('title','Bozza Social — '.($draft->article->title ?? 'Articolo'))
@section('content')

@php
    $article = $draft->article;
    $channelLabel = \App\Models\SocialDraft::CHANNELS[$draft->channel] ?? $draft->channel;
    $statusLabels = [
        'draft' => 'Bozza', 'reviewed' => 'Revisionato', 'approved' => 'Approvato',
        'scheduled' => 'Programmato', 'published' => 'Pubblicato (storico)', 'failed' => 'Fallito (storico)',
    ];
@endphp

<div class="admin-topbar">
  <h1 class="admin-page-title">Bozza Social — {{ $channelLabel }}</h1>
  <a href="{{ route('admin.social-drafts.index') }}" class="btn btn--secondary">← Torna alle bozze</a>
</div>

@if(session('error'))
<div role="alert" style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;color:#991b1b;font-size:.85rem;">
  {{ session('error') }}
</div>
@endif

@if($errors->any())
<div role="alert" style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;color:#991b1b;font-size:.85rem;">
  <ul style="margin:0;padding-left:1.2rem;">
    @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
  </ul>
</div>
@endif

<p><span class="badge badge--{{ $draft->status }}">{{ $statusLabels[$draft->status] ?? $draft->status }}</span>
   — collegata a <a href="{{ route('admin.articles.edit', $article) }}">{{ $article->title ?? 'articolo eliminato' }}</a>
   (stato editoriale: {{ $article->status ?? '—' }})</p>

@unless(in_array(optional($article)->status, ['published','scheduled'], true))
<div role="status" style="background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;color:#92400e;font-size:.82rem;">
  ⚠ L'articolo collegato non è ancora pubblico né programmato: questa bozza può restare in preparazione, ma non potrà essere programmata finché l'articolo non lo è.
</div>
@endunless

<div class="public-premium-layout" style="display:grid;grid-template-columns:minmax(0,1fr) 380px;gap:1.5rem;align-items:start;">
  <div>

    <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1.5rem;margin-bottom:1.25rem;">
      <h2 style="margin-top:0;font-size:1.05rem;">Contenuto e programmazione</h2>

      <form method="POST" action="{{ route('admin.social-drafts.update', $draft) }}">
        @csrf
        @method('PUT')

        <div class="form-group">
          <label class="form-label" for="copy">Copy {{ $isEditableFully ? '' : '(sola lettura)' }}</label>
          <textarea id="copy" name="copy" class="form-input" rows="6" maxlength="10000"
                    @unless($isEditableFully) readonly @endunless>{{ old('copy', $draft->copy) }}</textarea>
        </div>

        <div class="form-group">
          <label class="form-label" for="destination_url">URL personalizzato (opzionale) {{ $isEditableFully ? '' : '(sola lettura)' }}</label>
          <input type="text" id="destination_url" name="destination_url" class="form-input"
                 value="{{ old('destination_url', $draft->destination_url) }}"
                 @unless($isEditableFully) readonly @endunless
                 placeholder="Vuoto = URL canonico dell'articolo">
          <small style="font-size:.72rem;color:var(--admin-muted);">
            URL naturale: <code>{{ $naturalUrl }}</code>
          </small>
        </div>

        <div class="form-group">
          <label class="form-label" for="use_utm">
            {{-- Un checkbox deselezionato non viene inviato dal browser:
                 senza questo hidden, togliere la spunta lascerebbe
                 use_utm invariato (mai spento) invece di passare a false. --}}
            <input type="hidden" name="use_utm" value="0">
            <input type="checkbox" id="use_utm" name="use_utm" value="1" @checked(old('use_utm', $draft->use_utm)) @disabled($isLocked)>
            Aggiungi parametri UTM
          </label>
        </div>

        <div class="form-group">
          <label class="form-label" for="utm_campaign">Nome campagna UTM (opzionale)</label>
          <input type="text" id="utm_campaign" name="utm_campaign" class="form-input"
                 value="{{ old('utm_campaign', $draft->utm_campaign) }}"
                 @disabled($isLocked)
                 placeholder="es. lancio-fisica-2026">
          <small style="font-size:.72rem;color:var(--admin-muted);">Se vuoto, generata automaticamente da canale + slug articolo.</small>
        </div>

        <div class="form-group">
          <label class="form-label" for="scheduled_date">Programmazione — Europe/Rome {{ $isLocked ? '(sola lettura, annulla la programmazione per modificare)' : '' }}</label>
          <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <input type="date" id="scheduled_date" name="scheduled_date" class="form-input"
                   value="{{ old('scheduled_date', optional($editorialScheduledAt)->format('Y-m-d')) }}"
                   @disabled($isLocked)>
            <input type="time" id="scheduled_time" name="scheduled_time" class="form-input"
                   value="{{ old('scheduled_time', optional($editorialScheduledAt)->format('H:i')) }}"
                   @disabled($isLocked)>
          </div>
          @if($draft->scheduled_at)
          <small style="font-size:.72rem;color:var(--admin-muted);">
            Nota tecnica: {{ $draft->scheduled_at->toIso8601String() }} UTC.
          </small>
          @endif
        </div>

        @unless($isLocked)
        <button type="submit" class="btn btn--primary">Salva</button>
        @endunless
      </form>
    </div>

    <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1.5rem;margin-bottom:1.25rem;">
      <h2 style="margin-top:0;font-size:1.05rem;">Azioni</h2>
      <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
        @php
          $actionLabels = [
            'reviewed' => 'Invia in revisione',
            'draft' => 'Riporta in bozza',
            'approved' => 'Approva',
            'scheduled' => 'Programma',
          ];
          // "Riporta in revisione" e "Annulla programmazione" condividono la
          // stessa destinazione tecnica (reviewed/approved) di altre azioni:
          // l'etichetta dipende dallo stato di partenza, non solo dal target.
          if ($draft->status === 'approved' && in_array('reviewed', $allowedTargets, true)) {
              $actionLabels['reviewed'] = 'Riporta in revisione';
          }
          if ($draft->status === 'scheduled' && in_array('approved', $allowedTargets, true)) {
              $actionLabels['approved'] = 'Annulla programmazione';
          }
        @endphp

        @forelse($allowedTargets as $target)
          <form method="POST" action="{{ route('admin.social-drafts.transition', $draft) }}"
                onsubmit="return confirm('{{ $actionLabels[$target] ?? $target }}: confermi?');">
            @csrf
            <input type="hidden" name="to" value="{{ $target }}">
            <button type="submit" class="btn btn--secondary">{{ $actionLabels[$target] ?? $target }}</button>
          </form>
        @empty
          <p style="color:var(--admin-muted);font-size:.85rem;">Nessuna transizione disponibile da questo stato.</p>
        @endforelse
      </div>
    </div>

    <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1.5rem;">
      <h2 style="margin-top:0;font-size:1.05rem;">Storico</h2>
      @if($history->isEmpty())
        <p style="color:var(--admin-muted);font-size:.85rem;">Nessuna azione registrata ancora.</p>
      @else
        <ul style="list-style:none;margin:0;padding:0;font-size:.85rem;">
          @foreach($history as $entry)
            <li style="padding:.5rem 0;border-bottom:1px solid #f1f5f9;">
              <strong>{{ $entry->action }}</strong>
              — {{ $entry->user?->name ?? 'Sistema' }}
              — <time datetime="{{ $entry->created_at->toIso8601String() }}">{{ $entry->created_at->clone()->timezone('Europe/Rome')->locale('it')->isoFormat('D MMM YYYY, HH:mm') }}</time>
            </li>
          @endforeach
        </ul>
      @endif
    </div>

  </div>

  <aside>
    @if($previewUrl)
      <x-admin.social-draft-preview :draft="$draft" :copy="$draft->copy" :url="$previewUrl" />
    @else
      <p style="color:#991b1b;font-size:.85rem;">URL di destinazione non valido: correggi l'URL personalizzato per vedere l'anteprima.</p>
    @endif
  </aside>
</div>

@endsection
