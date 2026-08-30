@if ($paginator->hasPages())
<style>
  .pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    gap: .45rem;
    margin-top: 1.35rem;
  }

  .pagination__item {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.75rem;
    height: 2.75rem;
    padding: 0 .8rem;
    border: 1px solid var(--admin-border, #d1d5db);
    border-radius: .75rem;
    background: var(--admin-white, #fff);
    color: var(--admin-ink, #111827);
    font-family: var(--font-ui, system-ui, sans-serif);
    font-size: .9rem;
    font-weight: 700;
    line-height: 1;
    box-shadow: 0 1px 2px rgba(15, 23, 42, .06), 0 4px 14px rgba(15, 23, 42, .05);
    transition: background .18s ease, border-color .18s ease, color .18s ease, transform .18s ease, box-shadow .18s ease;
  }

  a.pagination__item:hover {
    border-color: var(--admin-primary, #0d9488);
    background: #f0fdfa;
    color: var(--admin-primary-dark, #0f766e);
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(15, 23, 42, .08), 0 7px 18px rgba(15, 23, 42, .08);
  }

  .pagination__item--current {
    border-color: var(--admin-primary, #0d9488);
    background: var(--admin-primary, #0d9488);
    color: #fff;
    box-shadow: 0 3px 10px rgba(13, 148, 136, .22);
  }

  .pagination__item--disabled {
    color: var(--admin-faint, #9ca3af);
    background: #f8fafc;
    box-shadow: none;
    cursor: default;
  }

  .pagination__item--arrow {
    font-size: 1.15rem;
  }

  .pagination__ellipsis {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.6rem;
    height: 2.75rem;
    color: var(--admin-muted, #6b7280);
    font-family: var(--font-ui, system-ui, sans-serif);
    font-size: .9rem;
    font-weight: 700;
  }

  .pagination__item:focus-visible {
    outline: 3px solid rgba(13, 148, 136, .28);
    outline-offset: 2px;
  }

  @media (max-width: 640px) {
    .pagination {
      gap: .35rem;
    }

    .pagination__item {
      min-width: 2.75rem;
      height: 2.75rem;
      padding: 0 .65rem;
    }
  }
</style>

<nav class="pagination" aria-label="{{ $ariaLabel ?? 'Paginazione articoli' }}" role="navigation">

  {{-- Precedente --}}
  @if ($paginator->onFirstPage())
    @if($showDisabled ?? true)
      <span class="pagination__item pagination__item--arrow pagination__item--disabled" aria-disabled="true">{{ $previousText ?? '←' }}</span>
    @endif
  @else
    <a class="pagination__item pagination__item--arrow" href="{{ $paginator->previousPageUrl() }}" aria-label="Pagina precedente">{{ $previousText ?? '←' }}</a>
  @endif

  {{-- Numeri pagina --}}
  @foreach ($elements as $element)
    @if (is_string($element))
      <span class="pagination__ellipsis" aria-hidden="true">…</span>
    @endif

    @if (is_array($element))
      @foreach ($element as $page => $url)
        @if ($page == $paginator->currentPage())
          <span class="pagination__item pagination__item--current" aria-current="page">{{ $page }}</span>
        @else
          <a class="pagination__item" href="{{ $url }}" aria-label="Vai a pagina {{ $page }}">{{ $page }}</a>
        @endif
      @endforeach
    @endif
  @endforeach

  {{-- Successiva --}}
  @if ($paginator->hasMorePages())
    <a class="pagination__item pagination__item--arrow" href="{{ $paginator->nextPageUrl() }}" aria-label="Pagina successiva">{{ $nextText ?? '→' }}</a>
  @else
    @if($showDisabled ?? true)
      <span class="pagination__item pagination__item--arrow pagination__item--disabled" aria-disabled="true">{{ $nextText ?? '→' }}</span>
    @endif
  @endif

</nav>
@endif
