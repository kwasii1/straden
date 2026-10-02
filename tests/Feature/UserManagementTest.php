<?php

use App\Models\User;
use Livewire\Livewire;

test('only admins can open user management', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('settings.users'))
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('settings.users'))
        ->assertOk()
        ->assertSee('Users');
});

test('non-admins cannot call user management actions', function () {
    $member = User::factory()->create();

    Livewire::actingAs($member)
        ->test('pages::settings.users')
        ->assertForbidden();
});

test('admins can create a user with a generated password', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::settings.users')
        ->set('name', 'New Person')
        ->set('email', 'new@example.com')
        ->set('isAdmin', false)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('revealedFor', 'new@example.com')
        ->assertSet('revealedPassword', fn ($password) => strlen($password) === 16);

    $user = User::where('email', 'new@example.com')->sole();
    expect($user->is_admin)->toBeFalse()
        ->and($user->email_verified_at)->not->toBeNull();
});

test('admins can create a user with a chosen password', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::settings.users')
        ->set('name', 'New Admin')
        ->set('email', 'boss@example.com')
        ->set('isAdmin', true)
        ->set('generatePassword', false)
        ->set('password', 'chosen-password-123')
        ->set('password_confirmation', 'chosen-password-123')
        ->call('save')
        ->assertHasNoErrors();

    $user = User::where('email', 'boss@example.com')->sole();
    expect($user->is_admin)->toBeTrue()
        ->and(Hash::check('chosen-password-123', $user->password))->toBeTrue();
});

test('admins can edit users and reset passwords', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $oldHash = $user->password;

    Livewire::actingAs($admin)
        ->test('pages::settings.users')
        ->call('startEdit', $user->id)
        ->set('name', 'Renamed')
        ->set('isAdmin', true)
        ->call('save')
        ->assertHasNoErrors()
        ->call('resetPassword', $user->id)
        ->assertSet('revealedFor', $user->email);

    $user->refresh();
    expect($user->name)->toBe('Renamed')
        ->and($user->is_admin)->toBeTrue()
        ->and($user->password)->not->toBe($oldHash);
});

test('admins cannot demote themselves or delete themselves', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::settings.users')
        ->call('startEdit', $admin->id)
        ->set('isAdmin', false)
        ->call('save')
        ->assertHasErrors('isAdmin')
        ->call('deleteUser', $admin->id);

    expect($admin->fresh())->not->toBeNull()
        ->and($admin->fresh()->is_admin)->toBeTrue();
});

test('the last admin cannot be demoted', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();

    // Demote the other admin, leaving $admin as the only one.
    Livewire::actingAs($admin)
        ->test('pages::settings.users')
        ->call('startEdit', $other->id)
        ->set('isAdmin', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(User::where('is_admin', true)->count())->toBe(1);
});

test('admins can delete other users', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $user->createToken('agent');

    Livewire::actingAs($admin)
        ->test('pages::settings.users')
        ->call('deleteUser', $user->id);

    expect($user->fresh())->toBeNull();
});

test('members cannot open instance-wide settings, horizon or the log viewer', function (string $url) {
    $this->actingAs(User::factory()->create())
        ->get($url)
        ->assertForbidden();
})->with([
    'AI integrations' => fn () => route('settings.ai-integrations'),
    'AI insights model' => fn () => route('settings.insights-model'),
    'Horizon' => fn () => route('horizon.index'),
    'Log viewer' => fn () => route('log-viewer.index'),
]);

test('admins can open instance-wide settings, horizon and the log viewer', function (string $url) {
    $this->actingAs(User::factory()->admin()->create())
        ->get($url)
        ->assertOk();
})->with([
    'AI integrations' => fn () => route('settings.ai-integrations'),
    'AI insights model' => fn () => route('settings.insights-model'),
    'Horizon' => fn () => route('horizon.index'),
    'Log viewer' => fn () => route('log-viewer.index'),
]);

test('members still manage their own API tokens and only see them in settings', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('settings.api-tokens'))
        ->assertOk()
        ->assertSee('API tokens')
        ->assertDontSee('AI integrations')
        ->assertDontSee(route('horizon.index'));
});
