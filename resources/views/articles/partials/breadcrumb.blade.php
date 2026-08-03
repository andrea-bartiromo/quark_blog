{{--
    Breadcrumb visibile per la pagina articolo. Companion del BreadcrumbList
    JSON-LD già presente in articles/partials/structured-data.blade.php:
    stesse fonti (Category::options(false), route('categoria', ...),
    $article->title, $article->metaCanonicalUrl() per il canonical implicito
    nell'ultima voce) e stesso fallback quando la categoria non risolve.

    Home usa qui route('home') (equivalente a url('/') usato nel JSON-LD,
    stessa destinazione) per idiomaticità nel markup Blade lato vista.

    Titolo editoriale mostrato per intero, nessun troncamento lato PHP:
    eventuali titoli lunghi vengono gestiti solo via wrapping CSS.
--}}
@php
    $breadcrumbCategoryOptions = \App\Models\Category::options(false);
    $breadcrumbCategoryRecognized = array_key_exists($article->category, $breadcrumbCategoryOptions);
@endphp
<nav class="article-premium__breadcrumb" aria-label="Percorso di navigazione">
  <ol>
    <li><a href="{{ route('home') }}">Home</a></li>

    @if($breadcrumbCategoryRecognized)
    <li>
      <a href="{{ route('categoria', $article->category) }}">
        {{ $breadcrumbCategoryOptions[$article->category] }}
      </a>
    </li>
    @endif

    <li><span aria-current="page">{{ $article->title }}</span></li>
  </ol>
</nav>
