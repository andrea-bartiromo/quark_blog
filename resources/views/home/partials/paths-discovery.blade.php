{{--
    Cantiere D — Home + Percorsi Visual Adoption (Prompt 38-44). Stesso
    $homePaths (query e limite invariati in HomeController::index()),
    stesso criterio di selezione — solo il markup cambia. Superficie sage
    leggera (Prompt 41) per distinguere visivamente le card Percorso dalle
    card articolo, senza toni promozionali.
--}}
<section class="home-paths kairus-home-paths" aria-labelledby="home-paths-title">
  <div class="container container--wide kairus-page-shell">
    <div class="home-paths__shell">
      <x-kairus.section-heading
        eyebrow="Percorsi"
        title="Un tema, dall'inizio."
        description="Sequenze editoriali curate per orientarti tra gli articoli di Kairus senza perderti i passaggi essenziali."
        class="kairus-home-paths__intro"
      >
        <x-slot:action>
          <a href="{{ route('percorsi.index') }}" class="home-paths__all kairus-focusable">Scopri tutti i percorsi <span aria-hidden="true">→</span></a>
        </x-slot:action>
      </x-kairus.section-heading>

      <ul class="home-paths__cards kairus-home-paths__grid">
        @foreach($homePaths as $path)
          <li>
            <x-kairus.path-card
              :href="route('percorsi.show', $path->slug)"
              :title="$path->name"
              :description="$path->short_description ? Str::limit($path->short_description, 95) : null"
              :article-count="$path->published_articles_count"
              cta="Esplora il percorso"
              :class="'home-path-link '.\App\Support\PathVisualSignature::cssClass($path)"
            >
              @if($path->cover_image)
                <x-slot:image>
                  <img src="{{ asset('assets/img/'.ltrim($path->cover_image, '/')) }}" alt="" loading="lazy" width="184" height="184">
                </x-slot:image>
              @endif
            </x-kairus.path-card>
          </li>
        @endforeach
      </ul>
    </div>
  </div>
</section>
