<?php

namespace App\GitProviders;

interface GitProvider
{
    public function validateToken(string $token, array $context = []): bool;

    public function listRepositories(string $token, array $context = []): array;

    public function listBranches(string $token, array $repo, array $context = []): array;

    public function buildAuthenticatedCloneUrl(string $cloneUrl, string $token): string;

    public function requiredScopesHelpText(): string;
}
