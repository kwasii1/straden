<div
    x-data="{
        output: '',
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
    @log-chunk.window="output += $event.detail.content; $nextTick(() => scrollToBottom())"
    @modal-show.window="if ($event.detail.name === 'run-logs') startPolling()"
    @modal-close.window="if ($event.detail.name === 'run-logs') stopPolling()"
    class="flex flex-col"
>
    <div class="overflow-hidden border-t border-zinc-800 bg-zinc-950">
        {{-- Title bar --}}
        <div class="flex items-center gap-2 border-b border-zinc-800 bg-zinc-900 px-4 py-2.5">
            <flux:icon.command-line variant="micro" class="text-zinc-500" />
            <span class="text-xs font-medium text-zinc-300">k6 output</span>
            <span class="truncate font-mono text-xs text-zinc-500">{{ $run->slug }}</span>
        </div>

        {{-- Log output --}}
        <div
            x-ref="log"
            class="h-80 overflow-y-auto px-4 py-3 font-mono text-xs leading-relaxed whitespace-pre-wrap break-words text-zinc-300 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden"
        >
            <span x-show="output === ''" class="text-zinc-500">Waiting for k6 output…</span>
            <span x-show="output !== ''" x-text="output"></span>
        </div>
    </div>
</div>
