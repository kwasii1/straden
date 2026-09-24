<?php

use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

test('api tokens page can be rendered', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('settings.api-tokens'))
        ->assertOk()
        ->assertSee(['API Tokens', 'AI Integrations', 'AI Insights Model']);
});

test('users can create a token with selected abilities', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.api-tokens')
        ->set('name', 'Claude Code')
        ->set('abilities', ['mcp:read'])
        ->set('expiresIn', '30')
        ->call('createToken')
        ->assertHasNoErrors()
        ->assertSet('plainTextToken', fn ($token) => filled($token));

    $token = $user->tokens()->sole();

    expect($token->name)->toBe('Claude Code')
        ->and($token->abilities)->toBe(['mcp:read'])
        ->and($token->expires_at)->not->toBeNull();
});

test('users can revoke their tokens', function () {
    $user = User::factory()->create();
    $token = $user->createToken('agent', ['mcp:read'])->accessToken;

    Livewire::actingAs($user)
        ->test('pages::settings.api-tokens')
        ->call('revokeToken', (string) $token->id);

    expect($user->tokens()->count())->toBe(0);
});

test('users cannot revoke tokens belonging to others', function () {
    $token = User::factory()->create()->createToken('agent')->accessToken;

    Livewire::actingAs(User::factory()->create())
        ->test('pages::settings.api-tokens')
        ->call('revokeToken', (string) $token->id);

    expect($token->fresh())->not->toBeNull();
});

test('settings pages offer a way back to the project page the user came from', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $projectUrl = route('projects.runs', ['project' => $project]);

    $this->actingAs($user)
        ->from($projectUrl)
        ->get(route('settings.ai-integrations'))
        ->assertOk()
        ->assertSee($projectUrl, false);

    // Moving between settings sub-pages keeps the original return URL.
    $this->from(route('settings.ai-integrations'))
        ->get(route('settings.api-tokens'))
        ->assertSee($projectUrl, false);
});
