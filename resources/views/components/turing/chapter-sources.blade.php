@props(['chapter'])

@php
    $sources = \App\Models\TuringChapterSource::query()
        ->where('chapter', $chapter)
        ->orderBy('sort_order')
        ->orderBy('id')
        ->get();
@endphp

@if($sources->isNotEmpty())
    <section aria-labelledby="turing-chapter-sources-heading" class="turing-chapter-sources">
        <h2 id="turing-chapter-sources-heading">Fonti</h2>
        <ol>
            @foreach($sources as $source)
                <li>
                    <a href="{{ $source->url }}" target="_blank" rel="noopener">{{ $source->label }}</a>
                    @if($source->year)
                        ({{ $source->year }})
                    @endif
                </li>
            @endforeach
        </ol>
    </section>
@endif
