<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class ScriptFileManager
{
    private Filesystem $disk;

    public function __construct(
        private string $basePath,
    ) {
        $this->disk = Storage::disk('local');
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    /** @return array<int, mixed> */
    public function fileTree(): array
    {
        if (! $this->disk->directoryExists($this->basePath)) {
            return [];
        }

        $directories = $this->disk->directories($this->basePath);
        $files = $this->disk->files($this->basePath);
        usort($directories, 'strnatcmp');
        usort($files, 'strnatcmp');

        $items = [];

        foreach ($directories as $d) {
            $items[] = [
                'name' => basename($d),
                'children' => $this->scanDir($d),
            ];
        }

        foreach ($files as $f) {
            $items[] = ['name' => basename($f)];
        }

        return $items;
    }

    public function readFile(string $relativePath): ?string
    {
        $full = $this->basePath.'/'.$relativePath;

        return $this->disk->exists($full) ? $this->disk->get($full) : null;
    }

    public function updateFile(string $relativePath, string $content): void
    {
        $resolved = $this->resolvePath($relativePath);

        $this->disk->put($this->basePath.'/'.$resolved, $content);
    }

    public function exists(string $relativePath): bool
    {
        return $this->disk->exists($this->basePath.'/'.$relativePath);
    }

    public function detectLanguage(string $path): string
    {
        return match (pathinfo($path, PATHINFO_EXTENSION)) {
            'js', 'mjs', 'cjs' => 'javascript',
            'ts', 'mts', 'cts' => 'typescript',
            'json' => 'json',
            'css' => 'css',
            'html', 'htm' => 'html',
            'md' => 'markdown',
            'py' => 'python',
            'rb' => 'ruby',
            'php' => 'php',
            default => 'plaintext',
        };
    }

    public function createFile(string $name, ?string $content = null, ?string $parentDir = null): ?string
    {
        $relPath = ($parentDir ? $parentDir.'/' : '').$name;

        if ($this->disk->exists($this->basePath.'/'.$relPath)) {
            return null;
        }

        $this->disk->put($this->basePath.'/'.$relPath, $content ?? '');

        return $relPath;
    }

    public function createFolder(string $name, ?string $parentDir = null): ?string
    {
        $relPath = ($parentDir ? $parentDir.'/' : '').$name;

        $this->disk->makeDirectory($this->basePath.'/'.$relPath);

        return $relPath;
    }

    public function move(string $sourcePath, string $targetDir): ?string
    {
        $fileName = basename($sourcePath);
        $destRel = ($targetDir ? $targetDir.'/' : '').$fileName;

        if ($sourcePath === $destRel) {
            return null;
        }

        if (str_starts_with($targetDir, $sourcePath.'/')) {
            return null;
        }

        if ($this->disk->exists($this->basePath.'/'.$destRel)) {
            return null;
        }

        $this->disk->move($this->basePath.'/'.$sourcePath, $this->basePath.'/'.$destRel);

        return $destRel;
    }

    public function rename(string $path, string $newName): ?string
    {
        $dir = dirname($path);
        $newPath = ($dir === '.' ? '' : $dir.'/').$newName;

        if ($path === $newPath) {
            return null;
        }

        if ($this->disk->exists($this->basePath.'/'.$newPath)) {
            return null;
        }

        $this->disk->move($this->basePath.'/'.$path, $this->basePath.'/'.$newPath);

        return $newPath;
    }

    public function delete(string $relativePath): bool
    {
        $full = $this->basePath.'/'.$relativePath;

        if ($this->disk->directoryExists($full)) {
            return $this->disk->deleteDirectory($full);
        }

        return $this->disk->delete($full);
    }

    public function entryPointPath(): string
    {
        return 'script.js';
    }

    private function resolvePath(string $path): string
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

    /** @return array<int, mixed> */
    private function scanDir(string $dir): array
    {
        $directories = $this->disk->directories($dir);
        $files = $this->disk->files($dir);
        usort($directories, 'strnatcmp');
        usort($files, 'strnatcmp');

        $items = [];

        foreach ($directories as $d) {
            $items[] = [
                'name' => basename($d),
                'children' => $this->scanDir($d),
            ];
        }

        foreach ($files as $f) {
            $items[] = ['name' => basename($f)];
        }

        return $items;
    }
}
