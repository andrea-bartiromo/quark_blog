@if($relatedItems->count())
<section style="margin-top:2rem;">
  <div class="public-section-head">
    <div>
      <span>Related stories</span>
      <h2>Continua a leggere</h2>
    </div>
  </div>

  <div class="related-premium-grid">
    @foreach($relatedItems as $item)
    <a href="{{ route('articolo', $item->slug) }}" class="public-card">
      <div class="public-card__media">
        <x-responsive-image
            :diskName="$item->cover_image ?: null"
            :src="asset('assets/img/placeholder-1.svg')"
            :onerrorSrc="asset('assets/img/placeholder-1.svg')"
            :alt="$item->title"
            :sizes="'(max-width: 900px) 100vw, 33vw'"
        />
      </div>
      <div class="public-card__body">
        <h3>{{ $item->title }}</h3>
        <p>{{ Str::limit($item->excerpt, 90) }}</p>
      </div>
    </a>
    @endforeach
  </div>
</section>
@endif
