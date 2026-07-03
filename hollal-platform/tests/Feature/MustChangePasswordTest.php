<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MustChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_middleware_redirects_user_with_must_change_password_to_change_page(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create([
            'must_change_password' => true,
        ]);
        $user->givePermissionTo('dashboard.view');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('password.change'));
    }

    public function test_change_password_page_is_accessible_when_flag_is_set(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->get(route('password.change'))
            ->assertOk()
            ->assertSee('تغيير كلمة المرور');
    }

    public function test_successful_password_change_clears_flag_and_redirects_to_dashboard(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
            'must_change_password' => true,
        ]);
        $user->givePermissionTo('dashboard.view');

        $this->actingAs($user)
            ->post(route('password.change.update'), [
                'current_password' => 'old-password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $user->refresh();

        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('new-secure-password', $user->password));
    }

    public function test_admin_seeder_does_not_use_hardcoded_password(): void
    {
        putenv('ADMIN_INITIAL_PASSWORD');
        unset($_ENV['ADMIN_INITIAL_PASSWORD'], $_SERVER['ADMIN_INITIAL_PASSWORD']);

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $this->artisan('db:seed', ['--class' => AdminUserSeeder::class])
            ->assertSuccessful();

        $admin = User::query()->where('email', 'admin@hollal.local')->first();

        $this->assertNotNull($admin);
        $this->assertTrue($admin->must_change_password);
        $this->assertFalse(Hash::check('password', $admin->password));
    }

    public function test_admin_seeder_uses_env_password_when_set(): void
    {
        putenv('ADMIN_INITIAL_PASSWORD=env-test-password');
        $_ENV['ADMIN_INITIAL_PASSWORD'] = 'env-test-password';
        $_SERVER['ADMIN_INITIAL_PASSWORD'] = 'env-test-password';

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $this->artisan('db:seed', ['--class' => AdminUserSeeder::class])
            ->assertSuccessful();

        $admin = User::query()->where('email', 'admin@hollal.local')->first();

        $this->assertNotNull($admin);
        $this->assertTrue(Hash::check('env-test-password', $admin->password));
        $this->assertTrue($admin->must_change_password);

        putenv('ADMIN_INITIAL_PASSWORD');
        unset($_ENV['ADMIN_INITIAL_PASSWORD'], $_SERVER['ADMIN_INITIAL_PASSWORD']);
    }
}
