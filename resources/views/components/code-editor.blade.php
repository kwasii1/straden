@props(['name', 'value' => '', 'language' => 'javascript', 'height' => '400px'])

<div
    {{ $attributes->merge(['class' => 'rounded border border-zinc-800 overflow-hidden']) }}
    x-data="monacoEditor(@js($value), @js($language))"
    wire:ignore
>
    <div x-ref="editorContainer" style="height: {{ $height }}"></div>
    <input type="hidden" name="{{ $name }}" x-bind:value="content">
</div>
