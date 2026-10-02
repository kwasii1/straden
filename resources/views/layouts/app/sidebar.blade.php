<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white text-zinc-900">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 lg:sticky lg:top-0 lg:h-dvh lg:self-start">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.item icon="squares-2x2" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="rectangle-stack" :href="route('projects')" :current="request()->routeIs('projects')" wire:navigate>
                    {{ __('Projects') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="bell" :href="route('notifications')" :current="request()->routeIs('notifications')" wire:navigate>
                    {{ __('Notifications') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="cog-6-tooth" :href="route(auth()->user()?->isAdmin() ? 'settings.ai-integrations' : 'settings.api-tokens')" :current="request()->routeIs('settings.*', 'profile.*', 'security.*', 'appearance.*', 'user-password.*', 'two-factor.*')" wire:navigate>
                    {{ __('Settings') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <flux:spacer />

            @include('partials.sidebar-footer-links')

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <flux:header class="h-14 min-h-14 border-b border-zinc-200 bg-white">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

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
