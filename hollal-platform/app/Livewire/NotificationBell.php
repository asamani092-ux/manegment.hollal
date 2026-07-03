<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class NotificationBell extends Component
{
    public bool $open = false;

    public function mount(): void
    {
        //
    }

    public function toggleDropdown(): void
    {
        $this->open = ! $this->open;
    }

    public function markAsRead(string $notificationId): void
    {
        $notification = auth()->user()->notifications()->where('id', $notificationId)->first();

        $notification?->markAsRead();
    }

    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function render(): View
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return view('livewire.notification-bell', [
            'notifications' => $user->notifications()->latest()->limit(10)->get(),
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }
}
