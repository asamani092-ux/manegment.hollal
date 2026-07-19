<?php

namespace Tests\Feature;

use App\Livewire\Contracts\ContractsIndex;
use App\Models\Contract;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContractValueVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $hrUser;

    protected User $financeUser;

    protected Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);

        $employee = User::factory()->create(['phone' => '0503333301']);

        $this->hrUser = User::factory()->create(['phone' => '0503333302']);
        $this->hrUser->givePermissionTo(['partnerships.contracts.view', 'partnerships.contracts.create']);

        $this->financeUser = User::factory()->create(['phone' => '0503333303']);
        $this->financeUser->givePermissionTo(['partnerships.contracts.view', 'finance.expenses.view']);

        $this->contract = Contract::factory()->create([
            'employee_id' => $employee->id,
            'value' => 25000.50,
        ]);
    }

    public function test_non_finance_user_cannot_see_contract_value_in_response(): void
    {
        Livewire::actingAs($this->hrUser)
            ->test(ContractsIndex::class)
            ->assertDontSee('25,000.50')
            ->assertDontSee('25000.50');

        $component = Livewire::actingAs($this->hrUser)->test(ContractsIndex::class);
        $this->assertFalse($component->instance()->canViewValue());
        $this->assertSame('****', $component->instance()->maskedValue($this->contract));
    }

    public function test_finance_user_can_see_contract_value_in_response(): void
    {
        Livewire::actingAs($this->financeUser)
            ->test(ContractsIndex::class)
            ->assertSee('25,000.50');

        $component = Livewire::actingAs($this->financeUser)->test(ContractsIndex::class);
        $this->assertTrue($component->instance()->canViewValue());
    }

    public function test_masked_value_method_returns_mask_for_non_finance(): void
    {
        $component = Livewire::actingAs($this->hrUser)->test(ContractsIndex::class);

        $this->assertSame('****', $component->instance()->maskedValue($this->contract));
    }

    public function test_masked_value_method_returns_amount_for_finance(): void
    {
        $component = Livewire::actingAs($this->financeUser)->test(ContractsIndex::class);

        $this->assertSame('25,000.50', $component->instance()->maskedValue($this->contract));
    }
}
