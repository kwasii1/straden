<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white text-zinc-900">
        <div class="grid min-h-dvh lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
            <aside class="relative hidden overflow-hidden border-e border-zinc-200 bg-zinc-50 lg:flex lg:flex-col lg:p-10">
                <img src="{{ asset('images/auth-panel.jpg') }}" alt="" aria-hidden="true" class="absolute inset-0 size-full object-cover" />

                <a href="{{ route('home') }}" class="relative flex items-center" wire:navigate>
                    <x-app-logo-icon class="h-7 w-auto text-zinc-900" />
                    <span class="sr-only">{{ config('app.name', 'Straden') }}</span>
                </a>

                <div class="relative mt-auto w-full rounded-xl bg-white/85 p-5 ring-1 ring-zinc-900/5 backdrop-blur-sm">
                    <p class="text-2xl font-semibold tracking-tight text-balance text-zinc-900">{{ __('Pull your system through real load.') }}</p>
                    <p class="mt-2 text-sm text-pretty text-zinc-600">{{ __('Write k6 scripts, run them at scale and let AI turn the metrics into answers.') }}</p>
                </div>
            </aside>

            <main class="flex items-center justify-center px-6 py-12 sm:px-10">
                <div class="flex w-full max-w-sm flex-col gap-6 animate-enter">
                    <a href="{{ route('home') }}" class="flex items-center lg:hidden" wire:navigate>
                        <x-app-logo-icon class="h-7 w-auto text-zinc-900" />
                        <span class="sr-only">{{ config('app.name', 'Straden') }}</span>
                    </a>

                    {{ $slot }}
                </div>
            </main>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
