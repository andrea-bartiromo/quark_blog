@props(['draft', 'copy', 'url'])

@php
    $networkLabel = \App\Models\SocialDraft::CHANNELS[$draft->channel] ?? $draft->channel;
    $article = $draft->article;
@endphp

<section class="social-draft-preview" aria-labelledby="social-draft-preview-heading">
  <h3 id="social-draft-preview-heading">Anteprima {{ $networkLabel }}</h3>
  <p style="font-size:.78rem;color:var(--admin-muted);margin:.2rem 0 .9rem;">
    Anteprima editoriale interna: la resa finale può differire da quella della piattaforma.
  </p>

  <div style="border:1px solid var(--admin-border,#e2e8f0);border-radius:10px;padding:1rem;max-width:480px;background:#fff;">
    @if($article && $article->cover_image)
      <img src="{{ asset('assets/img/'.$article->cover_image) }}"
           alt="{{ $article->cover_alt ?: '' }}"
           style="width:100%;border-radius:6px;display:block;margin-bottom:.75rem;"
           loading="lazy" decoding="async">
      @unless($article->cover_alt)
        <p role="status" style="font-size:.74rem;color:#b45309;margin:0 0 .5rem;">
          ⚠ Testo alternativo mancante per la copertina — la bozza non è considerabile pronta finché non viene aggiunto in Admin → Articolo.
        </p>
      @endunless
    @else
      <div style="background:#f1f5f9;border-radius:6px;padding:1.5rem 1rem;margin-bottom:.75rem;text-align:center;color:#475569;font-size:.85rem;">
        <strong>{{ $article->title ?? 'Articolo' }}</strong>
        <br>{{ $url }}
      </div>
    @endif

    <p style="white-space:pre-wrap;font-size:.9rem;margin:0 0 .5rem;">{{ $copy ?: '(copy non ancora scritto)' }}</p>
    <p style="font-size:.78rem;color:#0f766e;word-break:break-all;margin:0;">{{ $url }}</p>
  </div>
</section>
