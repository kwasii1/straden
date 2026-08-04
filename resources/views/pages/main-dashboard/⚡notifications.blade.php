<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts::app')]
class extends Component
{
    use WithPagination;

    #[Computed]
    public function notifications()
    {
        return auth()->user()->notifications()->paginate(15);
    }

    #[Computed]
    public function unreadCount(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();

        unset($this->unreadCount, $this->notifications);
    }

    public function visit(string $id, ?string $url = null): void
    {
        auth()->user()->notifications()
            ->whereKey($id)
            ->first()
            ?->markAsRead();

        if ($url) {
            $this->redirect($url, navigate: true);
        }
    }

    public function delete(string $id): void
    {
        auth()->user()->notifications()
            ->whereKey($id)
            ->delete();

        unset($this->notifications);
    }
};
?>

<div class="flex flex-col gap-y-6">
    <div class="flex items-center justify-between gap-x-4">
        <div>
            <flux:heading size="xl">Notifications</flux:heading>
            <flux:text>Keep track of your test runs and AI agent tasks.</flux:text>
        </div>

        @if ($this->unreadCount > 0)
            <flux:button variant="primary" wire:click="markAllAsRead">
                Mark all as read
            </flux:button>
        @endif
    </div>

    <div class="flex flex-col gap-y-3">
        @forelse ($this->notifications as $notification)
            <div
                wire:key="{{ $notification->id }}"
                class="flex items-start gap-x-3 rounded-xl border p-4 transition {{ $notification->read_at === null ? 'border-sky-800 bg-sky-950/20' : 'border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900' }}"
            >
                <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-zinc-800">
                    <flux:icon :icon="data_get($notification->data, 'icon', 'bell')" class="size-5" />
                </span>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-x-2">
                        @if ($notification->read_at === null)
                            <span class="size-1.5 shrink-0 rounded-full bg-sky-500"></span>
                        @endif
                        <flux:heading size="sm">{{ data_get($notification->data, 'title') }}</flux:heading>
                    </div>
                    <flux:text class="mt-1">{{ data_get($notification->data, 'body') }}</flux:text>
                    <flux:text class="mt-1 text-xs">{{ $notification->created_at->diffForHumans() }}</flux:text>
                </div>

                <div class="flex shrink-0 items-center gap-x-1">
                    @if ($notification->read_at === null)
                        <flux:button
                            variant="subtle"
                            size="sm"
                            wire:click="visit('{{ $notification->id }}')"
                        >
                            View
                        </flux:button>
                    @endif

                    <flux:button
                        variant="subtle"
                        size="sm"
                        wire:click="delete('{{ $notification->id }}')"
                        wire:confirm="Delete this notification?"
                        icon="trash"
                        aria-label="Delete notification"
                    />
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 dark:border-zinc-600 py-16 gap-y-3">
                <flux:icon.bell class="size-12 text-zinc-300 dark:text-zinc-600" />
                <flux:text class="text-zinc-500 dark:text-zinc-400">No notifications yet.</flux:text>
            </div>
        @endforelse
    </div>

    <div>
        {{ $this->notifications->links() }}
    </div>
</div>
