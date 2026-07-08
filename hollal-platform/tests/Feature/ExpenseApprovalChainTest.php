<?php

namespace Tests\Feature;

use App\Livewire\Expenses\ExpensesIndex;
use App\Models\ExpenseRequest;
use App\Models\ExpenseSetting;
use App\Models\User;
use App\Notifications\ExpenseAwaitingApproval;
use App\Notifications\ExpensePaidReady;
use App\Services\ExpenseApprovalService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ExpenseApprovalChainTest extends TestCase
{
    use RefreshDatabase;

    protected User $requester;

    protected User $deptManager;

    protected User $executive;

    protected User $finance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->deptManager = User::factory()->create(['phone' => '0501000001', 'must_change_password' => false]);
        $this->deptManager->givePermissionTo('expenses.approve');

        $this->requester = User::factory()->create([
            'phone' => '0501000002',
            'manager_id' => $this->deptManager->id,
            'must_change_password' => false,
        ]);
        $this->requester->assignRole('Employee');

        $this->executive = User::factory()->create(['phone' => '0501000003', 'must_change_password' => false]);
        $this->executive->assignRole('Executive Manager');

        $this->finance = User::factory()->create(['phone' => '0501000004', 'must_change_password' => false]);
        $this->finance->assignRole('Finance');
    }

    public function test_full_chain_routes_through_all_stages(): void
    {
        Notification::fake();

        ExpenseSetting::current()->update(['chain_mode' => 'full', 'skip_missing_department_manager' => true]);

        $expense = ExpenseRequest::factory()->create([
            'requester_id' => $this->requester->id,
            'status' => 'draft',
        ]);

        Livewire::actingAs($this->requester)
            ->test(ExpensesIndex::class)
            ->call('submitExpense', $expense->id);

        $expense->refresh();
        $this->assertSame('pending', $expense->status);
        $this->assertSame(ExpenseApprovalService::STAGE_DEPARTMENT_MANAGER, $expense->current_approval_stage);
        $this->assertSame(
            [
                ExpenseApprovalService::STAGE_DEPARTMENT_MANAGER,
                ExpenseApprovalService::STAGE_EXECUTIVE,
                ExpenseApprovalService::STAGE_FINANCE,
            ],
            $expense->approval_stages
        );

        Notification::assertSentTo($this->deptManager, ExpenseAwaitingApproval::class);

        Livewire::actingAs($this->deptManager)->test(ExpensesIndex::class)->call('approveExpense', $expense->id);
        $expense->refresh();
        $this->assertSame(ExpenseApprovalService::STAGE_EXECUTIVE, $expense->current_approval_stage);

        Notification::assertSentTo($this->executive, ExpenseAwaitingApproval::class);

        Livewire::actingAs($this->executive)->test(ExpensesIndex::class)->call('approveExpense', $expense->id);
        $expense->refresh();
        $this->assertSame(ExpenseApprovalService::STAGE_FINANCE, $expense->current_approval_stage);

        Notification::assertSentTo($this->finance, ExpenseAwaitingApproval::class);

        Livewire::actingAs($this->finance)->test(ExpensesIndex::class)->call('approveExpense', $expense->id);
        $expense->refresh();

        $this->assertSame('approved', $expense->status);
        $this->assertNull($expense->current_approval_stage);
        $this->assertNotNull($expense->paid_ready_at);
        $this->assertDatabaseCount('expense_approval_logs', 3);

        Notification::assertSentTo($this->requester, ExpensePaidReady::class);
    }

    public function test_short_chain_skips_department_manager(): void
    {
        ExpenseSetting::current()->update(['chain_mode' => 'short']);

        $expense = ExpenseRequest::factory()->create([
            'requester_id' => $this->requester->id,
            'status' => 'draft',
        ]);

        app(ExpenseApprovalService::class)->initializeChain($expense);
        $expense->refresh();

        $this->assertSame(ExpenseApprovalService::STAGE_EXECUTIVE, $expense->current_approval_stage);
        $this->assertSame(
            [ExpenseApprovalService::STAGE_EXECUTIVE, ExpenseApprovalService::STAGE_FINANCE],
            $expense->approval_stages
        );
    }

    public function test_missing_department_manager_is_skipped_when_enabled(): void
    {
        ExpenseSetting::current()->update(['chain_mode' => 'full', 'skip_missing_department_manager' => true]);

        $requesterNoManager = User::factory()->create([
            'phone' => '0501000005',
            'manager_id' => null,
            'must_change_password' => false,
        ]);
        $requesterNoManager->assignRole('Employee');

        $expense = ExpenseRequest::factory()->create([
            'requester_id' => $requesterNoManager->id,
            'status' => 'draft',
        ]);

        app(ExpenseApprovalService::class)->initializeChain($expense);
        $expense->refresh();

        $this->assertSame(ExpenseApprovalService::STAGE_EXECUTIVE, $expense->current_approval_stage);
        $this->assertNotContains(ExpenseApprovalService::STAGE_DEPARTMENT_MANAGER, $expense->approval_stages);
    }

    public function test_camera_captured_image_stores_on_private_disk(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->requester)
            ->test(ExpensesIndex::class)
            ->call('openExpenseCreate')
            ->set('type', 'operational')
            ->set('amount', '500')
            ->set('reason', 'إيصال مصور')
            ->set('priority', 'high')
            ->set('payment_method', 'pos')
            ->set('cameraAttachment', UploadedFile::fake()->create('receipt.jpg', 100, 'image/jpeg'))
            ->call('saveExpense', false)
            ->assertHasNoErrors();

        $expense = ExpenseRequest::first();
        $this->assertNotNull($expense);
        $this->assertSame('high', $expense->priority);
        $this->assertSame('pos', $expense->payment_method);
        $this->assertNotNull($expense->attachment);
        Storage::disk('local')->assertExists($expense->attachment);
    }

    public function test_list_sorts_by_priority(): void
    {
        ExpenseRequest::factory()->create([
            'requester_id' => $this->requester->id,
            'priority' => 'low',
            'amount' => 100,
        ]);
        ExpenseRequest::factory()->create([
            'requester_id' => $this->requester->id,
            'priority' => 'urgent',
            'amount' => 200,
        ]);
        ExpenseRequest::factory()->create([
            'requester_id' => $this->requester->id,
            'priority' => 'normal',
            'amount' => 300,
        ]);

        $ordered = ExpenseRequest::query()
            ->where('requester_id', $this->requester->id)
            ->orderByPriority()
            ->pluck('priority')
            ->all();

        $this->assertSame(['urgent', 'normal', 'low'], $ordered);
    }
}
