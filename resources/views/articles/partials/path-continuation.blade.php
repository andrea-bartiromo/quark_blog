@if($pathNavigation)
<section
    class="path-continuation"
    aria-labelledby="path-continuation-title"
    data-path-slug="{{ $pathNavigation['cluster']->slug }}"
    data-article-id="{{ $article->id }}"
    data-path-position="{{ $pathNavigation['current_index'] }}"
    data-path-total="{{ $pathNavigation['total'] }}"
>
    <div class="path-continuation__header">
        <div>
            <p class="path-continuation__eyebrow">Percorso</p>
            <h2 id="path-continuation-title">Continua il percorso</h2>
            <a class="path-continuation__name" href="{{ $pathNavigation['path_url'] }}" data-path-event="path_view_all_click">
                {{ $pathNavigation['cluster']->name }}
            </a>
        </div>
        <p class="path-continuation__progress" aria-label="Posizione nel percorso">
            {{ $pathNavigation['current_index'] }} di {{ $pathNavigation['total'] }}
        </p>
    </div>

    @if($pathNavigation['previous'] || $pathNavigation['next'])
        <nav class="path-continuation__nav" aria-label="Navigazione nel percorso {{ $pathNavigation['cluster']->name }}">
            @if($pathNavigation['previous'])
                <a class="path-continuation__step path-continuation__step--previous" href="{{ route('articolo', $pathNavigation['previous']->slug) }}" data-path-event="path_previous_click" data-target-article-id="{{ $pathNavigation['previous']->id }}">
                    <span class="path-continuation__direction">Precedente</span>
                    <span>{{ $pathNavigation['previous']->title }}</span>
                </a>
            @endif

            @if($pathNavigation['next'])
                <a class="path-continuation__step path-continuation__step--next" href="{{ route('articolo', $pathNavigation['next']->slug) }}" data-path-event="path_next_click" data-target-article-id="{{ $pathNavigation['next']->id }}">
                    <span class="path-continuation__direction">Successivo</span>
                    <span>{{ $pathNavigation['next']->title }}</span>
                </a>
            @endif
        </nav>
    @endif

    <a class="path-continuation__all" href="{{ $pathNavigation['path_url'] }}" data-path-event="path_view_all_click">Vedi tutto il percorso</a>
</section>
@endif
