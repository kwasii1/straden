@props(['tree' => []])

<div {{ $attributes->class('p-1.5') }}>
    @foreach ($tree as $item)
        <x-file-tree-item :item="$item" :depth="0" />
    @endforeach
</div>
