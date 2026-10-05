<?php

namespace Tests\Feature;

use App\Livewire\Users\EmployeeProfileShow;
use App\Models\AttendanceRecord;
use App\Models\EmployeeDocument;
use App\Models\ReferenceItem;
use App\Models\ReferenceList;
use App\Models\Task;
use App\Models\User;
use App\Models\Violation;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * بطاقات النظرة والتنبيهات المربوطة.
 * Time: O(1) لكل حالة | Space: O(1)
 */
class EmployeeOverviewPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_attendance_says_no_data_and_cards_list_overdue_tasks(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = User::factory()->create(['must_change_password' => false]);
        $admin->givePermissionTo('hr.employees.view');
        $employee = User::factory()->create(['name' => 'محمد الموظف', 'must_change_password' => false]);

        Task::query()->create([
            'title' => 'إغلاق مهمة متأخرة',
            'assigned_by' => $admin->id,
            'assigned_to' => $employee->id,
            'status' => 'new',
            'due_date' => now()->subDay(),
        ]);

        Livewire::actingAs($admin)
            ->test(EmployeeProfileShow::class, ['user' => $employee])
            ->assertSee('لا بيانات', false)
            ->assertSee('بيانات العمل', false)
            ->assertSee('المهام المفتوحة', false)
            ->assertSee('إغلاق مهمة متأخرة', false)
            ->assertSee('color:#b42318', false)
            ->assertSeeHtml("setTab('performance')");

        AttendanceRecord::query()->create([
            'employee_id' => $employee->id,
            'date' => now()->toDateString(),
            'type' => 'انقطاع',
            'declared_by' => $employee->id,
        ]);

        Livewire::actingAs($admin)
            ->test(EmployeeProfileShow::class, ['user' => $employee])
            ->assertSee('لا بيانات', false)
            ->assertDontSee('0٪', false);

        AttendanceRecord::query()->create([
            'employee_id' => $employee->id,
            'date' => now()->subDay()->toDateString(),
            'type' => 'حضور',
            'declared_by' => $employee->id,
        ]);

        Livewire::actingAs($admin)
            ->test(EmployeeProfileShow::class, ['user' => $employee])
            ->assertSee('50٪', false)
            ->assertDontSee('لا بيانات', false);
    }

    public function test_alerts_open_the_document_upload_and_the_statement_form(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = User::factory()->create(['must_change_password' => false]);
        $admin->givePermissionTo(['hr.employees.view', 'hr.employees.update']);
        $employee = User::factory()->create(['name' => 'محمد الموظف', 'must_change_password' => false]);
        $employee->givePermissionTo('dashboard.view');

        $doc = EmployeeDocument::query()->create([
            'user_id' => $employee->id,
            'type' => 'هوية',
            'document_number' => '1000000001',
            'expiry_date' => now()->subMonth()->toDateString(),
        ]);
        $list = ReferenceList::query()->create(['key' => 'violations-polish', 'name_ar' => 'المخالفات']);
        $item = ReferenceItem::query()->create([
            'reference_list_id' => $list->id,
            'code' => 'late',
            'name_ar' => 'تأخر',
            'status' => 'active',
            'version' => 1,
            'effective_from' => now()->subYear()->toDateString(),
        ]);
        $violation = Violation::query()->create([
            'employee_id' => $employee->id,
            'reference_item_id' => $item->id,
            'occurred_on' => now()->subDays(2)->toDateString(),
            'discovered_on' => now()->subDays(2)->toDateString(),
            'facts' => 'تأخر عن الدوام دون إذن',
            'status' => 'awaiting_statement',
            'occurrence_index' => 1,
            'source' => 'manual',
        ]);

        Livewire::actingAs($admin)
            ->test(EmployeeProfileShow::class, ['user' => $employee])
            ->assertSee('وثيقة منتهية', false)
            ->assertSee('إفادة مطلوبة', false)
            ->call('openOverviewAlert', 'document', $doc->id)
            ->assertSet('activeTab', 'documents')
            ->assertSet('showDocumentModal', true)
            ->assertSet('documentId', $doc->id)
            ->assertDontSeeHtml('type="date"')
            ->assertSee('اختيار ملف', false)
            ->call('setDocPart', 'docExpiryDate', 'day', '09')
            ->assertSet('docExpiryDate', now()->subMonth()->format('Y-m').'-09');

        Livewire::actingAs($employee)
            ->test(EmployeeProfileShow::class, ['user' => $employee])
            ->call('openOverviewAlert', 'statement', $violation->id)
            ->assertSet('activeTab', 'violations')
            ->assertSet('focusViolationId', $violation->id)
            ->assertSee('نموذج الإفادة', false)
            ->assertSee('textarea', false);
    }
}
