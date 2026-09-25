@props(['name'])

@php
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    [$icon, $color] = match (true) {
        in_array($extension, ['js', 'mjs', 'cjs', 'jsx']) => ['code-bracket', 'text-amber-500'],
        in_array($extension, ['ts', 'tsx']) => ['code-bracket', 'text-blue-500'],
        $extension === 'json' => ['code-bracket-square', 'text-yellow-600 dark:text-yellow-400'],
        in_array($extension, ['md', 'txt']) => ['document-text', 'text-sky-500'],
        in_array($extension, ['csv', 'tsv']) => ['table-cells', 'text-emerald-500'],
        in_array($extension, ['yml', 'yaml', 'toml', 'env', 'ini']) => ['cog-6-tooth', 'text-violet-500'],
        $extension === 'php' => ['code-bracket', 'text-indigo-500'],
        in_array($extension, ['html', 'xml', 'svg']) => ['code-bracket', 'text-orange-500'],
        in_array($extension, ['css', 'scss']) => ['swatch', 'text-pink-500'],
        in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp']) => ['photo', 'text-teal-500'],
        default => ['document', 'text-zinc-400'],
    };
@endphp

<flux:icon :icon="$icon" variant="micro" {{ $attributes->class(['size-4 shrink-0', $color]) }} />
