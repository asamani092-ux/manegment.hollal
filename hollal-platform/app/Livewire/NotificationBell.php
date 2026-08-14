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

        $path = self::resolveTarget($notification);
        if ($path !== null) {
            // Full page redirect keeps ?open= (Livewire navigate can drop query in some cases).
            $this->redirect($path, navigate: false);

            return;
        }

        $this->open = true;
    }

    /**
     * Prefer typed ids (task_id, …) over stale stored urls that omit ?open=.
     * Time: O(1) | Space: O(1)
     */
    public static function resolveTarget(\Illuminate\Notifications\DatabaseNotification $notification): ?string
    {
        $data = $notification->data;
        if (! empty($data['task_id']) && is_numeric($data['task_id'])) {
            return \App\Support\RecordUrl::task((int) $data['task_id']);
        }
        if (! empty($data['expense_id']) && is_numeric($data['expense_id'])) {
            return \App\Support\RecordUrl::expense((int) $data['expense_id']);
        }
        if (! empty($data['leave_id']) && is_numeric($data['leave_id'])) {
            return \App\Support\RecordUrl::leave((int) $data['leave_id']);
        }
        if (! empty($data['custody_id']) && is_numeric($data['custody_id'])) {
            return \App\Support\RecordUrl::custody((int) $data['custody_id']);
        }

        $path = self::appPath($data['url'] ?? null);
        if ($path === null) {
            return null;
        }

        // Repair bare /tasks → cannot open a record without id.
        if ($path === '/tasks' || $path === '/tasks/') {
            return null;
        }

        return $path;
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
