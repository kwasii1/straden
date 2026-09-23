<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createPageNotification(User $user, string $title): void
{
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\Notifications\RunCompleted',
        'data' => [
            'title' => $title,
            'body' => 'Body text',
            'url' => null,
            'icon' => 'bell',
        ],
    ]);
}

test('guests are redirected to the login page', function () {
    $this->get(route('notifications'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view the notifications page', function () {
    $user = User::factory()->create();
    createPageNotification($user, 'Run passed');

    $this->actingAs($user)
        ->get(route('notifications'))
        ->assertOk()
        ->assertSee('Notifications')
        ->assertSee('Run passed');
});

test('page lists notifications for the current user only', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    createPageNotification($user, 'Mine');
    createPageNotification($other, 'Not mine');

    $this->actingAs($user)
        ->get(route('notifications'))
        ->assertOk()
        ->assertSee('Mine')
        ->assertDontSee('Not mine');
});

test('mark all as read clears the unread notifications', function () {
    $user = User::factory()->create();
    createPageNotification($user, 'Alpha');
    createPageNotification($user, 'Bravo');

    Livewire::actingAs($user)
        ->test('pages::main-dashboard.notifications')
        ->call('markAllAsRead');

    expect($user->unreadNotifications()->count())->toBe(0);
});

test('visiting a notification marks it read and redirects', function () {
    $user = User::factory()->create();
    createPageNotification($user, 'Alpha');

    $notification = $user->notifications()->first();

    Livewire::actingAs($user)
        ->test('pages::main-dashboard.notifications')
        ->call('visit', $notification->id, '/dashboard')
        ->assertRedirect('/dashboard');

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('deleting a notification removes it', function () {
    $user = User::factory()->create();
    createPageNotification($user, 'Alpha');

    $notification = $user->notifications()->first();

    Livewire::actingAs($user)
        ->test('pages::main-dashboard.notifications')
        ->call('delete', $notification->id);

    expect($user->notifications()->count())->toBe(0);
});
