@if($paginator->hasPages())
  <nav class="detail-pagination" aria-label="Paginación de {{ $label ?? 'resultados' }}">
    <div class="detail-pagination-summary">
      Mostrando {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} de {{ $paginator->total() }}
    </div>

    <div class="detail-pagination-links">
      @if($paginator->onFirstPage())
        <span class="detail-page-link is-disabled">Anterior</span>
      @else
        <a class="detail-page-link" href="{{ $paginator->previousPageUrl() }}">Anterior</a>
      @endif

      <span class="detail-page-link is-active">{{ $paginator->currentPage() }}</span>

      @if($paginator->hasMorePages())
        <a class="detail-page-link" href="{{ $paginator->nextPageUrl() }}">Siguiente</a>
      @else
        <span class="detail-page-link is-disabled">Siguiente</span>
      @endif
    </div>
  </nav>
@endif
