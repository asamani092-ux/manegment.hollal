<?php

namespace App\Livewire\Settings;

use App\Models\MailSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

/**
 * 00-B3 — SMTP settings screen with an honest test-send button.
 * The stored password is never bound to the view; it is only written when a
 * new value is entered.
 */
class MailSettingsIndex extends Component
{
    use AuthorizesRequests;

    public string $host = '';

    public ?int $port = null;

    public ?string $encryption = null;

    public string $username = '';

    /** New password entry only; blank leaves the stored value untouched. */
    public string $password = '';

    public string $from_address = '';

    public string $from_name = '';

    public bool $hasStoredPassword = false;

    public function mount(): void
    {
        $this->authorize('settings.notifications.manage');

        $settings = MailSetting::current();
        $this->host = (string) $settings->host;
        $this->port = $settings->port;
        $this->encryption = $settings->encryption;
        $this->username = (string) $settings->username;
        $this->from_address = (string) $settings->from_address;
        $this->from_name = (string) $settings->from_name;
        $this->hasStoredPassword = filled($settings->password);
    }

    public function save(): void
    {
        $this->authorize('settings.notifications.manage');

        $validated = $this->validate([
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'encryption' => 'nullable|in:tls,ssl',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'from_address' => 'required|email|max:255',
            'from_name' => 'nullable|string|max:255',
        ]);

        $settings = MailSetting::current();
        $settings->fill([
            'host' => $validated['host'],
            'port' => $validated['port'],
            'encryption' => $validated['encryption'] ?? null,
            'username' => $validated['username'] ?? null,
            'from_address' => $validated['from_address'],
            'from_name' => $validated['from_name'] ?? null,
            'updated_by' => auth()->id(),
        ]);

        if (filled($this->password)) {
            $settings->password = $this->password;
        }

        $settings->save();

        $this->password = '';
        $this->hasStoredPassword = filled($settings->password);

        $this->dispatch('toast', type: 'success', message: 'تم حفظ إعدادات البريد');
    }

    public function sendTest(): void
    {
        $this->authorize('settings.notifications.manage');

        $settings = MailSetting::current();

        if (! $settings->isConfigured()) {
            $this->dispatch('toast', type: 'error', message: 'الرجاء إكمال إعدادات SMTP (المضيف والمنفذ وعنوان المُرسِل) قبل الاختبار');

            return;
        }

        try {
            $settings->applyToConfig();

            $recipient = auth()->user()->email;

            Mail::raw('هذه رسالة اختبار من منصة حلّل الإدارية للتأكد من صحة إعدادات البريد.', function ($message) use ($recipient) {
                $message->to($recipient)->subject('اختبار إعدادات البريد — منصة حلّل');
            });

            $this->dispatch('toast', type: 'success', message: 'تم إرسال رسالة اختبار إلى '.$recipient);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'فشل إرسال رسالة الاختبار: '.$e->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.settings.mail-settings-index')
            ->layout('layouts.app', ['title' => 'إعدادات الإشعارات والبريد']);
    }
}
