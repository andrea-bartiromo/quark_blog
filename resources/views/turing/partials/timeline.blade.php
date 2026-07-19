@if($timeline->isNotEmpty())
<section class="turing-section turing-section--dark" id="timeline">
  <div class="turing-section has-bg turing-timeline__header" style="{{ $bg($timelineBackgroundImage) }}">
    <div class="container container--wide">
      <div class="turing-section__head">
        <p class="turing-kicker">Timeline</p>
        <h2>Una vita che attraversa il Novecento</h2>
      </div>
    </div>
  </div>

  <div class="container container--wide">
    <div class="turing-timeline">
      @foreach($timeline as $event)
        @php
          $eventUrl = $event['url'] ?? $event['link_url'] ?? null;
          $tag = filled($eventUrl) ? 'a' : 'div';
        @endphp
        <{{ $tag }} class="turing-timeline__item {{ filled($eventUrl) ? 'turing-timeline__item--link' : '' }}" @if(filled($eventUrl)) href="{{ $eventUrl }}" @endif>
          <div class="turing-timeline__year">{{ $event['year'] ?? '' }}</div>
          <div>
            <h3>{{ $event['title'] ?? 'Evento Turing' }}</h3>
            <p>{{ $event['text'] ?? '' }}</p>
            @if(!empty($event['image']))<img class="turing-timeline__media" src="{{ $img($event['image']) }}" alt="{{ $event['alt'] ?? $event['title'] ?? '' }}">@endif
          </div>
        </{{ $tag }}>
      @endforeach
    </div>
  </div>
</section>
@endif
