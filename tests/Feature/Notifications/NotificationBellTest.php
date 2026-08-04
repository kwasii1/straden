<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createBellNotification(User $user, string $title, ?string $id = null, ?int $minutesAgo = null): void
{
    $user->notifications()->create([
        'id' => $id ?? (string) Str::uuid(),
        'type' => 'App\Notifications\RunCompleted',
        'data' => [
            'title' => $title,
            'body' => 'Body text',
            'url' => null,
            'icon' => 'bell',
        ],
        'created_at' => $minutesAgo === null ? now() : now()->subMinutes($minutesAgo),
        'updated_at' => $minutesAgo === null ? now() : now()->subMinutes($minutesAgo),
    ]);
}

test('bell shows an unread count badge', function () {
    $user = User::factory()->create();
    createBellNotification($user, 'Alpha');
    createBellNotification($user, 'Bravo');

    Livewire::actingAs($user)
        ->test('notification-bell')
        ->assertSeeHtml('bg-red-600')
        ->assertSee('2')
        ->assertSee('Mark all as read');
});

test('bell lists only the five most recent notifications', function () {
    $user = User::factory()->create();

    foreach (range(1, 7) as $i) {
        createBellNotification($user, "Notification {$i}", minutesAgo: 8 - $i);
    }

    Livewire::actingAs($user)
        ->test('notification-bell')
        ->assertSee('Notification 7')
        ->assertSee('Notification 6')
        ->assertSee('Notification 5')
        ->assertSee('Notification 4')
        ->assertSee('Notification 3')
        ->assertDontSee('Notification 2')
        ->assertDontSee('Notification 1');
});

test('bell hides the badge and mark-all action when there are no unread notifications', function () {
    $user = User::factory()->create();
    createBellNotification($user, 'Alpha');
    $user->unreadNotifications->markAsRead();

    Livewire::actingAs($user)
        ->test('notification-bell')
        ->assertDontSeeHtml('bg-red-600')
        ->assertDontSee('Mark all as read');
});

test('mark all as read clears the unread notifications', function () {
    $user = User::factory()->create();
    createBellNotification($user, 'Alpha');
    createBellNotification($user, 'Bravo');

    Livewire::actingAs($user)
        ->test('notification-bell')
        ->call('markAllAsRead');

    expect($user->unreadNotifications()->count())->toBe(0);
});

test('marking a notification as read redirects to its url', function () {
    $user = User::factory()->create();
    $notification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\Notifications\RunCompleted',
        'data' => [
            'title' => 'Alpha',
            'body' => 'Body text',
            'url' => '/notifications',
            'icon' => 'bell',
        ],
    ]);

    Livewire::actingAs($user)
        ->test('notification-bell')
        ->call('markAsReadAndVisit', $notification->id, '/notifications')
        ->assertRedirect('/notifications');

    expect($user->unreadNotifications()->count())->toBe(0);
});
