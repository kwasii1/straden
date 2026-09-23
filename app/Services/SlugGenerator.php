<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SlugGenerator
{
    public static function unique(string $source, string $modelClass, int|string|null $ignoreId = null): string
    {
        $base = Str::slug($source);
        $slug = $base;
        $suffix = 2;

        while (self::exists($modelClass, $slug, $ignoreId)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    protected static function exists(string $modelClass, string $slug, int|string|null $ignoreId): bool
    {
        /** @var class-string<Model> $modelClass */
        return $modelClass::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }
}
