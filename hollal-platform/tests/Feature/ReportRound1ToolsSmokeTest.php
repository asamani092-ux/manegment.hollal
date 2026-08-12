<?php

namespace Tests\Feature;

use App\Livewire\Documents\DocumentPoliciesIndex;
use App\Livewire\Documents\DocumentTemplatesIndex;
use App\Livewire\Settings\ExpenseSettingsIndex;
use App\Livewire\Tasks\RecurringTasksIndex;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\ExpenseSetting;
use App\Models\RecurringTaskTemplate;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Round-1 batch 5 — smoke open + CRUD of previously untested tools.
 */
class ReportRound1ToolsSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    public function test_super_admin_opens_previously_untested_tools(): void
    {
        $admin = User::factory()->create(['must_change_password' => false]);
        $admin->assignRole('Super Admin');

        foreach ([
            'recurring-tasks.index',
            'financial-documents.index',
            'documents.templates',
            'documents.versions',
            'documents.policies',
            'settings.expenses',
        ] as $route) {
            $this->actingAs($admin)
                ->get(route($route))
                ->assertOk();
        }
    }

    public function test_recurring_task_template_create_smoke(): void
    {
        $admin = User::factory()->create(['must_change_password' => false]);
        $admin->assignRole('Super Admin');
        $assignee = User::factory()->create(['is_active' => true]);

        Livewire::actingAs($admin)
            ->test(RecurringTasksIndex::class)
            ->call('openCreate')
            ->set('title', 'تقرير أسبوعي تجريبي')
            ->set('assigned_to_id', $assignee->id)
            ->set('pattern', 'أسبوعي')
            ->set('day_of_week', 0)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('recurring_task_templates', [
            'title' => 'تقرير أسبوعي تجريبي',
            'assigned_to_id' => $assignee->id,
        ]);
        $this->assertSame(1, RecurringTaskTemplate::count());
    }

    public function test_expense_settings_save_smoke(): void
    {
        $admin = User::factory()->create(['must_change_password' => false]);
        $admin->assignRole('Super Admin');

        Livewire::actingAs($admin)
            ->test(ExpenseSettingsIndex::class)
            ->set('chain_mode', 'short')
            ->set('skip_missing_department_manager', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('short', ExpenseSetting::current()->chain_mode);
    }

    public function test_document_template_and_policy_create_smoke(): void
    {
        $admin = User::factory()->create(['must_change_password' => false]);
        $admin->assignRole('Super Admin');

        Livewire::actingAs($admin)
            ->test(DocumentTemplatesIndex::class)
            ->set('title', 'نموذج تجربة')
            ->set('uploadFile', UploadedFile::fake()->create('t.pdf', 10, 'application/pdf'))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, DocumentTemplate::count());

        Livewire::actingAs($admin)
            ->test(DocumentPoliciesIndex::class)
            ->set('policyTitle', 'سياسة تجربة')
            ->set('policyFile', UploadedFile::fake()->create('p.pdf', 10, 'application/pdf'))
            ->call('savePolicy')
            ->assertHasNoErrors();

        $this->assertTrue(Document::query()->where('is_policy', true)->where('title', 'سياسة تجربة')->exists());
    }
}
