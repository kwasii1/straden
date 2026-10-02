<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white text-zinc-900">
        @php
            $project = request()->route('project');
        @endphp

        <flux:sidebar sticky collapsible class="border-e border-zinc-200 bg-zinc-50 lg:sticky lg:top-0 lg:h-dvh lg:self-start">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.item icon="arrow-left" :href="route('projects')" wire:navigate>
                    {{ __('All projects') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <flux:separator class="my-1" />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="squares-2x2" :href="route('projects.overview', ['project' => $project])" :current="request()->routeIs('projects.overview')" wire:navigate>
                    {{ __('Overview') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="beaker" :href="route('projects.tests', ['project' => $project])" :current="request()->routeIs('projects.tests', 'projects.new-test', 'projects.view-test', 'projects.view-test-script')" wire:navigate>
                    {{ __('Tests') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="rectangle-stack" :href="route('projects.runs', ['project' => $project])" :current="request()->routeIs('projects.runs*')" wire:navigate>
                    {{ __('Runs') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="link" :href="route('projects.connectors', ['project' => $project])" :current="request()->routeIs('projects.connectors')" wire:navigate>
                    {{ __('Connectors') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="folder-git-2" :href="route('projects.repositories', ['project' => $project])" :current="request()->routeIs('projects.repositories', 'projects.repository-browse')" wire:navigate>
                    {{ __('Repositories') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="git-compare" :href="route('projects.git-providers', ['project' => $project])" :current="request()->routeIs('projects.git-providers', 'projects.repository-picker')" wire:navigate>
                    {{ __('Git providers') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="cog-6-tooth" :href="route(auth()->user()?->isAdmin() ? 'settings.ai-integrations' : 'settings.api-tokens')" wire:navigate>
                    {{ __('Settings') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            @include('partials.sidebar-footer-links')

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <flux:header class="h-14 min-h-14 border-b border-zinc-200 bg-white">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <livewire:dropdown-search />

            <flux:spacer />

            <div class="flex items-center gap-1">
                <livewire:notification-bell />
                @include('partials.mobile-user-menu')
            </div>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
