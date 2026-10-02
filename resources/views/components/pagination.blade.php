@props([
    'paginator',
])

@if ($paginator->hasPages())
    <div {{ $attributes->class('flex items-center justify-between gap-3') }}>
        <p class="text-xs text-zinc-500 tabular-nums">
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}
        </p>

        <nav class="flex items-center gap-0.5" aria-label="Pagination">
            <button type="button" class="ui-icon-button" wire:click="previousPage" @disabled($paginator->onFirstPage()) aria-label="Previous page">
                <flux:icon.chevron-left variant="micro" />
            </button>

            @foreach ($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
                @if ($page == $paginator->currentPage())
                    <span class="inline-flex h-7 min-w-7 items-center justify-center rounded-md bg-zinc-900 px-2 text-xs font-medium text-white tabular-nums" aria-current="page">{{ $page }}</span>
                @else
                    <button type="button" wire:click="gotoPage({{ $page }})" class="ui-icon-button w-auto min-w-7 px-2 text-xs tabular-nums">{{ $page }}</button>
                @endif
            @endforeach

            <button type="button" class="ui-icon-button" wire:click="nextPage" @disabled(! $paginator->hasMorePages()) aria-label="Next page">
                <flux:icon.chevron-right variant="micro" />
            </button>
        </nav>
    </div>
@endif
