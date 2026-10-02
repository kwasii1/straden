@props([
    'backUrl' => null,
])

@php
    $accountLinks = [
        ['route' => 'profile.edit', 'label' => __('Profile')],
        ['route' => 'security.edit', 'label' => __('Security')],
        ['route' => 'appearance.edit', 'label' => __('Appearance')],
        ['route' => 'settings.api-tokens', 'label' => __('API tokens')],
    ];

    $adminLinks = auth()->user()?->isAdmin() ? [
        ['route' => 'settings.ai-integrations', 'label' => __('AI integrations')],
        ['route' => 'settings.insights-model', 'label' => __('Insights model')],
        ['route' => 'settings.users', 'label' => __('Users')],
    ] : [];

    // Livewire's original URL keeps the active tab correct during component update requests.
    $currentUrl = rtrim(\Livewire\Livewire::originalUrl(), '/');
@endphp

<div class="flex flex-col gap-8">
    <div class="flex flex-col gap-6">
        @include('partials.settings-heading', ['backUrl' => $backUrl])

        <nav aria-label="{{ __('Settings') }}" class="flex items-stretch gap-6 overflow-x-auto border-b border-zinc-200 [scrollbar-width:none]">
            @foreach ([$accountLinks, $adminLinks] as $group)
                @if ($loop->last && $group !== [])
                    <span class="my-2.5 w-px shrink-0 bg-zinc-200" aria-hidden="true"></span>
                @endif

                @foreach ($group as $link)
                    @php($isCurrent = rtrim(route($link['route']), '/') === $currentUrl)
                    <a
                        href="{{ route($link['route']) }}"
                        wire:navigate
                        wire:current.ignore
                        @if ($isCurrent) aria-current="page" @endif
                        @class([
                            'relative shrink-0 py-3 text-sm whitespace-nowrap transition-colors duration-150',
                            'font-medium text-zinc-900 after:absolute after:inset-x-0 after:-bottom-px after:h-0.5 after:rounded-full after:bg-zinc-900' => $isCurrent,
                            'text-zinc-500 hover:text-zinc-900' => ! $isCurrent,
                        ])
                    >{{ $link['label'] }}</a>
                @endforeach
            @endforeach
        </nav>
    </div>

    <div class="min-w-0">
        {{ $slot }}
    </div>
</div>
