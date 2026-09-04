{{--
    Cantiere E (Prompt 124). Algoritmo/quantità/ordinamento/destinazioni
    di $relatedItems invariati (ArticleRelatedService, controller). Solo
    il markup di ciascuna card diventa x-kairus.article-card — un solo
    <a> per card, come già era qui (nessun link annidato prima o dopo).
--}}
@if($relatedItems->count())
<section class="kairus-related-articles" style="margin-top:2rem;">
  <div class="public-section-head">
    <div>
      <span>Related stories</span>
      <h2>Continua a leggere</h2>
    </div>
  </div>

  <ul class="related-premium-grid kairus-related-articles__grid">
    @foreach($relatedItems as $item)
    <li>
      <x-kairus.article-card
          :href="route('articolo', $item->slug)"
          :title="$item->title"
          :excerpt="Str::limit($item->excerpt, 90)"
      >
        <x-slot:image>
          <x-responsive-image
              :diskName="$item->cover_image ?: null"
              :src="asset('assets/img/placeholder-1.svg')"
              :onerrorSrc="asset('assets/img/placeholder-1.svg')"
              :alt="$item->title"
              :sizes="'(max-width: 900px) 100vw, 33vw'"
          />
        </x-slot:image>
      </x-kairus.article-card>
    </li>
    @endforeach
  </ul>
</section>
@endif
