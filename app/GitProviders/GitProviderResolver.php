<?php

namespace App\GitProviders;

use App\Enums\ConnectorType;
use RuntimeException;

class GitProviderResolver
{
    public static function for(ConnectorType $type): GitProvider
    {
        return match ($type) {
            ConnectorType::GitHub => new GitHubProvider,
            ConnectorType::GitLab => new GitLabProvider,
            ConnectorType::Bitbucket => new BitbucketProvider,
            ConnectorType::AzureDevOps => new AzureDevOpsProvider,
            default => throw new RuntimeException("No GitProvider implementation for connector type: {$type->value}"),
        };
    }
}
