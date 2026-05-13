@if($timeline->isNotEmpty())
<section class="turing-section turing-section--dark">
  <div class="container container--wide">
    <div class="turing-section__head">
      <p class="turing-kicker">Timeline</p>
      <h2>Una vita che attraversa il Novecento</h2>
    </div>

    <div class="turing-timeline">
      @foreach($timeline as $event)
        <div class="turing-timeline__item">
          <div class="turing-timeline__year">{{ $event['year'] ?? '' }}</div>
          <div>
            <h3>{{ $event['title'] ?? 'Evento Turing' }}</h3>
            <p>{{ $event['text'] ?? '' }}</p>
            @if(!empty($event['image']))<img class="turing-timeline__media" src="{{ $img($event['image']) }}" alt="">@endif
          </div>
        </div>
      @endforeach
    </div>
  </div>
</section>
@endif
