<section class="article-premium__body">
  @if($article->cover_caption || $article->cover_credit || $article->cover_source || $article->cover_license)
  <figure class="article-premium__panel" style="margin:0 0 2rem;">
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
  @endif

  @if($isHtml)
    {!! $mainBody !!}
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
