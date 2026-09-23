<div
    x-data="{ activeTab: 'chat' }"
    {{ $attributes->merge(['class' => 'flex flex-col border border-zinc-800 bg-zinc-950 overflow-hidden']) }}
>
    <div class="grid grid-cols-2 border-b border-zinc-800 shrink-0">
        <button
            @click="activeTab = 'chat'"
            :class="activeTab === 'chat'
                ? 'bg-zinc-800 text-zinc-100'
                : 'text-zinc-500 hover:text-zinc-300 hover:bg-zinc-900/50'"
            class="flex items-center justify-center gap-2 px-3 py-2 text-sm font-medium transition-colors"
        >
            <flux:icon.sparkles class="size-4" />
            AI Chat
        </button>
        <button
            @click="activeTab = 'files'"
            :class="activeTab === 'files'
                ? 'bg-zinc-800 text-zinc-100'
                : 'text-zinc-500 hover:text-zinc-300 hover:bg-zinc-900/50'"
            class="flex items-center justify-center gap-2 px-3 py-2 text-sm font-medium transition-colors"
        >
            <flux:icon.folder-tree class="size-4" />
            File Tree
        </button>
    </div>

    <div x-show="activeTab === 'chat'" class="flex-1 flex flex-col min-h-0">
        <x-chat-panel />
    </div>

    <div x-show="activeTab === 'files'" class="flex-1 flex flex-col min-h-0 overflow-y-auto">
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
