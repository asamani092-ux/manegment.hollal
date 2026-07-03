<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ExpenseRequest;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseFileDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected User $requester;

    protected User $outsider;

    protected ExpenseRequest $expense;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        Storage::fake('local');

        $this->requester = User::factory()->create(['phone' => '0501111111', 'must_change_password' => false]);
        $this->requester->givePermissionTo(['expenses.create']);

        $this->outsider = User::factory()->create(['phone' => '0502222222', 'must_change_password' => false]);

        Storage::disk('local')->put('expenses/receipt.pdf', 'expense-file-content');

        $this->expense = ExpenseRequest::factory()->create([
            'requester_id' => $this->requester->id,
            'attachment' => 'expenses/receipt.pdf',
        ]);
    }

    public function test_guest_is_redirected_when_downloading_expense_attachment(): void
    {
        $this->get(route('expenses.files.download', $this->expense))
            ->assertRedirect(route('login'));
    }

    public function test_unrelated_user_receives_forbidden_when_downloading_expense_attachment(): void
    {
        $this->actingAs($this->outsider)
            ->get(route('expenses.files.download', $this->expense))
            ->assertForbidden();
    }

    public function test_requester_can_download_expense_attachment(): void
    {
        $this->actingAs($this->requester)
            ->get(route('expenses.files.download', $this->expense))
            ->assertOk()
            ->assertDownload('receipt.pdf');
    }
}
