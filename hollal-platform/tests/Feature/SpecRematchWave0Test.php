<?php

namespace Tests\Feature;

use App\Livewire\Expenses\ExpensesIndex;
use App\Livewire\Meetings\MeetingMinutes;
use App\Models\ExpenseCategory;
use App\Models\Meeting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SpecRematchWave0Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_meeting_minutes_shows_approve_button_before_approval(): void
    {
        $chair = User::factory()->create(['must_change_password' => false]);
        $chair->givePermissionTo(['meetings.view', 'meetings.update']);

        $meeting = Meeting::factory()->create([
            'chair_id' => $chair->id,
            'approval_status' => Meeting::APPROVAL_DRAFT,
        ]);

        Livewire::actingAs($chair)
            ->test(MeetingMinutes::class, ['meeting' => $meeting])
            ->assertSee('اعتماد المحضر')
            ->call('approveMinutes')
            ->assertDispatched('toast');

        $this->assertTrue($meeting->fresh()->isApproved());
    }

    public function test_expense_form_accepts_optional_project_and_department(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['finance.expenses.view', 'finance.expenses.create']);

        $category = ExpenseCategory::create(['name_ar' => 'اختبار', 'parent_id' => null]);

        Livewire::actingAs($user)
            ->test(ExpensesIndex::class)
            ->set('type', 'operational')
            ->set('amount', '100')
            ->set('reason', 'سبب تجريبي')
            ->set('category_id', $category->id)
            ->set('project_id', null)
            ->set('department_id', null)
            ->call('saveExpense')
            ->assertHasNoErrors();
    }
}
