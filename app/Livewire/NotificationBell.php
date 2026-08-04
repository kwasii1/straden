<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Attributes\Computed;
use Livewire\Component;

class NotificationBell extends Component
{
    #[Computed]
    public function unreadCount(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    #[Computed]
    public function recentNotifications(): Collection
    {
        return auth()->user()->notifications()->limit(5)->get();
    }

    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function markAsReadAndVisit(string $id, ?string $url = null): void
    {
        auth()->user()->notifications()
            ->whereKey($id)
            ->first()
            ?->markAsRead();

        if ($url) {
            $this->redirect($url, navigate: true);
        }
    }

    public function render(): View
    {
        return view('livewire.notification-bell');
    }
}
