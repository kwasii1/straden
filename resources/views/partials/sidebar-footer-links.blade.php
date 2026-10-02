<flux:sidebar.nav>
    <flux:sidebar.item icon="git-branch" :href="config('straden.links.repository')" target="_blank">
        {{ __('Source code') }}
    </flux:sidebar.item>

    <flux:sidebar.item icon="book-open-text" :href="config('straden.links.documentation')" target="_blank">
        {{ __('Documentation') }}
    </flux:sidebar.item>

    @if (auth()->user()?->isAdmin())
        <flux:sidebar.item icon="queue-list" :href="route('horizon.index')" target="_blank">
            {{ __('Horizon') }}
        </flux:sidebar.item>

        <flux:sidebar.item icon="document-text" :href="route('log-viewer.index')" target="_blank">
            {{ __('Log viewer') }}
        </flux:sidebar.item>
    @endif
</flux:sidebar.nav>
