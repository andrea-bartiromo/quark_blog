@if($pathNavigation)
<section class="path-continuation" aria-labelledby="path-continuation-title" data-path-slug="{{ $pathNavigation['cluster']->slug }}" data-article-id="{{ $article->id }}" data-path-position="{{ $pathNavigation['current_index'] }}" data-path-total="{{ $pathNavigation['total'] }}">
    <div class="path-continuation__header">
        <div><p class="path-continuation__eyebrow">Percorso · {{ $pathNavigation['cluster']->name }}</p><h2 id="path-continuation-title">Continua il percorso</h2></div>
        <div class="path-continuation__progress-wrap"><p class="path-continuation__progress" aria-label="Posizione nel percorso">{{ $pathNavigation['current_index'] }} di {{ $pathNavigation['total'] }}</p><div class="path-continuation__meter" aria-hidden="true"><span style="width: {{ ($pathNavigation['current_index'] / max(1, $pathNavigation['total'])) * 100 }}%"></span></div></div>
    </div>
    @if($pathNavigation['previous'] || $pathNavigation['next'])
        <nav class="path-continuation__nav {{ !($pathNavigation['previous'] && $pathNavigation['next']) ? 'path-continuation__nav--single' : '' }}" aria-label="Navigazione nel percorso {{ $pathNavigation['cluster']->name }}">
            @if($pathNavigation['previous'])<a class="path-continuation__step path-continuation__step--previous" href="{{ route('articolo', $pathNavigation['previous']->slug) }}" data-path-event="path_previous_click" data-target-article-id="{{ $pathNavigation['previous']->id }}"><span class="path-continuation__direction"><span aria-hidden="true">←</span> Precedente</span><strong>{{ $pathNavigation['previous']->title }}</strong></a>@endif
            @if($pathNavigation['next'])<a class="path-continuation__step path-continuation__step--next" href="{{ route('articolo', $pathNavigation['next']->slug) }}" data-path-event="path_next_click" data-target-article-id="{{ $pathNavigation['next']->id }}"><span class="path-continuation__direction">Successivo <span aria-hidden="true">→</span></span><strong>{{ $pathNavigation['next']->title }}</strong></a>@endif
        </nav>
    @else
        <p class="path-continuation__complete">Questo è l'unico articolo pubblicato del percorso.</p>
    @endif
    <div class="path-continuation__footer"><a class="path-continuation__all" href="{{ $pathNavigation['path_url'] }}" data-path-event="path_view_all_click">Vedi tutto il percorso “{{ $pathNavigation['cluster']->name }}” <span aria-hidden="true">→</span></a></div>
</section>
@endif
