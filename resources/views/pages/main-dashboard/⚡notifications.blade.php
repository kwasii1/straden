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

<div class="flex flex-col gap-8">
    <x-page-header title="Notifications" description="Test runs, AI agent tasks and workspace activity.">
        <x-slot:breadcrumbs>
            <button
                type="button"
                class="inline-flex items-center gap-1 text-zinc-500 hover:text-zinc-900"
                x-on:click="history.length > 1 ? history.back() : Livewire.navigate('{{ route('dashboard') }}')"
            >
                <flux:icon.arrow-left variant="micro" class="text-zinc-400" />
                Back
            </button>
        </x-slot:breadcrumbs>

        @if ($this->unreadCount > 0)
            <x-slot:actions>
                <flux:button size="sm" icon="check" wire:click="markAllAsRead">Mark all as read</flux:button>
            </x-slot:actions>
        @endif
    </x-page-header>

    <section class="ui-panel overflow-hidden">
        <header class="ui-panel-header">
            <div class="flex items-center gap-1" role="tablist" aria-label="Filter notifications">
                @foreach (['all' => ['All', $this->totalCount], 'unread' => ['Unread', $this->unreadCount]] as $key => [$label, $count])
                    <button
                        type="button"
                        role="tab"
                        aria-selected="{{ $filter === $key ? 'true' : 'false' }}"
                        wire:click="setFilter('{{ $key }}')"
                        @class([
                            'inline-flex h-7 items-center gap-1.5 rounded-md px-2.5 text-xs font-medium transition-colors duration-150',
                            'bg-zinc-900 text-white' => $filter === $key,
                            'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900' => $filter !== $key,
                        ])
                    >
                        {{ $label }}
                        <span @class(['tabular-nums', 'text-white/60' => $filter === $key, 'text-zinc-400' => $filter !== $key])>{{ $count }}</span>
                    </button>
                @endforeach
            </div>
        </header>

        @if ($this->notifications->isEmpty())
            @if ($filter === 'unread')
                <x-empty-state icon="bell" title="No unread notifications" description="You're all caught up. Switch to All to see earlier notifications.">
                    <flux:button variant="ghost" size="sm" wire:click="setFilter('all')">View all notifications</flux:button>
                </x-empty-state>
            @else
                <x-empty-state icon="bell" title="No notifications yet" description="Run results, agent tasks and alerts will show up here." />
            @endif
        @else
            <div class="ui-list">
                @foreach ($this->notifications as $notification)
                    @php
                        $isUnread = $notification->read_at === null;
                        $url = data_get($notification->data, 'url');
                    @endphp

                    <div wire:key="{{ $notification->id }}" class="ui-list-row group items-start">
                        <span @class(['mt-3.5 size-1.5 shrink-0 rounded-[2px]', 'bg-zinc-900' => $isUnread, 'bg-transparent' => ! $isUnread])>
                            @if ($isUnread)
                                <span class="sr-only">Unread</span>
                            @endif
                        </span>

                        <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 ring-1 ring-zinc-200 ring-inset">
                            <flux:icon :icon="data_get($notification->data, 'icon', 'bell')" class="size-4" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <p @class(['truncate text-sm', 'font-semibold text-zinc-900' => $isUnread, 'font-medium text-zinc-700' => ! $isUnread])>
                                {{ data_get($notification->data, 'title') }}
                            </p>

                            @if (data_get($notification->data, 'body'))
                                <p @class(['mt-0.5 text-sm', 'text-zinc-700' => $isUnread, 'text-zinc-500' => ! $isUnread])>
                                    {{ data_get($notification->data, 'body') }}
                                </p>
                            @endif

                            <p class="mt-1 text-xs text-zinc-500">
                                <time datetime="{{ $notification->created_at->toIso8601String() }}" title="{{ $notification->created_at->toDayDateTimeString() }}">
                                    {{ $notification->created_at->diffForHumans() }}
                                </time>
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-0.5 transition-opacity duration-150 sm:opacity-0 sm:group-focus-within:opacity-100 sm:group-hover:opacity-100">
                            @if ($url)
                                <flux:button variant="ghost" size="sm" wire:click="visit('{{ $notification->id }}', @js($url))">
                                    Open
                                </flux:button>
                            @endif

                            <flux:button
                                variant="ghost"
                                size="sm"
                                square
                                wire:click="toggleRead('{{ $notification->id }}')"
                                :icon="$isUnread ? 'check' : 'envelope'"
                                icon:variant="micro"
                                aria-label="{{ $isUnread ? 'Mark as read' : 'Mark as unread' }}"
                                tooltip="{{ $isUnread ? 'Mark as read' : 'Mark as unread' }}"
                            />

                            <flux:button
                                variant="ghost"
                                size="sm"
                                square
                                wire:click="delete('{{ $notification->id }}')"
                                wire:confirm="Are you sure you want to delete this notification?"
                                icon="trash"
                                icon:variant="micro"
                                aria-label="Delete notification"
                                tooltip="Delete notification"
                            />
                        </div>
                    </div>
                @endforeach
            </div>

            <x-pagination :paginator="$this->notifications" class="ui-panel-footer" />
        @endif
    </section>
</div>
