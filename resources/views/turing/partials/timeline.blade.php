@if($timelineChapters->isNotEmpty())
  {{-- Cover: apertura dell'intera Timeline (Decision #001), invariata --}}
  <x-special.timeline
    :events="[]"
    kicker="Timeline"
    title="Una vita che attraversa il Novecento"
    :background="$timelineBackgroundImage"
    id="timeline"
  />

  {{-- Cover -> (Chapter Opener -> eventi) ripetuto per ogni capitolo temporale
       (Decision #003). Il wrapper .turing-timeline-chapter rende le due
       sezioni un'unica superficie nera (vedi turing.css): raggruppa soltanto,
       nessuna logica propria, entrambi i componenti restano invariati. --}}
  @foreach($timelineChapters as $index => $chapter)
    <div class="turing-timeline-chapter">
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
    </div>
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
