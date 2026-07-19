<?php

namespace Tests\Feature;

use App\Console\Commands\NotifyExpiringContracts;
use App\Models\Contract;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ContractExpiring;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContractExpiringTest extends TestCase
{
    use RefreshDatabase;

    protected User $hrManager;

    protected User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);

        $this->hrManager = User::factory()->create(['phone' => '0502222201']);
        $this->hrManager->givePermissionTo('partnerships.contracts.manage');

        $this->employee = User::factory()->create(['phone' => '0502222202']);
    }

    /** @return array<string, array{0: int}> */
    public static function expiryThresholdProvider(): array
    {
        return [
            '90 days' => [90],
            '60 days' => [60],
            '30 days' => [30],
        ];
    }

    #[DataProvider('expiryThresholdProvider')]
    public function test_contract_expiry_command_notifies_hr_manager_at_threshold(int $days): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-01-01 08:00:00'));

        $contract = Contract::factory()->create([
            'employee_id' => $this->employee->id,
            'end_date' => now()->addDays($days)->toDateString(),
            'status' => 'active',
        ]);

        Artisan::call(NotifyExpiringContracts::class);

        Notification::assertSentTo(
            $this->hrManager,
            ContractExpiring::class,
            fn (ContractExpiring $notification) => $notification->contract->id === $contract->id
                && $notification->daysRemaining === $days
        );

        Carbon::setTestNow();
    }

    public function test_hr_role_user_receives_expiry_notification(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-01-01 08:00:00'));

        $hrUser = User::factory()->create(['phone' => '0502222203']);
        $hrUser->assignRole(Role::firstOrCreate(['name' => 'HR', 'guard_name' => 'web']));

        Contract::factory()->create([
            'employee_id' => $this->employee->id,
            'end_date' => now()->addDays(30)->toDateString(),
            'status' => 'active',
        ]);

        Artisan::call(NotifyExpiringContracts::class);

        Notification::assertSentTo($hrUser, ContractExpiring::class);

        Carbon::setTestNow();
    }
}
