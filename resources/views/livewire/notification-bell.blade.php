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
        <flux:button variant="ghost" size="sm" square class="relative" aria-label="Notifications">
            <flux:icon.bell variant="outline" class="size-5 text-zinc-500" />

            @if ($this->unreadCount > 0)
                <span class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] leading-none font-semibold text-white tabular-nums ring-2 ring-white">
                    {{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}
                </span>
            @endif
        </flux:button>

        <flux:menu class="w-80 overflow-hidden p-0!">
            <div class="flex items-center justify-between gap-3 border-b border-zinc-200 px-3.5 py-2.5">
                <span class="text-sm font-medium text-zinc-900">
                    {{ __('Notifications') }}
                    @if ($this->unreadCount > 0)
                        <span class="ms-1 text-xs font-normal text-zinc-500 tabular-nums">{{ $this->unreadCount }} {{ __('unread') }}</span>
                    @endif
                </span>

                @if ($this->unreadCount > 0)
                    <button type="button" wire:click="markAllAsRead" class="ui-link text-xs">
                        {{ __('Mark all as read') }}
                    </button>
                @endif
            </div>

            <div class="max-h-[360px] divide-y divide-zinc-100 overflow-y-auto">
                @forelse ($this->recentNotifications as $notification)
                    @php
                        $isUnread = $notification->read_at === null;
                    @endphp

                    <div
                        role="button"
                        tabindex="0"
                        x-on:click="$wire.markAsReadAndVisit(@js($notification->id), @js(data_get($notification->data, 'url')))"
                        x-on:keydown.enter="$wire.markAsReadAndVisit(@js($notification->id), @js(data_get($notification->data, 'url')))"
                        class="flex cursor-pointer items-start gap-3 px-3.5 py-3 transition-colors duration-150 hover:bg-zinc-50"
                    >
                        <div class="flex size-7 shrink-0 items-center justify-center rounded-md bg-zinc-100 text-zinc-500 ring-1 ring-zinc-200 ring-inset">
                            <flux:icon :icon="data_get($notification->data, 'icon', 'bell')" class="size-3.5" />
                        </div>

                        <div class="flex min-w-0 flex-1 flex-col gap-0.5 text-left">
                            <div class="flex items-center gap-2">
                                <p @class(['min-w-0 flex-1 truncate text-sm', 'font-semibold text-zinc-900' => $isUnread, 'font-medium text-zinc-700' => ! $isUnread])>
                                    {{ data_get($notification->data, 'title') }}
                                </p>
                                @if ($isUnread)
                                    <span class="size-1.5 shrink-0 rounded-[2px] bg-zinc-900"><span class="sr-only">{{ __('Unread') }}</span></span>
                                @endif
                            </div>

                            <p class="line-clamp-2 text-xs text-zinc-500">
                                {{ data_get($notification->data, 'body') }}
                            </p>

                            <span class="mt-0.5 text-xs text-zinc-500">
                                {{ $notification->created_at->diffForHumans() }}
                            </span>
                        </div>
                    </div>
                @empty
                    <x-empty-state compact icon="bell" :title="__('No notifications yet')" :description="__('Run results and agent updates will show up here.')" />
                @endforelse
            </div>

            <div class="border-t border-zinc-200 p-1">
                <flux:menu.item :href="route('notifications')" wire:navigate class="justify-center text-xs! font-medium">
                    {{ __('View all notifications') }}
                </flux:menu.item>
            </div>
        </flux:menu>
    </flux:dropdown>
</div>
