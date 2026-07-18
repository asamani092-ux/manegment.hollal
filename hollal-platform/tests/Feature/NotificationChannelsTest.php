<?php

namespace Tests\Feature;

use App\Livewire\Settings\MailSettingsIndex;
use App\Models\MailSetting;
use App\Models\NotificationPreference;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Database\Seeders\PermissionSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 00-B3 — queued mail channel + notification preferences + SMTP test-send.
 */
class NotificationChannelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_critical_notification_delivers_on_database_and_mail(): void
    {
        $user = User::factory()->create(['email' => 'recipient@example.com']);
        $task = Task::factory()->create();

        $channels = (new TaskAssigned($task))->via($user);

        $this->assertContains('database', $channels);
        $this->assertContains('mail', $channels);
    }

    public function test_critical_notification_is_queued(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new TaskAssigned(Task::factory()->create()));
    }

    public function test_mail_channel_respects_user_optout(): void
    {
        $user = User::factory()->create(['email' => 'optout@example.com']);
        $task = Task::factory()->create();

        NotificationPreference::create([
            'user_id' => $user->id,
            'channel' => 'mail',
            'event_type' => 'TaskAssigned',
            'enabled' => false,
        ]);

        $channels = (new TaskAssigned($task))->via($user);

        $this->assertContains('database', $channels);
        $this->assertNotContains('mail', $channels);
    }

    public function test_test_send_fails_honestly_when_smtp_not_configured(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['must_change_password' => false]);
        $admin->givePermissionTo('settings.notifications.manage');

        Livewire::actingAs($admin)
            ->test(MailSettingsIndex::class)
            ->call('sendTest')
            ->assertDispatched('toast', type: 'error');

        Mail::assertNothingSent();
    }

    public function test_saving_smtp_settings_stores_encrypted_password(): void
    {
        $admin = User::factory()->create(['must_change_password' => false]);
        $admin->givePermissionTo('settings.notifications.manage');

        Livewire::actingAs($admin)
            ->test(MailSettingsIndex::class)
            ->set('host', 'smtp.example.com')
            ->set('port', 587)
            ->set('encryption', 'tls')
            ->set('username', 'mailer@example.com')
            ->set('password', 'super-secret')
            ->set('from_address', 'no-reply@example.com')
            ->set('from_name', 'منصة حلّل')
            ->call('save')
            ->assertDispatched('toast', type: 'success');

        $settings = MailSetting::current();
        $this->assertSame('smtp.example.com', $settings->host);
        $this->assertSame('super-secret', $settings->password);

        // Stored ciphertext must not equal the plaintext.
        $raw = \Illuminate\Support\Facades\DB::table('mail_settings')->value('password');
        $this->assertNotSame('super-secret', $raw);
    }

    public function test_test_send_requires_permission(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);

        Livewire::actingAs($user)
            ->test(MailSettingsIndex::class)
            ->assertForbidden();
    }
}
