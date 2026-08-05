@props(['name', 'value' => '', 'language' => 'javascript', 'height' => '400px', 'editable' => false, 'savePath' => null])

<div
    {{ $attributes->merge(['class' => 'rounded border border-zinc-800 overflow-hidden']) }}
    x-data="monacoEditor(@js($value), @js($language), @js((bool) $editable), @js($savePath))"
    wire:ignore
>
    @if ($editable)
        <div class="flex items-center justify-end gap-2 border-b border-zinc-800 bg-zinc-900 px-2 py-1">
            <span class="text-xs text-zinc-500">Ctrl+S to save</span>
            <flux:button
                x-on:click="save()"
                variant="primary"
                size="sm"
                icon="check"
            >
                Save
            </flux:button>
        </div>
    @endif
    <div x-ref="editorContainer" style="height: {{ $height }}"></div>
    <input type="hidden" name="{{ $name }}" x-bind:value="content">
</div>
