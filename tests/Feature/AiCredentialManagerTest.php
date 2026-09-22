<?php

use App\Models\AiProviderCredential;
use App\Models\Project;
use App\Models\User;
use App\Services\AiCredentialManager;
use Illuminate\Support\Facades\DB;

test('stored key is encrypted at rest and decrypts back', function () {
    $manager = app(AiCredentialManager::class);

    $manager->put('openai', ['OPENAI_API_KEY' => 'sk-live-secret-1234']);

    $credential = AiProviderCredential::where('provider', 'openai')->first();

    $this->assertModelExists($credential);
    expect($credential->api_key)->toBe('sk-live-secret-1234')
        ->and($credential->key_hint)->toBe('••••1234');

    $raw = DB::table('ai_provider_credentials')->where('provider', 'openai')->value('api_key');
    expect($raw)->not->toContain('sk-live-secret-1234');
});

test('put injects credentials into runtime config', function () {
    $manager = app(AiCredentialManager::class);

    $manager->put('openai', [
        'OPENAI_API_KEY' => 'sk-test-9999',
        'OPENAI_URL' => 'https://proxy.example.com/v1',
    ]);

    expect(config('ai.providers.openai.key'))->toBe('sk-test-9999')
        ->and(config('ai.providers.openai.url'))->toBe('https://proxy.example.com/v1')
        ->and($manager->isConnected('openai'))->toBeTrue();
});

test('missing credential resolves to null key', function () {
    $manager = app(AiCredentialManager::class);

    $manager->syncConfig();

    expect(config('ai.providers.groq.key'))->toBeNull()
        ->and($manager->isConnected('groq'))->toBeFalse();
});

test('azure fields land in key url and extra config', function () {
    $manager = app(AiCredentialManager::class);

    $manager->put('azure', [
        'AZURE_OPENAI_API_KEY' => 'azure-secret',
        'AZURE_OPENAI_URL' => 'https://my-resource.openai.azure.com/',
        'AZURE_OPENAI_API_VERSION' => '2025-01-01',
        'AZURE_OPENAI_DEPLOYMENT' => 'my-deploy',
    ]);

    expect(config('ai.providers.azure.key'))->toBe('azure-secret')
        ->and(config('ai.providers.azure.url'))->toBe('https://my-resource.openai.azure.com/')
        ->and(config('ai.providers.azure.api_version'))->toBe('2025-01-01')
        ->and(config('ai.providers.azure.deployment'))->toBe('my-deploy')
        ->and($manager->isConnected('azure'))->toBeTrue();
});

test('bedrock access keys are stored encrypted and applied', function () {
    $manager = app(AiCredentialManager::class);

    $manager->put('bedrock', [
        'AWS_ACCESS_KEY_ID' => 'AKIAEXAMPLE',
        'AWS_SECRET_ACCESS_KEY' => 'bedrock-secret',
        'AWS_BEDROCK_REGION' => 'eu-west-1',
    ]);

    $credential = AiProviderCredential::where('provider', 'bedrock')->first();

    expect($credential->extra['access_key_id'])->toBe('AKIAEXAMPLE')
        ->and($credential->extra['secret_access_key'])->toBe('bedrock-secret')
        ->and(config('ai.providers.bedrock.access_key_id'))->toBe('AKIAEXAMPLE')
        ->and(config('ai.providers.bedrock.region'))->toBe('eu-west-1')
        ->and($manager->isConnected('bedrock'))->toBeTrue();

    $raw = DB::table('ai_provider_credentials')->where('provider', 'bedrock')->value('extra');
    expect($raw)->not->toContain('bedrock-secret');
});

test('partial update preserves untouched fields', function () {
    $manager = app(AiCredentialManager::class);

    $manager->put('openai', [
        'OPENAI_API_KEY' => 'sk-original',
        'OPENAI_URL' => 'https://proxy.example.com/v1',
    ]);

    // Rotate only the key — base URL not submitted, must be preserved.
    $manager->put('openai', ['OPENAI_API_KEY' => 'sk-rotated']);

    $credential = AiProviderCredential::where('provider', 'openai')->first();

    expect($credential->api_key)->toBe('sk-rotated')
        ->and($credential->base_url)->toBe('https://proxy.example.com/v1')
        ->and(config('ai.providers.openai.url'))->toBe('https://proxy.example.com/v1');
});

test('remove clears runtime config and connection status', function () {
    $manager = app(AiCredentialManager::class);

    $manager->put('groq', ['GROQ_API_KEY' => 'gsk-test']);
    expect($manager->isConnected('groq'))->toBeTrue();

    $manager->remove('groq');

    expect(AiProviderCredential::where('provider', 'groq')->count())->toBe(0)
        ->and(config('ai.providers.groq.key'))->toBeNull()
        ->and($manager->isConnected('groq'))->toBeFalse();
});

test('settings page does not expose secrets in html', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    app(AiCredentialManager::class)->put('openai', ['OPENAI_API_KEY' => 'sk-super-secret-xyz']);

    $this->actingAs($user)
        ->get(route('projects.settings', ['project' => $project]))
        ->assertOk()
        ->assertDontSee('sk-super-secret-xyz');
});
