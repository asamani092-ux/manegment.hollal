<?php

namespace Tests\Feature;

use App\Livewire\Expenses\ExpensesIndex;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Models\User;
use App\Support\Setting;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 04-B1 — mandatory expense category, department-or-project attribution, and the
 * company tax-number reminder.
 */
class ExpenseCategoryEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function requester(): User
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['finance.expenses.view', 'finance.expenses.create']);

        return $user;
    }

    public function test_category_is_required(): void
    {
        Livewire::actingAs($this->requester())
            ->test(ExpensesIndex::class)
            ->call('openExpenseCreate')
            ->set('amount', '100')
            ->set('reason', 'مصروف تجريبي')
            ->set('project_id', Project::factory()->create()->id)
            ->call('saveExpense')
            ->assertHasErrors('category_id');
    }

    public function test_department_or_project_is_required(): void
    {
        $category = ExpenseCategory::create(['name_ar' => 'ضيافة']);

        Livewire::actingAs($this->requester())
            ->test(ExpensesIndex::class)
            ->call('openExpenseCreate')
            ->set('amount', '100')
            ->set('reason', 'مصروف')
            ->set('category_id', $category->id)
            ->call('saveExpense')
            ->assertHasErrors('project_id');
    }

    public function test_valid_expense_saves(): void
    {
        $category = ExpenseCategory::create(['name_ar' => 'مواصلات']);
        $project = Project::factory()->create();

        Livewire::actingAs($this->requester())
            ->test(ExpensesIndex::class)
            ->call('openExpenseCreate')
            ->set('amount', '250')
            ->set('reason', 'أجرة نقل')
            ->set('category_id', $category->id)
            ->set('project_id', $project->id)
            ->call('saveExpense')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('expense_requests', [
            'category_id' => $category->id,
            'project_id' => $project->id,
            'amount' => 250,
        ]);
    }

    public function test_tax_number_warning_visibility(): void
    {
        Livewire::actingAs($this->requester())
            ->test(ExpensesIndex::class)
            ->assertViewHas('companyTaxNumberMissing', true);

        Setting::set('company.tax_number', '300000000000003');

        Livewire::actingAs($this->requester())
            ->test(ExpensesIndex::class)
            ->assertViewHas('companyTaxNumberMissing', false);
    }
}
