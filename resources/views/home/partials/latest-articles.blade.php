{{--
    Cantiere D — Home + Percorsi Visual Adoption (Prompt 27-37).
    Stesso $latest, stesso limite (6), stesso ordinamento di
    HomeController::index() — solo il markup cambia.
--}}
<section class="home-editorial-section">
  <x-kairus.section-heading
    eyebrow="Latest from Kairus"
    title="Ultimi articoli"
    :heading-level="2"
  >
    <x-slot:action>
      <a href="{{ route('notizie') }}" class="kairus-focusable">Vedi tutti gli articoli →</a>
    </x-slot:action>
  </x-kairus.section-heading>

  @forelse($latest->take(6) as $article)
    @if($loop->first)
      <ul class="home-editorial-grid kairus-latest-articles__grid">
    @endif

        {{--
            Prompt 29: variante standard per ogni card (nessuna
            distinzione di variante richiesta dal prompt) — la classe
            legacy home-editorial-card--lead resta applicata solo alla
            prima card, comportamento CSS preesistente invariato (span
            di 2 colonne sotto 820px), non rimosso qui.
        --}}
        <li>
          <x-kairus.article-card
            :href="route('articolo', $article->slug)"
            :title="$article->title"
            :excerpt="Str::limit($article->excerpt, $loop->first ? 155 : 96)"
            :category-label="$categoryLabel($article)"
            variant="standard"
            :class="'home-editorial-card '.($loop->first ? 'home-editorial-card--lead' : '')"
          >
            <x-slot:image>
              <x-responsive-image
                :diskName="$article->cover_image ?: null"
                :src="$visualFor($article)"
                :onerrorSrc="$visualFor($article)"
                :alt="$article->title"
                :sizes="'(max-width: 640px) 100vw, (max-width: 820px) 50vw, 33vw'"
              />
            </x-slot:image>
            <x-slot:meta>
              <x-kairus.article-meta
                :author="Str::before($article->author->name, ' ')"
                :read-minutes="$article->read_minutes"
                density="compact"
              />
            </x-slot:meta>
          </x-kairus.article-card>
        </li>

    @if($loop->last)
      </ul>
    @endif
  @empty
    <x-kairus.empty-state
      title="Nessun articolo pubblicato ancora"
      message="Torna presto: qui compariranno gli ultimi articoli di Kairus."
      icon="notice"
    >
      <x-slot:action>
        <a href="{{ route('notizie') }}">Vai a tutti gli articoli</a>
      </x-slot:action>
    </x-kairus.empty-state>
  @endforelse
</section>
