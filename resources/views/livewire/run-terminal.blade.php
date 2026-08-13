<div
    x-data="{
        timer: null,
        tick() {
            if ($wire.finished) {
                this.stopPolling();

                return;
            }

            $wire.poll().then(() => {
                this.scrollToBottom();

                if ($wire.finished) {
                    this.stopPolling();
                }
            });
        },
        startPolling() {
            if (this.timer) return;

            this.tick();
            this.timer = setInterval(() => this.tick(), 1000);
        },
        stopPolling() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
        },
        scrollToBottom() {
            const el = this.$refs.log;

            if (el) {
                el.scrollTop = el.scrollHeight;
            }
        },
        init() {
            this.startPolling();
        },
    }"
    @modal-show.window="if ($event.detail.name === 'run-logs') startPolling()"
    @modal-close.window="if ($event.detail.name === 'run-logs') stopPolling()"
    class="flex flex-col"
>
    

    <div class="overflow-hidden rounded-none border border-zinc-800 bg-[#0B0B0B] dark:border-zinc-800">
        {{-- Terminal title bar --}}
        <div class="flex items-center gap-2 border-b border-zinc-800 bg-[#141414] px-4 py-2.5">
            <span class="size-3 rounded-full bg-[#FF5F57]"></span>
            <span class="size-3 rounded-full bg-[#FEBC2E]"></span>
            <span class="size-3 rounded-full bg-[#28C840]"></span>
            <span class="ml-2 font-mono text-xs text-zinc-500">k6 — {{ $run->slug }}</span>
        </div>

        {{-- Log output --}}
        <div
            x-ref="log"
            class="h-80 overflow-y-auto px-4 py-3 font-mono text-xs leading-relaxed whitespace-pre-wrap break-words text-green-400/90 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden"
        >
            @if ($content === '')
                <span class="text-zinc-600">Waiting for k6 output…</span>
            @else
                {{ $content }}
            @endif
        </div>
    </div>
</div>
