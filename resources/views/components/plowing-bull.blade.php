<div {{ $attributes->class('relative h-28 w-64 overflow-hidden') }} role="status" aria-label="Test run in progress">
    <style>
        @keyframes plow-bob { 0%, 100% { transform: translateY(0) rotate(0deg); } 25% { transform: translateY(-3px) rotate(-1.5deg); } 75% { transform: translateY(1px) rotate(1deg); } }
        @keyframes plow-ground { to { transform: translateX(-32px); } }
        @keyframes plow-dust { 0% { opacity: .7; transform: translate(0, 0) scale(.6); } 100% { opacity: 0; transform: translate(-26px, -14px) scale(1.4); } }
        .plow-bull { animation: plow-bob .7s ease-in-out infinite; transform-origin: 50% 90%; }
        .plow-ground { animation: plow-ground .5s linear infinite; }
        .plow-dust { animation: plow-dust 1s ease-out infinite; }
        @media (prefers-reduced-motion: reduce) { .plow-bull, .plow-ground, .plow-dust { animation: none; } }
    </style>

    <x-app-logo-icon variant="mark" class="plow-bull absolute bottom-6 left-1/2 size-20 -translate-x-1/2 text-zinc-700 dark:text-zinc-200" />

    <span class="plow-dust absolute bottom-7 left-[38%] size-2 rounded-full bg-amber-700/60"></span>
    <span class="plow-dust absolute bottom-6 left-[36%] size-1.5 rounded-full bg-amber-700/50" style="animation-delay: .35s"></span>
    <span class="plow-dust absolute bottom-8 left-[40%] size-1.5 rounded-full bg-amber-700/40" style="animation-delay: .7s"></span>

    <div class="absolute inset-x-0 bottom-0 h-6 overflow-hidden">
        <div class="plow-ground flex w-[calc(100%+64px)]">
            @for ($i = 0; $i < 16; $i++)
                <svg class="h-6 w-8 shrink-0 text-amber-800/70 dark:text-amber-600/60" viewBox="0 0 32 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M0 6 Q8 0 16 6 T32 6" />
                    <path d="M0 14 Q8 8 16 14 T32 14" opacity=".7" />
                    <path d="M0 22 Q8 16 16 22 T32 22" opacity=".4" />
                </svg>
            @endfor
        </div>
    </div>
</div>
