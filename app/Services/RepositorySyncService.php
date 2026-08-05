<?php

namespace App\Services;

use App\GitProviders\GitProviderResolver;
use App\Models\Repository;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

class RepositorySyncService
{
    private const GIT_CLONE_TIMEOUT = 300;

    private const GIT_PULL_TIMEOUT = 120;

    public function sync(Repository $repository): array
    {
        if ($repository->type === 'git') {
            return $this->syncGitRepository($repository);
        }

        if ($repository->type === 'local_path') {
            return $this->syncLocalPath($repository);
        }

        throw new RuntimeException("Unsupported repository type: {$repository->type}");
    }

    private function syncGitRepository(Repository $repository): array
    {
        $clonePath = $this->clonePath($repository);

        if (is_dir($clonePath.DIRECTORY_SEPARATOR.'.git')) {
            $this->pull($repository, $clonePath);
        } else {
            $this->clone($repository, $clonePath);
        }

        $fileTree = $this->buildFileTree($clonePath);
        $commitSha = $this->getCurrentCommit($clonePath);

        return [
            'file_tree' => $fileTree,
            'last_commit_sha' => $commitSha,
        ];
    }

    private function syncLocalPath(Repository $repository): array
    {
        $localPath = $repository->local_path;

        if ($localPath === null || $localPath === '' || $localPath === '0') {
            throw new RuntimeException('Local path is not configured.');
        }

        if (! is_dir($localPath)) {
            throw new RuntimeException("Local path does not exist: {$localPath}");
        }

        if (! is_readable($localPath)) {
            throw new RuntimeException("Local path is not readable: {$localPath}");
        }

        $fileTree = $this->buildFileTree($localPath);

        return [
            'file_tree' => $fileTree,
            'last_commit_sha' => null,
        ];
    }

    private function clonePath(Repository $repository): string
    {
        return storage_path('app/repos/'.$repository->project_id.'/'.$repository->id);
    }

    private function clone(Repository $repository, string $targetPath): void
    {
        $url = $this->resolveGitUrl($repository);
        $branch = $repository->git_branch ?: 'main';

        File::ensureDirectoryExists(dirname($targetPath));

        $command = [
            'git', 'clone',
            '--depth', '1',
            '--branch', $branch,
            $url,
            $targetPath,
        ];

        $process = new Process($command);
        $process->setTimeout(self::GIT_CLONE_TIMEOUT);

        $env = $this->gitEnvironment($repository);
        if ($env !== null) {
            $process->setEnv(array_merge($_ENV, $_SERVER, $env));
        }

        $process->run();

        if (! $process->isSuccessful()) {
            $error = $process->getErrorOutput() ?: $process->getOutput();
            $this->cleanupCloneDirectory($targetPath);

            $sanitizedError = $this->sanitizeError($error);

            if ($this->isAuthError($sanitizedError)) {
                throw new RuntimeException('Git clone failed (auth error): '.$sanitizedError);
            }

            throw new RuntimeException('Git clone failed: '.$sanitizedError);
        }
    }

    private function pull(Repository $repository, string $targetPath): void
    {
        $command = ['git', 'pull', '--ff-only'];

        $process = new Process($command, $targetPath);
        $process->setTimeout(self::GIT_PULL_TIMEOUT);

        $env = $this->gitEnvironment($repository);
        if ($env !== null) {
            $process->setEnv(array_merge($_ENV, $_SERVER, $env));
        }

        $process->run();

        if (! $process->isSuccessful()) {
            $error = $process->getErrorOutput() ?: $process->getOutput();

            $sanitizedError = $this->sanitizeError($error);

            if ($this->isAuthError($sanitizedError)) {
                throw new RuntimeException('Git pull failed (auth error): '.$sanitizedError);
            }

            throw new RuntimeException('Git pull failed: '.$sanitizedError);
        }
    }

    private function resolveGitUrl(Repository $repository): string
    {
        if ($repository->connector_id && $repository->connector) {
            $connector = $repository->connector;

            if ($connector->isGitProvider()) {
                $provider = GitProviderResolver::for($connector->type);

                return $provider->buildAuthenticatedCloneUrl(
                    $repository->git_url,
                    $connector->token
                );
            }
        }

        $url = $repository->git_url;

        if (str_starts_with($url, 'https://')) {
            return $url;
        }

        if (! str_contains($url, '@') && ! str_starts_with($url, 'git@')) {
            return 'https://github.com/'.$url;
        }

        return $url;
    }

    private function gitEnvironment(Repository $repository): ?array
    {
        return null;
    }

    private function buildFileTree(string $basePath): array
    {
        return $this->scanDir($basePath);
    }

    private function scanDir(string $dir): array
    {
        $items = [];
        $entries = scandir($dir);

        if ($entries === false) {
            return $items;
        }

        usort($entries, 'strnatcmp');

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if ($this->shouldExclude($entry)) {
                continue;
            }

            $fullPath = $dir.DIRECTORY_SEPARATOR.$entry;

            if (is_dir($fullPath)) {
                $children = $this->scanDir($fullPath);

                if ($children === []) {
                    continue;
                }

                $items[] = [
                    'name' => $entry,
                    'children' => $children,
                ];
            } elseif (is_file($fullPath)) {
                $items[] = ['name' => $entry];
            }
        }

        return $items;
    }

    private function shouldExclude(string $name): bool
    {
        return str_starts_with($name, '.git')
            || str_starts_with($name, 'node_modules')
            || $name === 'vendor'
            || $name === '.env';
    }

    private function getCurrentCommit(string $clonePath): ?string
    {
        $process = new Process(['git', 'rev-parse', 'HEAD'], $clonePath);
        $process->run();

        if ($process->isSuccessful()) {
            return trim($process->getOutput());
        }

        return null;
    }

    private function cleanupCloneDirectory(string $targetPath): void
    {
        if (is_dir($targetPath)) {
            File::deleteDirectory($targetPath);
        }
    }

    private function sanitizeError(string $error): string
    {
        $error = preg_replace('/https:\/\/[^@]+@/', 'https://***@', $error);

        return mb_substr(trim($error), 0, 1000);
    }

    private function isAuthError(string $error): bool
    {
        $lower = strtolower($error);

        return str_contains($lower, 'could not read from remote repository')
            || str_contains($lower, 'authentication failed')
            || str_contains($lower, 'permission denied')
            || str_contains($lower, 'unauthorized')
            || str_contains($lower, 'access denied')
            || str_contains($lower, 'remote: invalid username or password')
            || str_contains($lower, 'remote: http basic: access denied');
    }
}
