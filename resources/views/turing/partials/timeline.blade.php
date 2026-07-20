@if($timelineChapters->isNotEmpty())
  {{-- Cover: apertura dell'intera Timeline (Decision #001), invariata --}}
  <x-special.timeline
    :events="[]"
    kicker="Timeline"
    title="Una vita che attraversa il Novecento"
    :background="$timelineBackgroundImage"
    id="timeline"
  />

  {{-- Cover -> (Chapter Opener -> eventi) ripetuto per ogni capitolo temporale (Decision #003) --}}
  @foreach($timelineChapters as $index => $chapter)
    <x-special.chapter-opener
      :id="'timeline-chapter-opener-'.($index + 1)"
      :period="$chapter['period'] ?? null"
      :title="$chapter['title'] ?? null"
      :intro="$chapter['intro'] ?? null"
      :image="$chapter['image'] ?? null"
      :alt="$chapter['alt'] ?? null"
    />
    <x-special.timeline
      :events="$chapter['events'] ?? []"
      :id="'timeline-chapter-'.($index + 1)"
    />
  @endforeach
@else
  <x-special.timeline
    :events="$timeline"
    kicker="Timeline"
    title="Una vita che attraversa il Novecento"
    :background="$timelineBackgroundImage"
    id="timeline"
  />
@endif
