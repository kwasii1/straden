<?php

use App\Ai\Agents\RunInsightAgent;
use App\Jobs\GenerateRunInsightJob;
use App\Models\Project;
use App\Models\Run;
use App\Models\RunInsight;
use App\Models\Script;
use App\Models\Test;
use App\Models\User;
use App\Services\AiCredentialManager;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function makeInsightsRun(string $status = 'failed'): Run
{
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    return Run::factory()->{$status}()->create(['script_id' => $script->id]);
}

function connectInsightsProvider(string $provider = 'openai', string $key = 'sk-test-1234'): void
{
    app(AiCredentialManager::class)->put($provider, match ($provider) {
        'openai' => ['OPENAI_API_KEY' => $key],
        'anthropic' => ['ANTHROPIC_API_KEY' => $key],
        default => ['OPENAI_API_KEY' => $key],
    });
}

test('insights selection round-trips through the manager', function () {
    $manager = app(AiCredentialManager::class);

    expect($manager->getInsightsSelection())->toBeNull()
        ->and($manager->isInsightsSelectionUsable())->toBeFalse();

    $manager->setInsightsSelection('openai', 'gpt-4o');

    expect($manager->getInsightsSelection())->toBe(['provider' => 'openai', 'model' => 'gpt-4o'])
        // Provider not connected yet, so not usable.
        ->and($manager->isInsightsSelectionUsable())->toBeFalse();

    connectInsightsProvider('openai');

    expect($manager->isInsightsSelectionUsable())->toBeTrue();

    $manager->clearInsightsSelection();

    expect($manager->getInsightsSelection())->toBeNull();
});

test('insights selection rejects unknown providers and blank models', function () {
    $manager = app(AiCredentialManager::class);

    expect(fn () => $manager->setInsightsSelection('not-a-provider', 'some-model'))
        ->toThrow(ValidationException::class);

    expect(fn () => $manager->setInsightsSelection('openai', '  '))
        ->toThrow(ValidationException::class);

    expect($manager->getInsightsSelection())->toBeNull();
});

test('insights model page saves the insights model', function () {
    $user = User::factory()->admin()->create();
    $project = Project::factory()->create();
    connectInsightsProvider('openai');

    Livewire::actingAs($user)
        ->test('pages::settings.insights-model')
        ->set('insightsProvider', 'openai')
        ->set('insightsModel', 'gpt-4o')
        ->call('saveInsightsModel')
        ->assertHasNoErrors();

    expect(app(AiCredentialManager::class)->getInsightsSelection())
        ->toBe(['provider' => 'openai', 'model' => 'gpt-4o']);
});

test('the insights model form starts from and resets to the stored selection', function () {
    $user = User::factory()->admin()->create();
    $project = Project::factory()->create();
    connectInsightsProvider('openai');
    app(AiCredentialManager::class)->setInsightsSelection('openai', 'gpt-4o');

    Livewire::actingAs($user)
        ->test('pages::settings.insights-model')
        ->assertSet('insightsProvider', 'openai')
        ->assertSet('insightsModel', 'gpt-4o')
        ->set('insightsProvider', 'anthropic')
        ->set('insightsModel', 'claude-sonnet-4')
        ->call('resetForm')
        ->assertSet('insightsProvider', 'openai')
        ->assertSet('insightsModel', 'gpt-4o');
});

test('insights model page renders within the settings navigation', function () {
    $user = User::factory()->admin()->create();
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.insights-model'))
        ->assertOk()
        ->assertSee(['AI insights model', 'AI integrations', 'API tokens']);
});

test('panel prompts to visit settings when no model is selected', function () {
    Queue::fake();

    $user = User::factory()->admin()->create();
    $run = makeInsightsRun('passed');

    Livewire::actingAs($user)
        ->test('run-insight-panel', ['run' => $run])
        ->assertSee('No insights model selected')
        ->assertSee('Go to Settings')
        ->call('generate');

    expect($run->insight)->toBeNull();
    Queue::assertNotPushed(GenerateRunInsightJob::class);
});

test('panel generates once a usable model is selected', function () {
    Queue::fake();

    $user = User::factory()->admin()->create();
    $run = makeInsightsRun('passed');
    connectInsightsProvider('openai');
    app(AiCredentialManager::class)->setInsightsSelection('openai', 'gpt-4o');

    Livewire::actingAs($user)
        ->test('run-insight-panel', ['run' => $run])
        ->assertSee('Using gpt-4o')
        ->call('generate');

    expect($run->insight)->not->toBeNull();
    Queue::assertPushed(GenerateRunInsightJob::class);
});

test('panel prompts when the selected provider is disconnected', function () {
    Queue::fake();

    $user = User::factory()->admin()->create();
    $run = makeInsightsRun('passed');
    app(AiCredentialManager::class)->setInsightsSelection('openai', 'gpt-4o');

    Livewire::actingAs($user)
        ->test('run-insight-panel', ['run' => $run])
        ->assertSee('Insights provider not connected')
        ->call('generate');

    Queue::assertNotPushed(GenerateRunInsightJob::class);
});

test('job uses the selected provider and model', function () {
    $run = makeInsightsRun();
    connectInsightsProvider('openai', 'sk-test-1234');
    app(AiCredentialManager::class)->setInsightsSelection('openai', 'gpt-4o');

    RunInsightAgent::fake([[
        'summary' => 'All good.',
        'overall_health' => 'healthy',
        'what_is_slow' => 'Nothing.',
        'key_findings' => [],
        'recommendations' => [],
        'script_observations' => 'Fine.',
    ]]);

    $insight = RunInsight::factory()->queued()->create(['run_id' => $run->id]);

    (new GenerateRunInsightJob($run->id, $insight->id))->handle();

    expect($insight->refresh()->status)->toBe('completed');

    RunInsightAgent::assertPrompted(
        fn ($prompt) => $prompt->model === 'gpt-4o' && $prompt->provider->name() === 'openai'
    );
});

test('job falls back to agent defaults when nothing is selected', function () {
    $run = makeInsightsRun();

    RunInsightAgent::fake([[
        'summary' => 'All good.',
        'overall_health' => 'healthy',
        'what_is_slow' => 'Nothing.',
        'key_findings' => [],
        'recommendations' => [],
        'script_observations' => 'Fine.',
    ]]);

    $insight = RunInsight::factory()->queued()->create(['run_id' => $run->id]);

    (new GenerateRunInsightJob($run->id, $insight->id))->handle();

    expect($insight->refresh()->status)->toBe('completed');
});

test('job fails with a settings hint when the selected provider is disconnected', function () {
    $run = makeInsightsRun();
    app(AiCredentialManager::class)->setInsightsSelection('openai', 'gpt-4o');

    $insight = RunInsight::factory()->queued()->create(['run_id' => $run->id]);

    (new GenerateRunInsightJob($run->id, $insight->id))->handle();

    $insight->refresh();

    expect($insight->status)->toBe('failed');
    expect($insight->error)->toContain('not connected');
});

test('insights model page renders searchable comboboxes', function () {
    connectInsightsProvider('openai');
    app(AiCredentialManager::class)->setInsightsSelection('openai', 'gpt-4o');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('settings.insights-model'))
        ->assertOk()
        ->assertSee(['Search providers...', 'Search or type a model name...'])
        ->assertDontSee('<datalist', false);
});
