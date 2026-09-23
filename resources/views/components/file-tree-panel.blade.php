@props(['tree' => []])

<div class="p-1">
    @foreach ($tree as $item)
        <x-file-tree-item :item="$item" :depth="0" />
    @endforeach
</div>
