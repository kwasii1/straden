<div {{ $attributes->class('relative h-28 w-64 overflow-hidden') }} role="status" aria-label="Test run in progress">
    <style>
        @keyframes plow-bob { 0%, 100% { transform: translateY(0) rotate(0deg); } 50% { transform: translateY(-2px) rotate(-1deg); } }
        @keyframes plow-ground { to { transform: translateX(-32px); } }
        .plow-bull { animation: plow-bob 1.6s ease-in-out infinite; transform-origin: 50% 90%; }
        .plow-ground { animation: plow-ground 1.4s linear infinite; }
        @media (prefers-reduced-motion: reduce) { .plow-bull, .plow-ground { animation: none; } }
    </style>

    <x-app-logo-icon variant="mark" class="plow-bull absolute bottom-6 left-1/2 size-20 -translate-x-1/2 text-zinc-700" />

    <div class="absolute inset-x-0 bottom-0 h-6 overflow-hidden">
        <div class="plow-ground flex w-[calc(100%+64px)]">
            @for ($i = 0; $i < 16; $i++)
                <svg class="h-6 w-8 shrink-0 text-zinc-300" viewBox="0 0 32 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                    <path d="M0 6 Q8 2 16 6 T32 6" />
                    <path d="M0 14 Q8 10 16 14 T32 14" opacity=".7" />
                    <path d="M0 22 Q8 18 16 22 T32 22" opacity=".4" />
                </svg>
            @endfor
        </div>
    </div>
</div>
