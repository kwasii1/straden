<div
    x-data="{
        init() {
            if (! window.__notificationBellBound) {
                window.__notificationBellBound = true;

                Echo.private('App.Models.User.{{ auth()->id() }}').listen('.NotificationSent', () => {
                    window.dispatchEvent(new CustomEvent('notification-received'));
                });
            }

            this.onNotificationReceived = () => $wire.$refresh();
            window.addEventListener('notification-received', this.onNotificationReceived);
        },
        destroy() {
            window.removeEventListener('notification-received', this.onNotificationReceived);
        },
    }"
>
    <flux:dropdown position="bottom" align="end">
        <flux:button variant="subtle" square class="relative group" aria-label="Notifications">
            <flux:icon.bell class="size-5" />

            @if ($this->unreadCount > 0)
                <span class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold leading-none text-white">
                    {{ min($this->unreadCount, 99) }}
                </span>
            @endif
        </flux:button>

        <flux:menu class="w-80">
            <div class="flex items-center justify-between gap-x-2 px-2 py-1.5">
                <flux:heading size="sm">{{ __('Notifications') }}</flux:heading>

                @if ($this->unreadCount > 0)
                    <flux:menu.item as="button" class="!text-xs !font-normal text-zinc-500" wire:click="markAllAsRead">
                        {{ __('Mark all as read') }}
                    </flux:menu.item>
                @endif
            </div>

            <flux:menu.separator />

            @forelse ($this->recentNotifications as $notification)
                <flux:menu.item
                    as="button"
                    class="!py-2"
                    x-on:click="$wire.markAsReadAndVisit(@js($notification->id), @js(data_get($notification->data, 'url')))"
                >
                    <div class="flex items-start gap-2.5 min-w-0">
                        <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center text-zinc-500">
                            <flux:icon :icon="data_get($notification->data, 'icon', 'bell')" class="size-5" />
                        </span>

                        <span class="flex min-w-0 flex-col gap-0.5 text-start">
                            <span class="flex items-center gap-1.5">
                                @if ($notification->read_at === null)
                                    <span class="size-1.5 shrink-0 rounded-full bg-sky-500"></span>
                                @endif
                                <span class="truncate">{{ data_get($notification->data, 'title') }}</span>
                            </span>
                            <span class="line-clamp-2 text-xs font-normal text-zinc-500">{{ data_get($notification->data, 'body') }}</span>
                            <span class="text-xs font-normal text-zinc-500">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                    </div>
                </flux:menu.item>
            @empty
                <div class="px-4 py-8 text-center text-sm text-zinc-500">
                    {{ __('No notifications yet.') }}
                </div>
            @endforelse

            <flux:menu.separator />

            <flux:menu.item :href="route('notifications')" icon="list-bullet" wire:navigate>
                {{ __('View all notifications') }}
            </flux:menu.item>
        </flux:menu>
    </flux:dropdown>
</div>
