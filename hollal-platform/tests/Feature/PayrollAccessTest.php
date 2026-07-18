<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_user_without_salaries_view_cannot_access_payroll_index(): void
    {
        $user = User::factory()->create([
            'phone' => '0504444444',
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($user)->get(route('payroll.index'));

        $response->assertForbidden();
    }

    public function test_user_with_salaries_view_can_access_payroll_index(): void
    {
        $user = User::factory()->create([
            'phone' => '0505555555',
            'must_change_password' => false,
        ]);
        $user->givePermissionTo('hr.salaries.view');

        $response = $this->actingAs($user)->get(route('payroll.index'));

        $response->assertOk();
    }
}
