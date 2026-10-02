<div
    x-data="{ activeTab: 'chat' }"
    {{ $attributes->merge(['class' => 'flex flex-col overflow-hidden border-l border-zinc-200 bg-white']) }}
>
    <div class="flex h-10 shrink-0 items-center gap-0.5 border-b border-zinc-200 bg-zinc-50 px-1.5" role="tablist">
        <button
            type="button"
            role="tab"
            @click="activeTab = 'chat'"
            :aria-selected="activeTab === 'chat'"
            :class="activeTab === 'chat'
                ? 'bg-zinc-200/70 text-zinc-900'
                : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900'"
            class="flex h-7 items-center gap-1.5 rounded-md px-2.5 text-[13px] font-medium"
        >
            <flux:icon.sparkles variant="micro" class="size-3.5" />
            Chat
        </button>
        <button
            type="button"
            role="tab"
            @click="activeTab = 'files'"
            :aria-selected="activeTab === 'files'"
            :class="activeTab === 'files'
                ? 'bg-zinc-200/70 text-zinc-900'
                : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900'"
            class="flex h-7 items-center gap-1.5 rounded-md px-2.5 text-[13px] font-medium"
        >
            <flux:icon.folder variant="micro" class="size-3.5" />
            Files
        </button>
    </div>

    <div x-show="activeTab === 'chat'" class="flex min-h-0 flex-1 flex-col">
        <x-chat-panel />
    </div>

    <div x-show="activeTab === 'files'" class="flex min-h-0 flex-1 flex-col overflow-y-auto bg-zinc-50">
        <x-file-tree-panel :tree="[
            [
                'name' => 'test-script',
                'children' => [
                    ['name' => 'entry.js'],
                    [
                        'name' => 'utils',
                        'children' => [
                            ['name' => 'helpers.js'],
                            ['name' => 'constants.js'],
                        ],
                    ],
                    [
                        'name' => 'tests',
                        'children' => [
                            ['name' => 'login.test.js'],
                        ],
                    ],
                    ['name' => 'config.json'],
                ],
            ],
        ]" />
    </div>
</div>
