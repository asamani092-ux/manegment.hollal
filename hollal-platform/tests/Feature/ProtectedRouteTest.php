<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProtectedRouteTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string}> */
    public static function protectedRoutesProvider(): array
    {
        return [
            'dashboard' => ['/dashboard'],
            'projects' => ['/projects'],
            'tasks' => ['/tasks'],
            'meetings' => ['/meetings'],
            'meetings open decisions' => ['/meetings/open-decisions'],
            'departments' => ['/departments'],
            'users' => ['/users'],
            'settings roles' => ['/settings/roles'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    #[DataProvider('protectedRoutesProvider')]
    public function test_guest_is_redirected_from_protected_route(string $url): void
    {
        $this->get($url)->assertRedirect(route('login'));
    }

    #[DataProvider('protectedRoutesProvider')]
    public function test_authenticated_user_without_permission_receives_forbidden(string $url): void
    {
        $user = User::factory()->create([
            'must_change_password' => false,
        ]);

        $this->actingAs($user)->get($url)->assertForbidden();
    }
}
