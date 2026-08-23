{{--
    "Continua da qui": UNA sola prosecuzione editoriale, non un mini-grid di
    esplorazione (quello è già related-articles.blade.php, sotto). Non
    renderizza mai quando $continuation coincide con il "Successivo" già
    mostrato da path-continuation.blade.php sopra — la duplicazione era
    l'unico rischio concreto qui, dato che ArticleContinuationService dà
    sempre priorità al Percorso quando esiste (vedi
    app/Services/ArticleContinuationService.php).
--}}
@if($continuation && (! $pathNavigation || ! $pathNavigation['next']))
<section class="continue-reading" aria-labelledby="continue-reading-title">
    <h2 id="continue-reading-title" class="continue-reading__heading">Continua da qui</h2>

    <a class="continue-reading__card"
       href="{{ route('articolo', $continuation->slug) }}"
       data-continuation-click
       data-source-article-id="{{ $article->id }}"
       data-target-article-id="{{ $continuation->id }}">
        <div class="continue-reading__media">
            <x-responsive-image
                :diskName="$continuation->cover_image ?: null"
                :src="asset('assets/img/placeholder-1.svg')"
                :onerrorSrc="asset('assets/img/placeholder-1.svg')"
                :alt="$continuation->title"
                :sizes="'(max-width: 640px) 100vw, 240px'"
            />
        </div>
        <div class="continue-reading__body">
            <span class="continue-reading__category">{{ $categoryOptions[$continuation->category] ?? $continuation->category }}</span>
            <strong class="continue-reading__title">{{ $continuation->title }}</strong>
            <p class="continue-reading__excerpt">{{ Str::limit($continuation->excerpt, 110) }}</p>
            <span class="continue-reading__meta">{{ $continuation->read_time }}</span>
        </div>
    </a>
</section>
@endif
