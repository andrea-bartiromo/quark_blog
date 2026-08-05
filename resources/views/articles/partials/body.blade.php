<section class="article-premium__body">
  @if($article->cover_caption || $article->cover_credit || $article->cover_source || $article->cover_license)
  <details class="cover-info">
    <summary class="cover-info__summary">
      <svg class="cover-info__icon" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
        <circle cx="10" cy="10" r="9" fill="none" stroke="currentColor" stroke-width="1.5"/>
        <circle cx="10" cy="6.2" r="1.1" fill="currentColor"/>
        <rect x="9.1" y="8.8" width="1.8" height="6" rx=".9" fill="currentColor"/>
      </svg>
      <span class="cover-info__label">Informazioni sull'immagine</span>
      <svg class="cover-info__chevron" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
        <path d="M5.5 7.5 10 12l4.5-4.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </summary>
    <div class="cover-info__body">
      <div class="cover-info__body-inner">
        <figure class="article-premium__panel" style="margin:0;">
          @if($article->cover_caption)
          <figcaption>{{ $article->cover_caption }}</figcaption>
          @endif

          @if($article->cover_credit)
          <small style="display:block;margin-top:.5rem;">Credito: {{ $article->cover_credit }}</small>
          @endif

          @if($article->cover_source)
          <small style="display:block;margin-top:.25rem;">
            Fonte:
            @if($article->cover_source_url && filter_var($article->cover_source_url, FILTER_VALIDATE_URL))
              <a href="{{ $article->cover_source_url }}" target="_blank" rel="noopener noreferrer">{{ $article->cover_source }}</a>
            @else
              {{ $article->cover_source }}
            @endif
          </small>
          @endif

          @if($article->cover_license)
          <small style="display:block;margin-top:.25rem;">Licenza: {{ $article->cover_license }}</small>
          @endif
        </figure>
      </div>
    </div>
  </details>
  @endif

  @if($isHtml)
    {!! $mainBodyWithTocIds !!}
  @else
    @php
      $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', e($mainBody));
      $paragraphs = array_filter(explode("\n\n", $html));
    @endphp

    @foreach($paragraphs as $para)
      @php
        $para = trim($para);
        $firstLine = explode("\n", $para)[0] ?? '';
      @endphp

      @if(Str::startsWith($para, '<strong>') && Str::contains($firstLine, '</strong>'))
        <h2>{!! preg_replace('/^<strong>(.*?)<\/strong>/', '$1', $firstLine) !!}</h2>
        @php $rest = implode("\n", array_slice(explode("\n", $para), 1)); @endphp
        @if(trim($rest))
          <p>{!! nl2br($rest) !!}</p>
        @endif
      @else
        <p>{!! nl2br($para) !!}</p>
      @endif
    @endforeach
  @endif

  @if($sources)
  <div class="article-premium__panel" style="margin-top:2rem;">
    <h3>Fonti</h3>
    <p style="margin:0;">{!! nl2br(e($sources)) !!}</p>
  </div>
  @endif
</section>
