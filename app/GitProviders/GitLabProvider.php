<?php

namespace App\GitProviders;

use Illuminate\Support\Facades\Http;

class GitLabProvider implements GitProvider
{
    private const API_BASE = 'https://gitlab.com/api/v4';

    public function validateToken(string $token, array $context = []): bool
    {
        $response = Http::withToken($token)
            ->get(self::API_BASE.'/user');

        return $response->successful();
    }

    public function listRepositories(string $token, array $context = []): array
    {
        $allRepos = [];
        $page = 1;

        do {
            $response = Http::withToken($token)
                ->get(self::API_BASE.'/projects', [
                    'membership' => true,
                    'per_page' => 100,
                    'page' => $page,
                    'order_by' => 'updated_at',
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException('Failed to list GitLab repositories: '.$response->body());
            }

            $repos = $response->json();

            if (! is_array($repos)) {
                break;
            }

            foreach ($repos as $repo) {
                $allRepos[] = [
                    'full_name' => $repo['path_with_namespace'],
                    'clone_url' => $repo['http_url_to_repo'] ?? $repo['clone_url'] ?? '',
                    'default_branch' => $repo['default_branch'] ?? 'main',
                    'private' => ($repo['visibility'] ?? 'private') !== 'public',
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

        return $scheme.'://oauth2:'.$token.'@'.$parsed['host'].($parsed['path'] ?? '');
    }

    public function requiredScopesHelpText(): string
    {
        return 'Create a token at https://gitlab.com/-/user_settings/personal_access_tokens with scopes: read_repository, api.';
    }
}
