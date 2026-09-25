<?php

use App\Enums\ConnectorType;
use App\Models\Connector;
use App\Models\User;
use Livewire\Livewire;

test('every page redirects to setup until the first user exists', function () {
    $this->markSetupIncomplete();

    $this->get('/login')->assertRedirect(route('setup'));
    $this->get(route('setup'))->assertOk()->assertSee('Welcome to Straden');
});

test('setup creates a verified admin and signs them in', function () {
    $this->markSetupIncomplete();

    Livewire::test('pages::auth.setup')
        ->set('name', 'Ada Admin')
        ->set('email', 'ada@example.com')
        ->set('password', 'secret-password-123')
        ->set('password_confirmation', 'secret-password-123')
        ->call('createAdmin')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $user = User::sole();

    expect($user->is_admin)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user);
});

test('setup is unavailable once a user exists', function () {
    $this->markSetupIncomplete();
    User::factory()->create();

    $this->get(route('setup'))->assertNotFound();
    $this->get('/login')->assertOk();
});

test('bootstrap creates the admin from env on first boot only', function () {
    config([
        'straden.admin.email' => 'root@example.com',
        'straden.admin.password' => 'env-password-123',
    ]);

    $this->artisan('straden:bootstrap')->assertSuccessful();

    $admin = User::sole();
    expect($admin->email)->toBe('root@example.com')
        ->and($admin->is_admin)->toBeTrue()
        ->and(Connector::query()->where('type', ConnectorType::InfluxDb)->where('is_system', true)->exists())->toBeTrue();

    config(['straden.admin.email' => 'other@example.com']);
    $this->artisan('straden:bootstrap')->assertSuccessful();

    expect(User::count())->toBe(1);
});

test('bootstrap leaves admin creation to the wizard when env is empty', function () {
    $this->artisan('straden:bootstrap')->assertSuccessful();

    expect(User::count())->toBe(0);
});

test('straden:admin promotes an existing user and resets their password', function () {
    $user = User::factory()->create(['email' => 'dev@example.com']);

    $this->artisan('straden:admin', ['email' => 'dev@example.com', '--password' => 'new-password-123'])
        ->assertSuccessful();

    $user->refresh();
    expect($user->is_admin)->toBeTrue()
        ->and(Hash::check('new-password-123', $user->password))->toBeTrue();
});

test('login records the last sign-in time', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    expect($user->fresh()->last_login_at)->not->toBeNull();
});
