{{--
    Cantiere 2 (programma 100-cantieri Kairus): chip "Argomenti" estratti da
    notizie.blade.php e categoria.blade.php in un componente unico, per non
    mantenere due copie divergenti dello stesso markup. Stesso CSS
    `.public-pill-row` di prima (public-premium.css), non duplicato.

    $options: mappa slug => etichetta di categorie GENUINAMENTE pubbliche
    (Category::publicOptions() — mai la lista che include bozza/programmata/
    disattivata), passata dal chiamante così il componente non esegue query
    proprie e il budget query di ogni pagina resta invariato.

    $current: slug della categoria attualmente in pagina, o null quando il
    chiamante è la pagina "Tutti" (notizie.blade.php) — in quel caso è
    "Tutti" ad avere class="active" + aria-current="page", non un'altra voce.

    aria-label "Filtra per argomento" (non "Argomenti"): sia notizie.blade.php
    che categoria.blade.php includono anche components/sidebar.blade.php, il
    cui topic-cloud è già un <nav aria-label="Argomenti"> distinto — due
    landmark nav con lo stesso nome accessibile sulla stessa pagina sarebbero
    indistinguibili per chi naviga con tecnologie assistive (finding Codex
    P2 su PR #553).
--}}
@props([
    'options' => [],
    'current' => null,
])

<nav class="public-pill-row" aria-label="Filtra per argomento">
  @if($current === null)
  <a href="{{ route('notizie') }}" class="active" aria-current="page">Tutti</a>
  @else
  <a href="{{ route('notizie') }}">Tutti</a>
  @endif

  @foreach($options as $optionSlug => $optionLabel)
    @if($optionSlug === $current)
    <a href="{{ route('categoria', $optionSlug) }}" class="active" aria-current="page">{{ $optionLabel }}</a>
    @else
    <a href="{{ route('categoria', $optionSlug) }}">{{ $optionLabel }}</a>
    @endif
  @endforeach
</nav>
