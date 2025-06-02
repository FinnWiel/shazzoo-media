@if ($paginator->hasPages())
    <nav class="flex items-center justify-center gap-1 mt-4">
        {{-- Previous Page Link
        @if ($paginator->onFirstPage())
            <span class="px-2 py-1 text-gray-400">&laquo;</span>
        @else
            <button wire:click="previousPage"
                class="px-3 py-1 rounded bg-white dark:bg-zinc-800 hover:bg-gray-100 dark:hover:bg-zinc-700">
                &laquo;
            </button>
        @endif --}}

        @php
            $current = $paginator->currentPage();
            $last = $paginator->lastPage();
        @endphp

        {{-- First Page --}}
        @if ($current > 2)
            <button wire:click="gotoPage(1)"
                class="px-3 py-1 rounded hover:bg-gray-100 dark:hover:bg-zinc-700">1</button>
        @endif

        {{-- Dots before current --}}
        @if ($current > 3)
            <span class="px-2 text-gray-400">…</span>
        @endif

        {{-- Pages Around Current --}}
        @for ($i = max(1, $current - 1); $i <= min($last, $current + 1); $i++)
            @if ($i === $current)
                <span class="px-3 py-1 rounded bg-primary-600 text-white font-bold">{{ $i }}</span>
            @else
                <button wire:click="gotoPage({{ $i }})"
                    class="px-3 py-1 rounded hover:bg-gray-100 dark:hover:bg-zinc-700">
                    {{ $i }}
                </button>
            @endif
        @endfor

        {{-- Dots after current --}}
        @if ($current < $last - 2)
            <span class="px-2 text-gray-400">…</span>
        @endif

        {{-- Last Page --}}
        @if ($current < $last - 1)
            <button wire:click="gotoPage({{ $last }})"
                class="px-3 py-1 rounded hover:bg-gray-100 dark:hover:bg-zinc-700">{{ $last }}</button>
        @endif

        {{-- Next Page Link
        @if ($paginator->hasMorePages())
            <button wire:click="nextPage"
                class="px-3 py-1 rounded bg-white dark:bg-zinc-800 hover:bg-gray-100 dark:hover:bg-zinc-700">
                &raquo;
            </button>
        @else
            <span class="px-2 py-1 text-gray-400">&raquo;</span>
        @endif --}}
    </nav>
@endif
