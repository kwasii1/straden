<?php

use App\Models\Project;
use App\Models\Run;
use App\Models\Script;
use App\Models\Test;
use App\Models\User;
use App\Notifications\RunCompleted;
use App\Notifications\RunInsightFailed;
use App\Notifications\RunInsightReady;
use App\Notifications\ScriptGenerationCompleted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

function makeNotificationRun(User $user, string $status = 'failed'): Run
{
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    return Run::factory()->{$status}()->create([
        'script_id' => $script->id,
        'triggered_by_user_id' => $user->id,
    ]);
}

test('notifications are delivered through database and broadcast channels', function () {
    $user = User::factory()->create();
    $run = makeNotificationRun($user);

    $notification = new RunCompleted($run->id);

    expect($notification->via($user))->toBe(['database', 'broadcast']);
});

test('queued notifications run on the notifications queue', function () {
    Bus::fake();

    $user = User::factory()->create();
    $run = makeNotificationRun($user);

    $user->notify(new RunCompleted($run->id));

    Bus::assertDispatched(SendQueuedNotifications::class, function ($job) {
        return $job->notification instanceof RunCompleted
            && $job->queue === 'notifications';
    });
});

test('run completed notification stores a database record with display data', function () {
    $user = User::factory()->create();
    $run = makeNotificationRun($user);

    $user->notifyNow(new RunCompleted($run->id));

    $notification = $user->notifications()->first();

    expect($notification)->not->toBeNull();
    expect($notification->type)->toBe(RunCompleted::class);
    expect($notification->read_at)->toBeNull();
    expect($notification->data['title'])->toBe('Run failed');
    expect($notification->data['body'])->toContain($run->script->name);
    expect($notification->data['url'])->toBe(route('projects.runs.view', ['project' => $run->script->test->project, 'run' => $run]));
    expect($notification->data['icon'])->toBe('x-circle');
});

test('run completed notification uses a passing icon and title', function () {
    $user = User::factory()->create();
    $run = makeNotificationRun($user, 'passed');

    $user->notifyNow(new RunCompleted($run->id));

    $notification = $user->notifications()->first();

    expect($notification->data['title'])->toBe('Run passed');
    expect($notification->data['icon'])->toBe('check-circle');
});

test('insight notifications carry run context', function () {
    $user = User::factory()->create();
    $run = makeNotificationRun($user);

    $ready = (new RunInsightReady($run->id))->toArray($user);
    expect($ready['title'])->toBe('Run insights ready');
    expect($ready['url'])->toBe(route('projects.runs.view', ['project' => $run->script->test->project, 'run' => $run]));

    $failed = (new RunInsightFailed($run->id, 'AI provider down'))->toArray($user);
    expect($failed['title'])->toBe('Run insights failed');
    expect($failed['error'])->toBe('AI provider down');
});

test('script generation notification carries script context', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $test = Test::factory()->create(['project_id' => $project->id]);
    $script = Script::factory()->create(['test_id' => $test->id]);

    $data = (new ScriptGenerationCompleted($script->id, $test->id))->toArray($user);

    expect($data['title'])->toBe('Script generation complete');
    expect($data['body'])->toContain($script->name);
    expect($data['url'])->toBe(route('projects.view-test-script', [
        'project' => $project,
        'test' => $test,
        'script' => $script,
    ]));
});
