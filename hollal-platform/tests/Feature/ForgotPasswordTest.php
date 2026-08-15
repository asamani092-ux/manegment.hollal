<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordResetLink;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_guest_can_view_forgot_password_form(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('نسيت كلمة المرور');
    }

    public function test_reset_link_is_sent_for_known_phone_without_leaking_existence(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'phone' => '0501234000',
            'email' => 'reset@hollal.local',
            'is_active' => true,
        ]);

        $this->from(route('password.request'))
            ->post(route('password.email'), ['identifier' => '0501234000'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('success');

        Notification::assertSentTo($user, PasswordResetLink::class);
    }

    public function test_unknown_identifier_still_shows_success(): void
    {
        Notification::fake();

        $this->from(route('password.request'))
            ->post(route('password.email'), ['identifier' => '0500000999'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('success');

        Notification::assertNothingSent();
    }

    public function test_user_can_reset_password_from_token(): void
    {
        $user = User::factory()->create([
            'phone' => '0501234111',
            'email' => 'reset2@hollal.local',
            'password' => Hash::make('old-secret-99'),
            'must_change_password' => false,
            'is_active' => true,
        ]);
        $user->givePermissionTo('dashboard.view');

        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'identifier' => 'reset2@hollal.local',
            'password' => 'new-secret-99',
            'password_confirmation' => 'new-secret-99',
        ])->assertRedirect(route('login'));

        $this->post(route('login'), [
            'phone' => '0501234111',
            'password' => 'new-secret-99',
        ])->assertRedirect(route('dashboard'));
    }
}
