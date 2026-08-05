<?php

namespace App\GitProviders;

use Illuminate\Support\Facades\Http;

class BitbucketProvider implements GitProvider
{
    private const API_BASE = 'https://api.bitbucket.org/2.0';

    public function validateToken(string $token, array $context = []): bool
    {
        $username = $context['username'] ?? '';

        if (empty($username)) {
            $response = Http::withBasicAuth('x-token-auth', $token)
                ->get(self::API_BASE.'/user');

            if ($response->successful()) {
                $userData = $response->json();

                return isset($userData['username']);
            }

            return false;
        }

        $response = Http::withBasicAuth($username, $token)
            ->get(self::API_BASE.'/user');

        return $response->successful();
    }

    public function listRepositories(string $token, array $context = []): array
    {
        $workspace = $context['workspace'] ?? '';

        if (empty($workspace)) {
            throw new \RuntimeException('Bitbucket requires a workspace to list repositories.');
        }

        $allRepos = [];
        $url = self::API_BASE.'/repositories/'.$workspace;

        do {
            $response = Http::withBasicAuth('x-token-auth', $token)
                ->get($url);

            if (! $response->successful()) {
                throw new \RuntimeException('Failed to list Bitbucket repositories: '.$response->body());
            }

            $data = $response->json();

            foreach ($data['values'] ?? [] as $repo) {
                $cloneUrl = '';

                foreach ($repo['links']['clone'] ?? [] as $link) {
                    if (($link['name'] ?? '') === 'https') {
                        $cloneUrl = $link['href'];

                        break;
                    }
                }

                $allRepos[] = [
                    'full_name' => $repo['full_name'] ?? '',
                    'clone_url' => $cloneUrl,
                    'default_branch' => $repo['mainbranch']['name'] ?? 'main',
                    'private' => ($repo['is_private'] ?? false),
                ];
            }

            $url = $data['next'] ?? null;
        } while ($url !== null);

        return $allRepos;
    }

    public function buildAuthenticatedCloneUrl(string $cloneUrl, string $token): string
    {
        $parsed = parse_url($cloneUrl);

        if ($parsed === false || ! isset($parsed['host'])) {
            return $cloneUrl;
        }

        $scheme = $parsed['scheme'] ?? 'https';

        return $scheme.'://x-token-auth:'.$token.'@'.$parsed['host'].($parsed['path'] ?? '');
    }

    public function requiredScopesHelpText(): string
    {
        return 'Create an app password at https://bitbucket.org/account/settings/app-passwords/ with permission: Repositories (Read). Note: Bitbucket calls these "App Passwords," not Personal Access Tokens.';
    }
}
