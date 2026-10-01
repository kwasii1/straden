<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts::app')]
class extends Component
{
    use WithPagination;

    #[Url]
    public string $filter = 'all';

    #[Computed]
    public function notifications()
    {
        return auth()->user()->notifications()
            ->when($this->filter === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->paginate(15);
    }

    #[Computed]
    public function unreadCount(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    #[Computed]
    public function totalCount(): int
    {
        return auth()->user()->notifications()->count();
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->resetPage();
    }

    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();

        unset($this->unreadCount, $this->notifications);
    }

    public function toggleRead(string $id): void
    {
        $notification = auth()->user()->notifications()->whereKey($id)->first();

        if ($notification) {
            if ($notification->read_at) {
                $notification->update(['read_at' => null]);
            } else {
                $notification->markAsRead();
            }
        }

        unset($this->unreadCount, $this->notifications);
    }

    public function visit(string $id, ?string $url = null): void
    {
        $notification = auth()->user()->notifications()->whereKey($id)->first();
        $notification?->markAsRead();

        if ($url) {
            $this->redirect($url, navigate: true);
        } else {
            unset($this->unreadCount, $this->notifications);
        }
    }

    public function delete(string $id): void
    {
        auth()->user()->notifications()
            ->whereKey($id)
            ->delete();

        unset($this->totalCount, $this->unreadCount, $this->notifications);
    }
};
?>

<div class="mx-auto max-w-4xl space-y-6">
    <flux:button variant="subtle" size="sm" icon="arrow-left" x-on:click="history.length > 1 ? history.back() : Livewire.navigate('{{ route('dashboard') }}')">
        Back
    </flux:button>

    {{-- Page Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-3">
                <flux:heading size="xl">Notifications</flux:heading>
                @if ($this->unreadCount > 0)
                    <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">
                        {{ $this->unreadCount }} new
                    </span>
                @endif
            </div>
            <flux:text class="mt-1">Keep track of your test runs, AI agent tasks, and workspace activity.</flux:text>
        </div>

        @if ($this->unreadCount > 0)
            <flux:button
                variant="subtle"
                icon="check-circle"
                wire:click="markAllAsRead"
                class="self-start sm:self-auto"
            >
                Mark all as read
            </flux:button>
        @endif
    </div>

    {{-- Filter Navigation Tabs --}}
    <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-800 pb-2">
        <div class="flex gap-2">
            <button
                type="button"
                wire:click="setFilter('all')"
                class="relative px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $filter === 'all' ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' }}"
            >
                All
                <span class="ml-1 opacity-60">({{ $this->totalCount }})</span>
            </button>

            <button
                type="button"
                wire:click="setFilter('unread')"
                class="relative px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $filter === 'unread' ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' }}"
            >
                Unread
                @if ($this->unreadCount > 0)
                    <span class="ml-1 rounded-full bg-indigo-500/20 px-1.5 py-0.2 text-[10px] text-indigo-600 dark:text-indigo-300">
                        {{ $this->unreadCount }}
                    </span>
                @endif
            </button>
        </div>
    </div>

    {{-- Notification Item List --}}
    <div class="space-y-3">
        @forelse ($this->notifications as $notification)
            @php
                $isUnread = $notification->read_at === null;
                $url = data_get($notification->data, 'url');
            @endphp

            <div
                wire:key="{{ $notification->id }}"
                class="group relative flex items-start justify-between gap-x-4 rounded-xl border p-4 transition-all hover:shadow-xs {{ $isUnread ? 'border-indigo-200 bg-indigo-50/20 dark:border-indigo-900/50 dark:bg-indigo-950/10' : 'border-zinc-200/80 bg-white dark:border-zinc-800 dark:bg-zinc-900/60' }}"
            >
                {{-- Left Accent Indicator for Unread --}}
                @if ($isUnread)
                    <div class="absolute left-0 top-3 bottom-3 w-1 rounded-r-full bg-indigo-600 dark:bg-indigo-400"></div>
                @endif

                <div class="flex items-start gap-x-3.5 min-w-0 pl-1">
                    {{-- Icon Container --}}
                    <div class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg border border-zinc-200/60 bg-zinc-50 text-zinc-600 shadow-2xs dark:border-zinc-700/60 dark:bg-zinc-800 dark:text-zinc-300">
                        <flux:icon :icon="data_get($notification->data, 'icon', 'bell')" class="size-4" />
                    </div>

                    {{-- Main Content --}}
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-sm text-zinc-900 dark:text-zinc-100 truncate">
                                {{ data_get($notification->data, 'title') }}
                            </span>
                            @if ($isUnread)
                                <span class="size-1.5 shrink-0 rounded-full bg-indigo-600 dark:bg-indigo-400"></span>
                            @endif
                        </div>

                        <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">
                            {{ data_get($notification->data, 'body') }}
                        </p>

                        <div class="mt-2 flex items-center gap-3 text-[11px] font-medium text-zinc-400 dark:text-zinc-500">
                            <span>{{ $notification->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                </div>

                {{-- Action Toolbar --}}
                <div class="flex shrink-0 items-center gap-x-1 opacity-90 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                    @if ($url)
                        <flux:button
                            variant="subtle"
                            size="sm"
                            wire:click="visit('{{ $notification->id }}', @js($url))"
                            icon-trailing="arrow-up-right"
                        >
                            Visit
                        </flux:button>
                    @endif

                    <flux:button
                        variant="subtle"
                        size="sm"
                        wire:click="toggleRead('{{ $notification->id }}')"
                        :icon="$isUnread ? 'check' : 'envelope'"
                        aria-label="{{ $isUnread ? 'Mark as read' : 'Mark as unread' }}"
                        tooltip="{{ $isUnread ? 'Mark as read' : 'Mark as unread' }}"
                    />

                    <flux:button
                        variant="subtle"
                        size="sm"
                        wire:click="delete('{{ $notification->id }}')"
                        wire:confirm="Are you sure you want to delete this notification?"
                        icon="trash"
                        aria-label="Delete notification"
                        tooltip="Delete notification"
                    />
                </div>
            </div>
        @empty
            {{-- Modern Empty State --}}
            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-200 dark:border-zinc-800 p-12 text-center">
                <div class="flex size-12 items-center justify-center rounded-full bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500">
                    <flux:icon.bell class="size-6" />
                </div>
                <h3 class="mt-3 text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                    {{ $filter === 'unread' ? 'No unread notifications' : 'No notifications yet' }}
                </h3>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400 max-w-sm">
                    {{ $filter === 'unread' ? "You're all caught up! Switch tabs to see previous notifications." : "When you receive new updates, alerts, or tasks, they will show up here." }}
                </p>
                @if ($filter === 'unread')
                    <flux:button variant="subtle" size="sm" class="mt-4" wire:click="setFilter('all')">
                        View all notifications
                    </flux:button>
                @endif
            </div>
        @endforelse
    </div>

    {{-- Pagination Links --}}
    @if ($this->notifications->hasPages())
        <div class="pt-2">
            {{ $this->notifications->links() }}
        </div>
    @endif
</div>
