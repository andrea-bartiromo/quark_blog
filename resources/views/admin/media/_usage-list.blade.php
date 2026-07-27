@if($usageCount === 0)
  <p class="media-card__usage-empty">Nessun utilizzo rilevato.</p>
@else
  <ul class="media-card__usage-list">
    @foreach($usage as $record)
      <li>
        <span class="media-card__usage-type">{{ $record['usage_type_label'] }}</span>
        <span class="media-card__usage-title">{{ $record['content_type'] }}: {{ $record['title'] }}</span>
        @if($record['status'])
          <span class="media-card__usage-status">{{ $record['status'] }}</span>
        @endif
        @if($record['edit_url'])
          <a href="{{ $record['edit_url'] }}" class="media-card__usage-link">Apri</a>
        @endif
      </li>
    @endforeach
  </ul>
@endif
