<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_PASSWORD = 'auth-test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_user_can_login_with_phone_and_password(): void
    {
        $user = User::factory()->create([
            'phone' => '0509999999',
            'password' => Hash::make(self::TEST_PASSWORD),
            'must_change_password' => false,
        ]);
        $user->givePermissionTo('dashboard.view');

        $response = $this->post(route('login'), [
            'phone' => '0509999999',
            'password' => self::TEST_PASSWORD,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_regenerates_session_id(): void
    {
        $user = User::factory()->create([
            'phone' => '0506666666',
            'password' => Hash::make(self::TEST_PASSWORD),
            'must_change_password' => false,
        ]);
        $user->givePermissionTo('dashboard.view');

        $this->startSession();
        $oldSessionId = session()->getId();

        $this->post(route('login'), [
            'phone' => '0506666666',
            'password' => self::TEST_PASSWORD,
        ])->assertRedirect(route('dashboard'));

        $this->assertNotSame($oldSessionId, session()->getId());
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'phone' => '0508888888',
            'password' => Hash::make(self::TEST_PASSWORD),
            'must_change_password' => false,
        ]);

        $response = $this->from(route('login'))->post(route('login'), [
            'phone' => '0508888888',
            'password' => 'wrong-password-value',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('phone');
        $this->assertGuest();
    }

    public function test_login_is_locked_out_after_five_failed_attempts(): void
    {
        User::factory()->create([
            'phone' => '0507777777',
            'password' => Hash::make(self::TEST_PASSWORD),
            'must_change_password' => false,
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->from(route('login'))->post(route('login'), [
                'phone' => '0507777777',
                'password' => 'wrong-password-value',
            ])->assertRedirect(route('login'));
        }

        $response = $this->from(route('login'))->post(route('login'), [
            'phone' => '0507777777',
            'password' => 'wrong-password-value',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('phone');
        $this->assertStringContainsString(
            'محاولات كثيرة',
            session('errors')->get('phone')[0]
        );
        $this->assertGuest();
    }
}
