@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        {{-- Mobile --}}
        <div class="flex items-center justify-between gap-2 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="btn-outline btn-sm cursor-not-allowed opacity-50">{!! __('pagination.previous') !!}</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn-outline btn-sm">{!! __('pagination.previous') !!}</a>
            @endif

            <span class="text-xs font-semibold text-muted tabular-nums">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn-outline btn-sm">{!! __('pagination.next') !!}</a>
            @else
                <span class="btn-outline btn-sm cursor-not-allowed opacity-50">{!! __('pagination.next') !!}</span>
            @endif
        </div>

        {{-- Desktop --}}
        <p class="hidden text-xs text-muted sm:block">
            {!! __('Showing') !!}
            @if ($paginator->firstItem())
                <span class="font-bold text-ink tabular-nums">{{ $paginator->firstItem() }}</span>
                {!! __('to') !!}
                <span class="font-bold text-ink tabular-nums">{{ $paginator->lastItem() }}</span>
            @else
                <span class="font-bold text-ink tabular-nums">{{ $paginator->count() }}</span>
            @endif
            {!! __('of') !!}
            <span class="font-bold text-ink tabular-nums">{{ $paginator->total() }}</span>
            {!! __('results') !!}
        </p>

        <div class="hidden items-center gap-1 sm:flex">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}"
                      class="inline-flex h-9 w-9 cursor-not-allowed items-center justify-center rounded-lg border border-line bg-surface text-faint">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}"
                   class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-line bg-surface text-muted transition hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span aria-disabled="true" class="inline-flex h-9 min-w-9 items-center justify-center px-1 text-sm font-semibold text-faint">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page"
                                  class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg bg-brand-600 px-2.5 text-sm font-bold text-white shadow-soft tabular-nums">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                               class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-line bg-surface px-2.5 text-sm font-semibold text-ink-soft transition tabular-nums hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}"
                   class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-line bg-surface text-muted transition hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            @else
                <span aria-disabled="true" aria-label="{{ __('pagination.next') }}"
                      class="inline-flex h-9 w-9 cursor-not-allowed items-center justify-center rounded-lg border border-line bg-surface text-faint">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </span>
            @endif
        </div>
    </nav>
@endif
