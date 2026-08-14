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

    public function markAsReadAndGo(string $notificationId): void
    {
        $notification = auth()->user()->notifications()->where('id', $notificationId)->first();
        if (! $notification) {
            return;
        }

        $notification->markAsRead();

        $path = self::appPath($notification->data['url'] ?? null);
        if ($path !== null) {
            // Relative path keeps the current host/port (APP_URL=localhost caused black pages).
            $this->redirect($path);

            return;
        }

        $this->open = true;
    }

    /**
     * Normalize stored absolute/relative notification URLs to an in-app path.
     * Time: O(1) | Space: O(1)
     */
    public static function appPath(mixed $url): ?string
    {
        if (! is_string($url) || $url === '' || $url === '#') {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['path'])) {
            return null;
        }

        $path = $parts['path'];
        if (! empty($parts['query'])) {
            $path .= '?'.$parts['query'];
        }

        return $path;
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
