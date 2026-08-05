@props(['activeTab' => 'ai-providers'])

<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist aria-label="{{ __('Project Settings') }}">
            <flux:navlist.item wire:click="$set('activeTab', 'ai-providers')" :current="$activeTab === 'ai-providers'">
                {{ __('AI Providers') }}
            </flux:navlist.item>
            <flux:navlist.item wire:click="$set('activeTab', 'git-providers')" :current="$activeTab === 'git-providers'">
                {{ __('Git Providers') }}
            </flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        {{ $slot }}
    </div>
</div>
