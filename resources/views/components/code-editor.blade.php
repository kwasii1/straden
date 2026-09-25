@props(['name', 'value' => '', 'language' => 'javascript', 'height' => '400px', 'editable' => false, 'savePath' => null])

<div
    {{ $attributes->merge(['class' => 'flex flex-col overflow-hidden rounded border border-zinc-200 dark:border-zinc-800']) }}
    x-data="monacoEditor(@js($value), @js($language), @js((bool) $editable), @js($savePath))"
    wire:ignore
>
    <div x-ref="editorContainer" class="min-h-0 flex-1" style="height: {{ $height }}"></div>

    {{-- Status bar --}}
    <div class="flex h-7 shrink-0 items-center justify-between gap-3 border-t border-zinc-200 bg-zinc-50 px-3 text-[11px] text-zinc-500 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400">
        <div class="flex min-w-0 items-center gap-3">
            <span class="tabular-nums" x-text="`Ln ${cursor.line}, Col ${cursor.column}`"></span>
            <span class="uppercase tracking-wide">{{ $language }}</span>
            @unless ($editable)
                <span class="inline-flex items-center gap-1">
                    <flux:icon.lock-closed variant="micro" class="size-3" /> Read-only
                </span>
            @endunless
        </div>

        @if ($editable && $savePath)
            <div class="flex shrink-0 items-center gap-3">
                <span class="inline-flex items-center gap-1.5" aria-live="polite">
                    <template x-if="buffer?.status === 'saving'">
                        <span class="inline-flex items-center gap-1.5"><flux:icon.arrow-path variant="micro" class="size-3 animate-spin" /> Saving…</span>
                    </template>
                    <template x-if="buffer?.status === 'dirty'">
                        <span class="inline-flex items-center gap-1.5 text-amber-600 dark:text-amber-400"><span class="size-1.5 rounded-full bg-current"></span> Unsaved changes</span>
                    </template>
                    <template x-if="buffer?.status === 'error'">
                        <span class="inline-flex items-center gap-1.5 text-red-600 dark:text-red-400"><flux:icon.exclamation-triangle variant="micro" class="size-3" /> Save failed</span>
                    </template>
                    <template x-if="buffer?.status === 'saved'">
                        <span class="inline-flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400"><flux:icon.check variant="micro" class="size-3" /> Saved</span>
                    </template>
                </span>

                <button
                    type="button"
                    x-on:click="$store.editor.toggleAutosave()"
                    class="inline-flex items-center gap-1.5 rounded px-1.5 py-0.5 hover:bg-zinc-200 hover:text-zinc-800 dark:hover:bg-white/10 dark:hover:text-zinc-100"
                    x-bind:title="$store.editor.autosave ? 'Autosave is on — changes save a second after you stop typing' : 'Autosave is off — press Ctrl+S to save'"
                >
                    <span class="size-1.5 rounded-full" x-bind:class="$store.editor.autosave ? 'bg-emerald-500' : 'bg-zinc-400'"></span>
                    <span x-text="$store.editor.autosave ? 'Autosave on' : 'Autosave off'"></span>
                </button>

                <button
                    type="button"
                    x-on:click="save()"
                    class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 hover:bg-zinc-200 hover:text-zinc-800 dark:hover:bg-white/10 dark:hover:text-zinc-100"
                    title="Save (Ctrl+S)"
                >
                    <flux:icon.arrow-down-tray variant="micro" class="size-3" /> Save
                </button>
            </div>
        @endif
    </div>

    <input type="hidden" name="{{ $name }}" x-bind:value="content">
</div>
