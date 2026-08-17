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
    // Passato dal controller (ArticleController::show()) e già usato anche
    // da articolo.blade.php e structured-data.blade.php: prima ciascuno dei
    // tre rieseguiva la stessa query "select name, slug from categories".
    $breadcrumbCategoryOptions = $categoryOptions;
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
