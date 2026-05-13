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
        <img src="{{ asset('assets/img/'.($item->cover_image ?? 'placeholder-1.svg')) }}" alt="{{ $item->title }}" loading="lazy">
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
