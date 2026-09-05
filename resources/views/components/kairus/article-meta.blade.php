{{--
    Kairus Editorial Foundations V1 — Missione 06.
    Metadati articolo: mostra solo i dati realmente passati, nessuna query,
    nessuna logica di business. publishedAt/updatedAt sono attesi come
    \DateTimeInterface (es. il cast Carbon già applicato da Article) — un
    valore assente o di altro tipo viene semplicemente omesso, mai
    interpretato o riformattato "a intuito".
--}}
@props([
    'author' => null,
    'publishedAt' => null,
    'updatedAt' => null,
    'readMinutes' => null,
    'categoryLabel' => null,
    'density' => 'standard',
])

@php
    $publishedAt = $publishedAt instanceof \DateTimeInterface ? $publishedAt : null;
    $updatedAt = $updatedAt instanceof \DateTimeInterface ? $updatedAt : null;
    $hasReadMinutes = filled($readMinutes);
    $hasCategory = filled($categoryLabel);
    $hasAuthor = filled($author);

    $hasAnyItem = $hasAuthor || $publishedAt || $updatedAt || $hasReadMinutes || $hasCategory;
    $densityClass = $density === 'compact' ? 'kairus-article-meta--compact' : null;
@endphp

@if($hasAnyItem)
<ul {{ $attributes->class(['kairus-article-meta', $densityClass]) }}>
    @if($hasAuthor)
        <li class="kairus-article-meta__item kairus-article-meta__item--author">{{ $author }}</li>
    @endif

    @if($publishedAt)
        <li class="kairus-article-meta__item">
            <time datetime="{{ $publishedAt->toIso8601String() }}">{{ $publishedAt->translatedFormat('d M Y') }}</time>
        </li>
    @endif

    @if($updatedAt)
        <li class="kairus-article-meta__item kairus-article-meta__item--updated">
            <time datetime="{{ $updatedAt->toIso8601String() }}">{{ __('Aggiornato il') }} {{ $updatedAt->translatedFormat('d M Y') }}</time>
        </li>
    @endif

    @if($hasReadMinutes)
        <li class="kairus-article-meta__item">{{ trans_choice(':count minuto di lettura|:count minuti di lettura', (int) $readMinutes, ['count' => $readMinutes]) }}</li>
    @endif

    @if($hasCategory)
        <li class="kairus-article-meta__item kairus-article-meta__item--category">{{ $categoryLabel }}</li>
    @endif
</ul>
@endif
