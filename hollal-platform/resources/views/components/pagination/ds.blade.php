@if ($paginator->hasPages())
    <nav class="ds-pagination" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="ds-btn ds-btn-sm ds-btn-outline" aria-disabled="true">السابق</span>
        @else
            <button type="button" class="ds-btn ds-btn-sm ds-btn-outline" wire:click="previousPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled">السابق</button>
        @endif

        <span class="ds-pagination-info">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <button type="button" class="ds-btn ds-btn-sm ds-btn-outline" wire:click="nextPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled">التالي</button>
        @else
            <span class="ds-btn ds-btn-sm ds-btn-outline" aria-disabled="true">التالي</span>
        @endif
    </nav>
@endif
