<?php

use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function projects()
    {
        return Project::query()
            ->orderByDesc('created_at')
            ->get(['id', 'name']);
    }

    public function goToProject(int $projectId)
    {
        return $this->redirect(route('project', ['id' => $projectId]), navigate: true);
    }
}; ?>

<div
    x-data="{
        open: false,
        search: '',
        panelStyle: '',
        projects: @js($this->projects->map->only(['id', 'name'])),
        get recent() {
            return this.projects.slice(0, 8);
        },
        get filtered() {
            if (this.search.trim() === '') return [];
            const q = this.search.toLowerCase();
            return this.projects.filter(p => p.name.toLowerCase().includes(q));
        },
        togglePanel() {
            this.open = !this.open;
            if (this.open) {
                this.$nextTick(() => this.positionPanel());
            }
        },
        positionPanel() {
            const rect = this.$refs.trigger.getBoundingClientRect();
            this.panelStyle = `top:${rect.bottom + 8}px; left:${rect.left}px;`;
        },
    }"
    @keydown.escape.window="open = false"
    @resize.window="open && positionPanel()"
    @scroll.window="open && positionPanel()"
    class="relative"
>
    <div x-ref="trigger">
        <flux:button @click="togglePanel()" variant="ghost" icon:trailing="chevron-down">
            Switch project
        </flux:button>
    </div>

    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            @click.outside="open = false"
            x-transition
            :style="panelStyle"
            class="fixed z-50 w-80 rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800"
        >
        <div class="border-b border-zinc-200 p-2 dark:border-zinc-700">
            <flux:input
                x-model="search"
                placeholder="Find a project..."
                icon="magnifying-glass"
                autofocus
            />
        </div>

        <div class="max-h-80 overflow-y-auto p-1">

            <template x-if="search.trim() === ''">
                <div>
                    <div class="px-3 py-1.5 text-xs font-medium uppercase tracking-wide text-zinc-400">
                        Recent
                    </div>
                    <template x-if="recent.length === 0">
                        <div class="px-3 py-2 text-sm text-zinc-400">No projects yet.</div>
                    </template>
                    <template x-for="project in recent" :key="project.id">
                        <button
                            type="button"
                            @click="open = false; $wire.goToProject(project.id)"
                            class="flex w-full items-center rounded-md px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-700"
                            x-text="project.name"
                        ></button>
                    </template>
                </div>
            </template>

            <template x-if="search.trim() !== ''">
                <div>
                    <div class="px-3 py-1.5 text-xs font-medium uppercase tracking-wide text-zinc-400">
                        Results
                    </div>
                    <template x-if="filtered.length === 0">
                        <div class="px-3 py-2 text-sm text-zinc-400">
                            No matches for "<span x-text="search"></span>"
                        </div>
                    </template>
                    <template x-for="project in filtered" :key="project.id">
                        <button
                            type="button"
                            @click="open = false; $wire.goToProject(project.id)"
                            class="flex w-full items-center rounded-md px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-700"
                            x-text="project.name"
                        ></button>
                    </template>
                </div>
            </template>

        </div>

        <div class="border-t border-zinc-200 p-1 dark:border-zinc-700">
            <a
                href="{{ route('projects') }}"
                wire:navigate
                class="flex items-center rounded-md px-3 py-2 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700"
            >
                View all projects
            </a>
        </div>
        </div>
    </template>
</div>
