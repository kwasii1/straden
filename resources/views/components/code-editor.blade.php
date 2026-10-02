@props(['name', 'value' => '', 'language' => 'javascript', 'height' => '400px', 'editable' => false, 'savePath' => null])

@php
    $languageLabel = match ($language) {
        'javascript' => 'JavaScript',
        'typescript' => 'TypeScript',
        'json' => 'JSON',
        'html' => 'HTML',
        'css', 'scss' => strtoupper($language),
        'yaml' => 'YAML',
        'xml' => 'XML',
        'php' => 'PHP',
        'sql' => 'SQL',
        'plaintext' => 'Plain text',
        default => ucfirst((string) $language),
    };
@endphp

<div
    {{ $attributes->merge(['class' => 'flex flex-col overflow-hidden rounded-lg border border-zinc-200']) }}
    x-data="monacoEditor(@js($value), @js($language), @js((bool) $editable), @js($savePath))"
    wire:ignore
>
    <div x-ref="editorContainer" class="min-h-0 flex-1" style="height: {{ $height }}"></div>

    {{-- Status bar --}}
    <div class="flex h-7 shrink-0 items-center justify-between gap-3 border-t border-zinc-200 bg-zinc-50 px-3 text-[11px] text-zinc-500">
        <div class="flex min-w-0 items-center gap-3">
            <span class="tabular-nums" x-text="`Ln ${cursor.line}, Col ${cursor.column}`"></span>
            <span>{{ $languageLabel }}</span>
            @unless ($editable)
                <span class="inline-flex items-center gap-1">
                    <flux:icon.lock-closed variant="micro" class="size-3" /> Read-only
                </span>
            @endunless
        </div>

        @if ($editable && $savePath)
            <div class="flex shrink-0 items-center gap-2">
                <span class="inline-flex items-center gap-1.5" aria-live="polite">
                    <template x-if="buffer?.status === 'saving'">
                        <span class="inline-flex items-center gap-1.5"><x-spinner class="size-3" /> Saving…</span>
                    </template>
                    <template x-if="buffer?.status === 'dirty'">
                        <span class="text-amber-700">Unsaved changes</span>
                    </template>
                    <template x-if="buffer?.status === 'error'">
                        <span class="inline-flex items-center gap-1 text-red-700"><flux:icon.exclamation-triangle variant="micro" class="size-3" /> Save failed</span>
                    </template>
                    <template x-if="buffer?.status === 'saved'">
                        <span class="inline-flex items-center gap-1"><flux:icon.check variant="micro" class="size-3 text-emerald-600" /> Saved</span>
                    </template>
                </span>

                <button
                    type="button"
                    x-on:click="$store.editor.toggleAutosave()"
                    class="inline-flex h-5 items-center gap-1.5 rounded px-1.5 hover:bg-zinc-200/70 hover:text-zinc-900"
                    x-bind:aria-pressed="$store.editor.autosave ? 'true' : 'false'"
                    x-bind:title="$store.editor.autosave ? 'Autosave is on — changes save a second after you stop typing' : 'Autosave is off — press Ctrl+S to save'"
                >
                    <span
                        class="relative inline-flex h-2.5 w-4 shrink-0 rounded-full transition-colors duration-150"
                        x-bind:class="$store.editor.autosave ? 'bg-zinc-900' : 'bg-zinc-300'"
                        aria-hidden="true"
                    >
                        <span
                            class="absolute top-0.5 left-0.5 size-1.5 rounded-full bg-white transition-transform duration-150 ease-snappy"
                            x-bind:class="$store.editor.autosave ? 'translate-x-1.5' : 'translate-x-0'"
                        ></span>
                    </span>
                    <span x-text="$store.editor.autosave ? 'Autosave on' : 'Autosave off'"></span>
                </button>

                <button
                    type="button"
                    x-on:click="save()"
                    class="inline-flex h-5 items-center gap-1 rounded px-1.5 hover:bg-zinc-200/70 hover:text-zinc-900"
                    title="Save (Ctrl+S)"
                >
                    <flux:icon.arrow-down-tray variant="micro" class="size-3" /> Save
                </button>
            </div>
        @endif
    </div>

    <input type="hidden" name="{{ $name }}" x-bind:value="content">
</div>
