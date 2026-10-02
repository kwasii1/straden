<?php

use App\Ai\Agents\TestAgent;
use App\Ai\Providers\AvailableModelMap;
use App\Livewire\AgentChat;
use App\Models\Project;
use App\Models\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Enums\Lab;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['ai.providers.deepseek.key' => 'test-deepseek-key']);
    config(['ai.providers.gemini.key' => 'test-gemini-key']);
});

test('switching provider resets the model to one of that provider', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);

    Livewire::actingAs($user)
        ->test(AgentChat::class, ['project' => $project, 'test' => $test])
        ->set('selectedProvider', 'gemini')
        ->assertSet('selectedProvider', 'gemini')
        ->assertOk();

    $component = Livewire::actingAs($user)
        ->test(AgentChat::class, ['project' => $project, 'test' => $test])
        ->set('selectedProvider', 'gemini');

    expect($component->get('selectedModel'))->toBeIn(AvailableModelMap::modelsFor('gemini'));
});

test('chosen model sticks after switching provider', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);

    $geminiModels = AvailableModelMap::modelsFor('gemini');
    $pick = $geminiModels[1] ?? $geminiModels[0];

    $component = Livewire::actingAs($user)
        ->test(AgentChat::class, ['project' => $project, 'test' => $test])
        ->set('selectedProvider', 'gemini')
        ->set('selectedModel', $pick);

    expect($component->get('selectedModel'))->toBe($pick);
});

test('gemini model list contains only real api ids', function () {
    $models = AvailableModelMap::modelsFor('gemini');

    expect($models)->toContain('gemini-2.5-flash')
        ->and($models)->toContain('gemini-3.7-flash')
        ->and($models)->not->toContain('gemini-3-flash')
        ->and($models)->not->toContain('gemini-3-pro')
        ->and($models)->not->toContain('gemini-3.1-flash-lite');

    foreach ($models as $model) {
        expect($model)->toMatch('/^gemini-\d/');
    }
});

test('deepseek model list contains only real api ids', function () {
    $models = AvailableModelMap::modelsFor('deepseek');

    expect($models)->toContain('deepseek-flash')
        ->and($models)->toContain('deepseek-v4-pro')
        ->and($models)->not->toContain('deepseek-chat')
        ->and($models)->not->toContain('deepseek-reasoner');
});

test('stale model is healed to the provider before dispatching', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);

    TestAgent::fake(['Done']);

    Livewire::actingAs($user)
        ->test(AgentChat::class, ['project' => $project, 'test' => $test])
        ->set('selectedProvider', 'gemini')
        // Simulate stale UI state: a deepseek model left over with gemini selected.
        ->set('selectedModel', 'deepseek-flash')
        ->set('input', 'Suggest a load test')
        ->call('submitMessage')
        ->assertSet('selectedProvider', 'gemini');

    TestAgent::assertQueued(function ($prompt) {
        return $prompt->provider === Lab::from('gemini')
            && in_array($prompt->model, AvailableModelMap::modelsFor('gemini'), true);
    });
});

test('default provider selection clears the model', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);

    Livewire::actingAs($user)
        ->test(AgentChat::class, ['project' => $project, 'test' => $test])
        ->set('selectedProvider', '')
        ->assertSet('selectedProvider', null)
        ->assertSet('selectedModel', null);
});

test('all models list puts featured first and includes models beyond the featured list', function () {
    $featured = AvailableModelMap::modelsFor('openrouter');
    $all = AvailableModelMap::allModelsFor('openrouter');

    expect(array_slice($all, 0, count($featured)))->toBe($featured)
        ->and(count($all))->toBeGreaterThan(count($featured));
});

test('catalog models are accepted as valid selections but unknown ones are not', function () {
    $catalogOnly = collect(AvailableModelMap::catalogFor('openrouter'))
        ->first(fn (string $model) => ! in_array($model, AvailableModelMap::modelsFor('openrouter'), true));

    expect(AvailableModelMap::isKnownModel('openrouter', $catalogOnly))->toBeTrue()
        ->and(AvailableModelMap::isKnownModel('openrouter', 'not/a-real-model'))->toBeFalse();
});
