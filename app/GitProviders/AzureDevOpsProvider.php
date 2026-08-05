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

    public function listBranches(string $token, array $repo, array $context = []): array
    {
        $organization = $context['organization'] ?? '';
        $project = $repo['project'] ?? '';
        $repoId = $repo['provider_id'] ?? '';

        if (empty($organization) || empty($project) || empty($repoId)) {
            throw new \RuntimeException('Azure DevOps requires organization, project, and repository ID to list branches.');
        }

        $response = Http::withBasicAuth('', $token)
            ->get(self::API_BASE.'/'.$organization.'/'.$project.'/_apis/git/repositories/'.$repoId.'/refs', [
                'filter' => 'heads/',
                'api-version' => '7.1',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Failed to list branches: '.$response->body());
        }

        $branches = [];

        foreach ($response->json()['value'] ?? [] as $ref) {
            $name = $ref['name'] ?? '';

            if (str_starts_with($name, 'refs/heads/')) {
                $branches[] = ['name' => substr($name, 11)];
            }
        }

        return $branches;
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
        return 'Create a personal access token at <code>https://dev.azure.com/{org}/_usersSettings/tokens</code> with <strong>Code (Read)</strong> scope. Replace <code>{org}</code> with your organization name.';
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
                'provider_id' => $repo['id'],
                'project' => $project,
            ];
        }

        return $allRepos;
    }
}
