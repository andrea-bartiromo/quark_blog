{{-- Barra categorie Kairus --}}
<nav class="category-bar" aria-label="Categorie">
  <div class="container">
    <a href="{{ route('notizie') }}"
       @class(['active' => request()->routeIs('notizie') && !request()->route('slug')])>
      Tutti
    </a>
    @foreach($categoryOptions as $slug => $label)
      <a href="{{ route('categoria', $slug) }}"
         @class(['active' => request()->is("categoria/{$slug}")])>
        {{ $label }}
      </a>
    @endforeach
  </div>
</nav>
