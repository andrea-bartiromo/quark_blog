{{--
  Indice generato automaticamente dai titoli h2/h3 dell'articolo
  ($tocItems, calcolato in articolo.blade.php da TableOfContentsService).
  Incluso due volte con varianti diverse (toc-panel--desktop nella colonna
  laterale, toc-panel--mobile in cima al contenuto principale): stessa
  struttura, il CSS ne mostra una sola per volta secondo il breakpoint.
--}}
@if(!empty($tocItems))
<div class="article-premium__panel toc-panel {{ $tocVariant }}">
  <nav class="toc-nav" aria-label="Indice articolo">
    <p class="toc-nav__title">Indice</p>
    <ul class="toc-nav__list">
      @foreach($tocItems as $item)
        <li>
          <a href="#{{ $item['id'] }}">{{ $item['text'] }}</a>
          @if(!empty($item['children']))
            <ul class="toc-nav__list toc-nav__list--nested">
              @foreach($item['children'] as $child)
                <li><a href="#{{ $child['id'] }}">{{ $child['text'] }}</a></li>
              @endforeach
            </ul>
          @endif
        </li>
      @endforeach
    </ul>
  </nav>
</div>
@endif
