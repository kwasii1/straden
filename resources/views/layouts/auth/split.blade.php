<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div class="bg-muted relative hidden h-full flex-col p-10 text-white lg:flex dark:border-e dark:border-neutral-800">
                <div class="absolute inset-0 bg-black"></div>
                <img src="{{ asset('images/auth-hero.svg') }}" alt="" aria-hidden="true" class="absolute inset-0 size-full object-cover" />
                <a href="{{ route('home') }}" class="relative z-20 flex items-center text-lg font-medium" wire:navigate>
                    <span class="flex items-center justify-center">
                        <x-app-logo-icon class="h-8 w-auto text-white" />
                    </span>
                    <span class="sr-only">{{ config('app.name', 'Straden') }}</span>
                </a>

                <div class="relative z-20 mt-auto max-w-md space-y-2">
                    <flux:heading size="xl" class="text-white">{{ __('Pull your system through real load.') }}</flux:heading>
                    <flux:text class="text-zinc-400">{{ __('Write k6 scripts, run them at scale and let AI turn the metrics into answers.') }}</flux:text>
                </div>
            </div>
            <div class="w-full lg:p-8">
                <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <a href="{{ route('home') }}" class="z-20 flex flex-col items-center gap-2 font-medium lg:hidden" wire:navigate>
                        <span class="flex items-center justify-center">
                            <x-app-logo-icon class="h-10 w-auto text-black dark:text-white" />
                        </span>

                        <span class="sr-only">{{ config('app.name', 'Straden') }}</span>
                    </a>
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
