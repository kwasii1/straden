@props(['name'])

@php
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    $icon = match (true) {
        in_array($extension, ['js', 'mjs', 'cjs', 'jsx', 'ts', 'tsx', 'mts', 'cts', 'php', 'py', 'rb', 'html', 'htm', 'xml', 'svg']) => 'code-bracket',
        $extension === 'json' => 'code-bracket-square',
        in_array($extension, ['md', 'txt']) => 'document-text',
        in_array($extension, ['csv', 'tsv']) => 'table-cells',
        in_array($extension, ['yml', 'yaml', 'toml', 'env', 'ini']) => 'cog-6-tooth',
        in_array($extension, ['css', 'scss']) => 'swatch',
        in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp']) => 'photo',
        default => 'document',
    };
@endphp

<flux:icon :icon="$icon" variant="micro" {{ $attributes->class(['shrink-0 text-zinc-400', 'size-4' => ! $attributes->has('class')]) }} />
