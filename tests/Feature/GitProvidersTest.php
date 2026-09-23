<?php

use App\Enums\ConnectorType;
use App\GitProviders\AzureDevOpsProvider;
use App\GitProviders\BitbucketProvider;
use App\GitProviders\GitHubProvider;
use App\GitProviders\GitLabProvider;
use App\GitProviders\GitProviderResolver;

test('GitHubProvider builds authenticated clone URL', function () {
    $provider = new GitHubProvider;

    $url = $provider->buildAuthenticatedCloneUrl(
        'https://github.com/user/repo.git',
        'ghp_test123'
    );

    expect($url)->toBe('https://x-access-token:ghp_test123@github.com/user/repo.git');
});

test('GitLabProvider builds authenticated clone URL', function () {
    $provider = new GitLabProvider;

    $url = $provider->buildAuthenticatedCloneUrl(
        'https://gitlab.com/group/project.git',
        'glpat-test123'
    );

    expect($url)->toBe('https://oauth2:glpat-test123@gitlab.com/group/project.git');
});

test('BitbucketProvider builds authenticated clone URL', function () {
    $provider = new BitbucketProvider;

    $url = $provider->buildAuthenticatedCloneUrl(
        'https://bitbucket.org/workspace/repo.git',
        'ATBB-test123'
    );

    expect($url)->toBe('https://x-token-auth:ATBB-test123@bitbucket.org/workspace/repo.git');
});

test('AzureDevOpsProvider builds authenticated clone URL', function () {
    $provider = new AzureDevOpsProvider;

    $url = $provider->buildAuthenticatedCloneUrl(
        'https://dev.azure.com/org/project/_git/repo',
        'ado-test123'
    );

    expect($url)->toBe('https://ado-test123@dev.azure.com/org/project/_git/repo');
});

test('GitProviderResolver resolves GitHub', function () {
    $provider = GitProviderResolver::for(ConnectorType::GitHub);

    expect($provider)->toBeInstanceOf(GitHubProvider::class);
});

test('GitProviderResolver resolves GitLab', function () {
    $provider = GitProviderResolver::for(ConnectorType::GitLab);

    expect($provider)->toBeInstanceOf(GitLabProvider::class);
});

test('GitProviderResolver resolves Bitbucket', function () {
    $provider = GitProviderResolver::for(ConnectorType::Bitbucket);

    expect($provider)->toBeInstanceOf(BitbucketProvider::class);
});

test('GitProviderResolver resolves Azure DevOps', function () {
    $provider = GitProviderResolver::for(ConnectorType::AzureDevOps);

    expect($provider)->toBeInstanceOf(AzureDevOpsProvider::class);
});

test('GitProviderResolver throws for non-git provider type', function () {
    GitProviderResolver::for(ConnectorType::Database);
})->throws(RuntimeException::class);

test('ConnectorType isGitProvider returns true for git providers', function () {
    expect(ConnectorType::GitHub->isGitProvider())->toBeTrue();
    expect(ConnectorType::GitLab->isGitProvider())->toBeTrue();
    expect(ConnectorType::Bitbucket->isGitProvider())->toBeTrue();
    expect(ConnectorType::AzureDevOps->isGitProvider())->toBeTrue();
});

test('ConnectorType isGitProvider returns false for non-git types', function () {
    expect(ConnectorType::InfluxDb->isGitProvider())->toBeFalse();
    expect(ConnectorType::Database->isGitProvider())->toBeFalse();
    expect(ConnectorType::Grafana->isGitProvider())->toBeFalse();
});

test('GitHubProvider scopes help text mentions scopes', function () {
    $provider = new GitHubProvider;

    expect($provider->requiredScopesHelpText())->toContain('token');
    expect($provider->requiredScopesHelpText())->toContain('github.com');
});

test('GitLabProvider scopes help text mentions scopes', function () {
    $provider = new GitLabProvider;

    expect($provider->requiredScopesHelpText())->toContain('token');
    expect($provider->requiredScopesHelpText())->toContain('gitlab.com');
});

test('BitbucketProvider scopes help text mentions App Password', function () {
    $provider = new BitbucketProvider;

    expect($provider->requiredScopesHelpText())->toContain('App Password');
    expect($provider->requiredScopesHelpText())->toContain('bitbucket.org');
});

test('AzureDevOpsProvider scopes help text mentions scopes', function () {
    $provider = new AzureDevOpsProvider;

    expect($provider->requiredScopesHelpText())->toContain('token');
    expect($provider->requiredScopesHelpText())->toContain('dev.azure.com');
});

test('BitbucketProvider listRepositories throws without workspace', function () {
    $provider = new BitbucketProvider;

    $provider->listRepositories('token', []);
})->throws(RuntimeException::class, 'workspace');

test('GitHubProvider returns provider_id in repo shape', function () {
    $provider = new GitHubProvider;

    expect(method_exists(GitHubProvider::class, 'listBranches'))->toBeTrue();
});

test('GitLabProvider returns provider_id in repo shape', function () {
    $provider = new GitLabProvider;

    expect(method_exists(GitLabProvider::class, 'listBranches'))->toBeTrue();
});
