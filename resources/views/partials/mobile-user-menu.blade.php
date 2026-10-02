<flux:dropdown position="bottom" align="end" class="lg:hidden">
    <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

    <flux:menu class="min-w-56">
        <div class="flex items-center gap-2.5 px-2 py-1.5 text-start text-sm">
            <flux:avatar size="sm" :name="auth()->user()->name" :initials="auth()->user()->initials()" />
            <div class="grid min-w-0 flex-1 leading-tight">
                <span class="truncate font-medium text-zinc-900">{{ auth()->user()->name }}</span>
                <span class="truncate text-xs text-zinc-500">{{ auth()->user()->email }}</span>
            </div>
        </div>

        <flux:menu.separator />

        <flux:menu.item :href="route('profile.edit')" icon="cog-6-tooth" wire:navigate>
            {{ __('Settings') }}
        </flux:menu.item>

        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">
                {{ __('Log out') }}
            </flux:menu.item>
        </form>
    </flux:menu>
</flux:dropdown>
