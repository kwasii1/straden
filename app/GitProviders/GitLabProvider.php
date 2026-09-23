<?php

namespace App\GitProviders;

use Illuminate\Support\Facades\Http;

class GitLabProvider implements GitProvider
{
    private const API_BASE = 'https://gitlab.com/api/v4';

    /** @param array{workspace?: string, organization?: string, project?: string, username?: string} $context */
    public function validateToken(string $token, array $context = []): bool
    {
        $response = Http::withToken($token)
            ->get(self::API_BASE.'/user');

        return $response->successful();
    }

    /**
     * @param  array{workspace?: string, organization?: string, project?: string, username?: string}  $context
     * @return array<int, array<string, mixed>>
     */
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
                    'provider_id' => $repo['id'],
                ];
            }

            $page++;
        } while (count($repos) === 100);

        return $allRepos;
    }

    /**
     * @param  array<string, mixed>  $repo
     * @param  array{workspace?: string, organization?: string, project?: string, username?: string}  $context
     * @return array<int, array<string, mixed>>
     */
    public function listBranches(string $token, array $repo, array $context = []): array
    {
        $projectId = $repo['provider_id'] ?? '';
        $branches = [];
        $page = 1;

        do {
            $response = Http::withToken($token)
                ->get(self::API_BASE.'/projects/'.$projectId.'/repository/branches', [
                    'per_page' => 100,
                    'page' => $page,
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException('Failed to list branches: '.$response->body());
            }

            $data = $response->json();

            if (! is_array($data)) {
                break;
            }

            foreach ($data as $branch) {
                $branches[] = ['name' => $branch['name']];
            }

            $page++;
        } while (count($data) === 100);

        return $branches;
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
        return 'Create a <a href="https://gitlab.com/-/user_settings/personal_access_tokens?name=Straden&scopes=read_repository,api" target="_blank" class="underline">personal access token</a> with <strong>read_repository</strong> and <strong>api</strong> scopes.';
    }
}
