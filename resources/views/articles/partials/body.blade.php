<section class="article-premium__body">
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
