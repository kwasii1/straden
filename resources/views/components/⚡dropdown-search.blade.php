<?php

use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?Project $currentProject = null;

    public function mount(): void
    {
        $routeProject = request()->route('project');

        if ($routeProject instanceof Project) {
            $this->currentProject = $routeProject;
        } elseif (is_string($routeProject)) {
            $this->currentProject = Project::where('slug', $routeProject)->first();
        }
    }

    #[Computed]
    public function projects()
    {
        return Project::query()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'slug']);
    }

    public function goToProject(string $slug): void
    {
        $this->redirect(route('projects.overview', ['project' => $slug]), navigate: true);
    }
}; ?>

<div
    x-data="{
        open: false,
        search: '',
        panelStyle: '',
        currentProjectId: @js($this->currentProject?->slug),
        projects: @js($this->projects->map->only(['id', 'name', 'slug'])),
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
        <flux:button @click="togglePanel()" variant="ghost" size="sm" class="font-medium text-zinc-900">
            <span class="flex items-center gap-2 leading-none">
                @if ($this->currentProject)
                    <span class="ui-monogram size-5 rounded-[5px] text-[9px] leading-none">{{ strtoupper(substr($this->currentProject->name, 0, 2)) }}</span>
                @else
                    <flux:icon.folder variant="micro" class="text-zinc-400" />
                @endif
                <span class="block max-w-48 truncate leading-5">{{ $this->currentProject?->name ?? 'Switch project' }}</span>
                <flux:icon.chevron-up-down variant="micro" class="shrink-0 text-zinc-400" />
            </span>
        </flux:button>
    </div>

    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            @click.outside="open = false"
            x-transition:enter="transition ease-snappy duration-150"
            x-transition:enter-start="opacity-0 scale-[0.97]"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-out duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            :style="panelStyle"
            class="fixed z-50 w-72 origin-top-left overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-lg shadow-zinc-900/5"
        >
            <div class="border-b border-zinc-200 p-1.5">
                <flux:input
                    x-model="search"
                    size="sm"
                    placeholder="Find a project"
                    icon="magnifying-glass"
                    autofocus
                />
            </div>

            <div class="max-h-80 overflow-y-auto p-1">
                <template x-if="search.trim() === ''">
                    <div>
                        <div class="px-2 pt-1.5 pb-1 text-xs text-zinc-500">Recent</div>
                        <template x-if="recent.length === 0">
                            <div class="px-2 py-1.5 text-sm text-zinc-500">No projects yet.</div>
                        </template>
                        <template x-for="project in recent" :key="project.id">
                            <button
                                type="button"
                                @click="open = false; $wire.goToProject(project.slug)"
                                class="flex w-full items-center gap-2.5 rounded-md px-2 py-1.5 text-left text-sm text-zinc-700 transition-colors duration-150 hover:bg-zinc-100 hover:text-zinc-900"
                                :class="project.slug === currentProjectId && 'font-medium text-zinc-900'"
                            >
                                <span class="ui-monogram size-6 rounded-md text-[10px]" x-text="project.name.substring(0, 2).toUpperCase()"></span>
                                <span class="min-w-0 flex-1 truncate" x-text="project.name"></span>
                                <flux:icon.check
                                    variant="micro"
                                    x-show="project.slug === currentProjectId"
                                    class="shrink-0 text-zinc-900"
                                />
                            </button>
                        </template>
                    </div>
                </template>

                <template x-if="search.trim() !== ''">
                    <div>
                        <div class="px-2 pt-1.5 pb-1 text-xs text-zinc-500">Results</div>
                        <template x-if="filtered.length === 0">
                            <div class="px-2 py-1.5 text-sm text-zinc-500">
                                No matches for &ldquo;<span x-text="search"></span>&rdquo;
                            </div>
                        </template>
                        <template x-for="project in filtered" :key="project.id">
                            <button
                                type="button"
                                @click="open = false; $wire.goToProject(project.slug)"
                                class="flex w-full items-center gap-2.5 rounded-md px-2 py-1.5 text-left text-sm text-zinc-700 transition-colors duration-150 hover:bg-zinc-100 hover:text-zinc-900"
                                :class="project.slug === currentProjectId && 'font-medium text-zinc-900'"
                            >
                                <span class="ui-monogram size-6 rounded-md text-[10px]" x-text="project.name.substring(0, 2).toUpperCase()"></span>
                                <span class="min-w-0 flex-1 truncate" x-text="project.name"></span>
                                <flux:icon.check
                                    variant="micro"
                                    x-show="project.slug === currentProjectId"
                                    class="shrink-0 text-zinc-900"
                                />
                            </button>
                        </template>
                    </div>
                </template>
            </div>

            <div class="border-t border-zinc-200 p-1">
                <a
                    href="{{ route('projects') }}"
                    wire:navigate
                    class="flex items-center gap-2.5 rounded-md px-2 py-1.5 text-sm text-zinc-600 transition-colors duration-150 hover:bg-zinc-100 hover:text-zinc-900"
                >
                    <flux:icon.squares-2x2 variant="micro" class="text-zinc-400" />
                    View all projects
                </a>
            </div>
        </div>
    </template>
</div>
