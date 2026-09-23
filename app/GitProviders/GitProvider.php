<?php

namespace App\GitProviders;

interface GitProvider
{
    /** @param array{workspace?: string, organization?: string, project?: string, username?: string} $context */
    public function validateToken(string $token, array $context = []): bool;

    /**
     * @param  array{workspace?: string, organization?: string, project?: string, username?: string}  $context
     * @return array<int, array<string, mixed>>
     */
    public function listRepositories(string $token, array $context = []): array;

    /**
     * @param  array<string, mixed>  $repo
     * @param  array{workspace?: string, organization?: string, project?: string, username?: string}  $context
     * @return array<int, array<string, mixed>>
     */
    public function listBranches(string $token, array $repo, array $context = []): array;

    public function buildAuthenticatedCloneUrl(string $cloneUrl, string $token): string;

    public function requiredScopesHelpText(): string;
}
