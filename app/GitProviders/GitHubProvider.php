<?php

namespace App\GitProviders;

use Illuminate\Support\Facades\Http;

class GitHubProvider implements GitProvider
{
    private const API_BASE = 'https://api.github.com';

    public function validateToken(string $token, array $context = []): bool
    {
        $response = Http::withToken($token)
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->get(self::API_BASE.'/user');

        return $response->successful();
    }

    public function listRepositories(string $token, array $context = []): array
    {
        $allRepos = [];
        $page = 1;

        do {
            $response = Http::withToken($token)
                ->withHeaders(['Accept' => 'application/vnd.github+json'])
                ->get(self::API_BASE.'/user/repos', [
                    'per_page' => 100,
                    'page' => $page,
                    'sort' => 'updated',
                    'affiliation' => 'owner,collaborator,organization_member',
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException('Failed to list GitHub repositories: '.$response->body());
            }

            $repos = $response->json();

            if (! is_array($repos)) {
                break;
            }

            foreach ($repos as $repo) {
                $allRepos[] = [
                    'full_name' => $repo['full_name'],
                    'clone_url' => $repo['clone_url'],
                    'default_branch' => $repo['default_branch'] ?? 'main',
                    'private' => $repo['private'] ?? false,
                ];
            }

            $page++;
        } while (count($repos) === 100);

        return $allRepos;
    }

    public function buildAuthenticatedCloneUrl(string $cloneUrl, string $token): string
    {
        $parsed = parse_url($cloneUrl);

        if ($parsed === false || ! isset($parsed['host'])) {
            return $cloneUrl;
        }

        $scheme = $parsed['scheme'] ?? 'https';

        return $scheme.'://x-access-token:'.$token.'@'.$parsed['host'].($parsed['path'] ?? '');
    }

    public function requiredScopesHelpText(): string
    {
        return 'Create a <a href="https://github.com/settings/personal-access-tokens/new?contents=read&metadata=read" target="_blank" class="underline">fine-grained personal access token</a> with <strong>Contents (Read-only)</strong> and <strong>Metadata (Read-only)</strong> scopes.';
    }
}
