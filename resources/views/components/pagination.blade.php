@if ($paginator->hasPages())
<nav class="pagination" aria-label="Paginazione articoli" role="navigation">

  {{-- Precedente --}}
  @if ($paginator->onFirstPage())
    <span style="opacity:.35;cursor:default;" aria-disabled="true">←</span>
  @else
    <a href="{{ $paginator->previousPageUrl() }}" aria-label="Pagina precedente">←</a>
  @endif

  {{-- Numeri pagina --}}
  @foreach ($elements as $element)
    @if (is_string($element))
      <span style="display:flex;align-items:center;font-family:var(--font-ui);font-size:.82rem;
                   padding:0 .4rem;color:var(--color-ink-muted);">…</span>
    @endif

    @if (is_array($element))
      @foreach ($element as $page => $url)
        @if ($page == $paginator->currentPage())
          <span class="current" aria-current="page">{{ $page }}</span>
        @else
          <a href="{{ $url }}">{{ $page }}</a>
        @endif
      @endforeach
    @endif
  @endforeach

  {{-- Successiva --}}
  @if ($paginator->hasMorePages())
    <a href="{{ $paginator->nextPageUrl() }}" aria-label="Pagina successiva">→</a>
  @else
    <span style="opacity:.35;cursor:default;" aria-disabled="true">→</span>
  @endif

</nav>
@endif
