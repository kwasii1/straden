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
        <flux:button variant="subtle" square class="relative" aria-label="Notifications">
            <flux:icon.bell class="size-5 text-zinc-600 dark:text-zinc-400" />

            @if ($this->unreadCount > 0)
                {{-- Inset badge positioning prevents layout overflow --}}
                <span class="absolute top-1 right-1 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white shadow-xs">
                    {{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}
                </span>
            @endif
        </flux:button>

        <flux:menu class="w-80 p-0 overflow-hidden rounded-xl border border-zinc-200/80 shadow-lg dark:border-zinc-800">
            {{-- Header --}}
            <div class="flex items-center justify-between px-4 py-3 bg-zinc-50/50 dark:bg-zinc-900/50 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-sm text-zinc-900 dark:text-white">{{ __('Notifications') }}</span>
                    @if ($this->unreadCount > 0)
                        <span class="px-2 py-0.5 text-[11px] font-medium rounded-full bg-zinc-200/60 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300">
                            {{ $this->unreadCount }}
                        </span>
                    @endif
                </div>

                @if ($this->unreadCount > 0)
                    <button
                        type="button"
                        wire:click="markAllAsRead"
                        class="text-xs font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 hover:underline transition"
                    >
                        {{ __('Mark all as read') }}
                    </button>
                @endif
            </div>

            {{-- Notifications List --}}
            <div class="max-h-[360px] [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-800/60">
                @forelse ($this->recentNotifications as $notification)
                    <div
                        role="button"
                        x-on:click="$wire.markAsReadAndVisit(@js($notification->id), @js(data_get($notification->data, 'url')))"
                        class="group relative flex items-start gap-3 p-3.5 transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50 cursor-pointer {{ $notification->read_at === null ? 'bg-indigo-50/30 dark:bg-indigo-950/10' : '' }}"
                    >
                        {{-- Icon Container --}}
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                            <flux:icon :icon="data_get($notification->data, 'icon', 'bell')" class="size-4" />
                        </div>

                        {{-- Content --}}
                        <div class="flex min-w-0 flex-1 flex-col gap-0.5 text-left">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs font-medium truncate text-zinc-900 dark:text-zinc-100">
                                    {{ data_get($notification->data, 'title') }}
                                </p>
                                @if ($notification->read_at === null)
                                    <span class="size-2 shrink-0 rounded-full bg-indigo-600 dark:bg-indigo-400"></span>
                                @endif
                            </div>

                            <p class="line-clamp-2 text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed">
                                {{ data_get($notification->data, 'body') }}
                            </p>

                            <span class="mt-1 text-[10px] font-medium text-zinc-400 dark:text-zinc-500">
                                {{ $notification->created_at->diffForHumans() }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center px-4 py-8 text-center">
                        <div class="flex size-10 items-center justify-center rounded-full bg-zinc-100 text-zinc-400 dark:bg-zinc-800">
                            <flux:icon.bell class="size-5" />
                        </div>
                        <p class="mt-2 text-xs font-medium text-zinc-600 dark:text-zinc-400">
                            {{ __('No notifications yet') }}
                        </p>
                        <p class="text-[11px] text-zinc-400 dark:text-zinc-500">
                            {{ __('We will notify you when something comes up.') }}
                        </p>
                    </div>
                @endforelse
            </div>

            {{-- Footer --}}
            <div class="border-t border-zinc-100 dark:border-zinc-800 p-1.5 bg-zinc-50/50 dark:bg-zinc-900/50">
                <flux:menu.item :href="route('notifications')" icon="list-bullet" wire:navigate class="justify-center !text-xs font-medium">
                    {{ __('View all notifications') }}
                </flux:menu.item>
            </div>
        </flux:menu>
    </flux:dropdown>
</div>
