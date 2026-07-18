<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContractFileDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected User $hrUser;

    protected User $outsider;

    protected Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        Storage::fake('local');

        $employee = User::factory()->create(['phone' => '0509999999']);

        $this->hrUser = User::factory()->create(['phone' => '0501111111', 'must_change_password' => false]);
        $this->hrUser->givePermissionTo(['partnerships.contracts.view']);

        $this->outsider = User::factory()->create(['phone' => '0502222222', 'must_change_password' => false]);

        Storage::disk('local')->put('contracts/contract.pdf', 'contract-file-content');

        $this->contract = Contract::factory()->create([
            'employee_id' => $employee->id,
            'contract_file' => 'contracts/contract.pdf',
        ]);
    }

    public function test_guest_is_redirected_when_downloading_contract_file(): void
    {
        $this->get(route('contracts.files.download', $this->contract))
            ->assertRedirect(route('login'));
    }

    public function test_unrelated_user_receives_forbidden_when_downloading_contract_file(): void
    {
        $this->actingAs($this->outsider)
            ->get(route('contracts.files.download', $this->contract))
            ->assertForbidden();
    }

    public function test_hr_user_can_download_contract_file(): void
    {
        $this->actingAs($this->hrUser)
            ->get(route('contracts.files.download', $this->contract))
            ->assertOk()
            ->assertDownload('contract.pdf');
    }
}
