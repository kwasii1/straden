<?php

namespace App\GitProviders;

use Illuminate\Support\Facades\Http;

class AzureDevOpsProvider implements GitProvider
{
    private const API_BASE = 'https://dev.azure.com';

    public function validateToken(string $token, array $context = []): bool
    {
        $organization = $context['organization'] ?? '';

        if (empty($organization)) {
            return false;
        }

        $response = Http::withBasicAuth('', $token)
            ->get(self::API_BASE.'/'.$organization.'/_apis/projects', [
                'api-version' => '7.1',
            ]);

        return $response->successful();
    }

    public function listRepositories(string $token, array $context = []): array
    {
        $organization = $context['organization'] ?? '';
        $project = $context['project'] ?? '';

        if (empty($organization)) {
            throw new \RuntimeException('Azure DevOps requires an organization to list repositories.');
        }

        if (empty($project)) {
            return $this->listProjects($token, $organization);
        }

        return $this->listProjectRepositories($token, $organization, $project);
    }

    public function buildAuthenticatedCloneUrl(string $cloneUrl, string $token): string
    {
        $parsed = parse_url($cloneUrl);

        if ($parsed === false || ! isset($parsed['host'])) {
            return $cloneUrl;
        }

        $scheme = $parsed['scheme'] ?? 'https';

        return $scheme.'://'.$token.'@'.$parsed['host'].($parsed['path'] ?? '');
    }

    public function requiredScopesHelpText(): string
    {
        return 'Create a token at https://dev.azure.com/{org}/_usersSettings/tokens with scope: Code (Read). Replace {org} with your organization name.';
    }

    private function listProjects(string $token, string $organization): array
    {
        $allProjects = [];

        $response = Http::withBasicAuth('', $token)
            ->get(self::API_BASE.'/'.$organization.'/_apis/projects', [
                'api-version' => '7.1',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Failed to list Azure DevOps projects: '.$response->body());
        }

        foreach ($response->json()['value'] ?? [] as $project) {
            $allProjects[] = [
                'name' => $project['name'],
                'description' => $project['description'] ?? '',
            ];
        }

        return $allProjects;
    }

    private function listProjectRepositories(string $token, string $organization, string $project): array
    {
        $allRepos = [];

        $response = Http::withBasicAuth('', $token)
            ->get(self::API_BASE.'/'.$organization.'/'.$project.'/_apis/git/repositories', [
                'api-version' => '7.1',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Failed to list Azure DevOps repositories: '.$response->body());
        }

        foreach ($response->json()['value'] ?? [] as $repo) {
            $allRepos[] = [
                'full_name' => $project.'/'.$repo['name'],
                'clone_url' => $repo['remoteUrl'] ?? $repo['webUrl'] ?? '',
                'default_branch' => $repo['defaultBranch'] ?? 'main',
                'private' => ($repo['project']['visibility'] ?? 'private') !== 'public',
            ];
        }

        return $allRepos;
    }
}
