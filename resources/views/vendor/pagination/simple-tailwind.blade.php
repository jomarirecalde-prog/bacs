@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between gap-2">
        @if ($paginator->onFirstPage())
            <span class="btn-outline btn-sm cursor-not-allowed opacity-50">{!! __('pagination.previous') !!}</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn-outline btn-sm">{!! __('pagination.previous') !!}</a>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn-outline btn-sm">{!! __('pagination.next') !!}</a>
        @else
            <span class="btn-outline btn-sm cursor-not-allowed opacity-50">{!! __('pagination.next') !!}</span>
        @endif
    </nav>
@endif
