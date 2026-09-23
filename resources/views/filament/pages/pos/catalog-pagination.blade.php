@if ($paginator->hasPages())
    <div class="px-5 py-3 bg-white border-t border-gray-200 flex items-center justify-between shrink-0 shadow-2xs z-10 transition-all">
        {{-- Results Counter & Loading State --}}
        <div class="flex items-center gap-3">
            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-gray-100/90 text-gray-700 border border-gray-200/80 shadow-2xs">
                <i class="ph ph-cube text-primary-600 text-sm"></i>
                <span>
                    {{ __('pos.showing') }}
                    <strong class="text-gray-900 font-extrabold">{{ $paginator->firstItem() ?? 0 }}</strong>
                    {{ __('pos.to') }}
                    <strong class="text-gray-900 font-extrabold">{{ $paginator->lastItem() ?? 0 }}</strong>
                    {{ __('pos.of') }}
                    <strong class="text-primary-600 font-extrabold">{{ $paginator->total() }}</strong>
                    {{ __('pos.products') }}
                </span>
            </div>

            <div wire:loading wire:target="previousPage, nextPage, gotoPage" class="inline-flex items-center gap-1.5 text-xs font-bold text-primary-600 bg-primary-50 px-2.5 py-1.5 rounded-xl border border-primary-200 animate-pulse">
                <i class="ph ph-spinner animate-spin text-sm"></i>
                <span>{{ __('pos.loading_products') }}</span>
            </div>
        </div>

        {{-- Navigation Controls --}}
        <nav role="navigation" aria-label="Catalog Pagination Navigation" class="flex items-center gap-1.5">
            {{-- Previous Page Button --}}
            @if ($paginator->onFirstPage())
                <span class="h-9 px-3 flex items-center gap-1 text-xs font-bold text-gray-400 bg-gray-50 border border-gray-200/70 rounded-xl cursor-not-allowed select-none opacity-60">
                    <i class="ph ph-caret-left rtl:rotate-180 text-sm"></i>
                    <span class="hidden sm:inline">{{ __('pos.previous') }}</span>
                </span>
            @else
                <button type="button"
                        wire:click="previousPage('{{ $paginator->getPageName() }}')"
                        wire:loading.attr="disabled"
                        class="h-9 px-3 flex items-center gap-1 text-xs font-bold text-gray-700 bg-white hover:bg-primary-50 hover:text-primary-600 hover:border-primary-300 border border-gray-200 rounded-xl shadow-2xs transition-all active:scale-95 cursor-pointer disabled:opacity-50">
                    <i class="ph ph-caret-left rtl:rotate-180 text-sm"></i>
                    <span class="hidden sm:inline">{{ __('pos.previous') }}</span>
                </button>
            @endif

            {{-- Page Number Elements --}}
            @foreach ($elements as $element)
                {{-- "Three Dots" Separator --}}
                @if (is_string($element))
                    <span class="h-9 min-w-[28px] flex items-center justify-center text-xs font-bold text-gray-400 select-none">
                        {{ $element }}
                    </span>
                @endif

                {{-- Array Of Links --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <span wire:key="catalog-paginator-page-{{ $page }}">
                            @if ($page == $paginator->currentPage())
                                <span class="h-9 min-w-[36px] px-2.5 flex items-center justify-center text-xs font-black text-white bg-primary-600 rounded-xl shadow-xs ring-2 ring-primary-500/20 select-none">
                                    {{ $page }}
                                </span>
                            @else
                                <button type="button"
                                        wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                        wire:loading.attr="disabled"
                                        class="h-9 min-w-[36px] px-2.5 flex items-center justify-center text-xs font-bold text-gray-700 bg-white hover:bg-gray-100 hover:text-primary-600 border border-gray-200 rounded-xl shadow-2xs transition-all active:scale-95 cursor-pointer disabled:opacity-50">
                                    {{ $page }}
                                </button>
                            @endif
                        </span>
                    @endforeach
                @endif
            @endforeach

            {{-- Next Page Button --}}
            @if ($paginator->hasMorePages())
                <button type="button"
                        wire:click="nextPage('{{ $paginator->getPageName() }}')"
                        wire:loading.attr="disabled"
                        class="h-9 px-3 flex items-center gap-1 text-xs font-bold text-gray-700 bg-white hover:bg-primary-50 hover:text-primary-600 hover:border-primary-300 border border-gray-200 rounded-xl shadow-2xs transition-all active:scale-95 cursor-pointer disabled:opacity-50">
                    <span class="hidden sm:inline">{{ __('pos.next') }}</span>
                    <i class="ph ph-caret-right rtl:rotate-180 text-sm"></i>
                </button>
            @else
                <span class="h-9 px-3 flex items-center gap-1 text-xs font-bold text-gray-400 bg-gray-50 border border-gray-200/70 rounded-xl cursor-not-allowed select-none opacity-60">
                    <span class="hidden sm:inline">{{ __('pos.next') }}</span>
                    <i class="ph ph-caret-right rtl:rotate-180 text-sm"></i>
                </span>
            @endif
        </nav>
    </div>
@elseif ($paginator->total() > 0)
    <div class="px-5 py-2.5 bg-white border-t border-gray-200 flex items-center justify-between shrink-0 shadow-2xs z-10 text-xs font-semibold text-gray-500">
        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-gray-50 border border-gray-200 text-gray-600">
            <i class="ph ph-cube text-primary-600 text-sm"></i>
            <span>
                {{ __('pos.showing') }}
                <strong class="text-gray-900 font-extrabold">{{ $paginator->total() }}</strong>
                {{ __('pos.products') }}
            </span>
        </div>
    </div>
@endif
