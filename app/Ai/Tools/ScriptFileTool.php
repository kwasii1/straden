<?php

namespace App\Ai\Tools;

use App\Models\Script;
use App\Services\ScriptFileManager;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Contracts\Tool;

abstract class ScriptFileTool implements Tool
{
    public function __construct(public Script $script) {}

    protected function fm(): ScriptFileManager
    {
        return new ScriptFileManager($this->basePath());
    }

    protected function basePath(): string
    {
        return 'scripts/'.$this->script->test_id.'/'.$this->script->id;
    }

    protected function itemExists(string $path): bool
    {
        $full = $this->basePath().'/'.$path;

        return Storage::disk('local')->exists($full) || Storage::disk('local')->directoryExists($full);
    }

    protected function resolvePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if (empty($segments)) {
                    throw new \InvalidArgumentException('Path escapes the script directory.');
                }

                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        if (empty($segments)) {
            throw new \InvalidArgumentException('A non-empty file path is required.');
        }

        return implode('/', $segments);
    }
}
