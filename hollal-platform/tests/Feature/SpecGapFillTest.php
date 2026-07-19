<?php

namespace Tests\Feature;

use App\Livewire\Documents\DocumentPoliciesIndex;
use App\Livewire\Documents\DocumentTemplatesIndex;
use App\Livewire\Users\EmployeeProfileShow;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\OfficialDutiesDocument;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Spec gap fill — permissions + UI surfaces required by
 * spec-07-08 / amendments HR before merge to main.
 */
class SpecGapFillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
    }

    public function test_spec_document_and_report_permissions_exist_with_arabic_labels(): void
    {
        $required = [
            'documents.manage-versions',
            'documents.templates.manage',
            'documents.policies.manage',
            'reports.weekly.view',
            'reports.monthly.view',
            'reports.projects.view',
            'reports.impact.view',
            'reports.kpis.view',
            'reports.audit-log.view',
            'reports.export',
            'structure.view',
            'structure.manage',
            'structure.positions.manage',
            'structure.committees.manage',
            'settings.general.manage',
            'settings.finance.manage',
            'settings.backup.manage',
        ];

        $labels = config('permission_labels.labels');

        foreach ($required as $name) {
            $this->assertDatabaseHas('permissions', ['name' => $name, 'guard_name' => 'web']);
            $this->assertArrayHasKey($name, $labels);
            $this->assertNotEmpty($labels[$name], $name);
        }
    }

    public function test_document_templates_require_manage_permission_to_upload(): void
    {
        $viewer = User::factory()->create(['must_change_password' => false]);
        $viewer->givePermissionTo('documents.view');

        Livewire::actingAs($viewer)
            ->test(DocumentTemplatesIndex::class)
            ->set('title', 'نموذج عقد')
            ->set('uploadFile', UploadedFile::fake()->create('t.pdf', 10, 'application/pdf'))
            ->call('save')
            ->assertForbidden();

        $manager = User::factory()->create(['must_change_password' => false]);
        $manager->givePermissionTo(['documents.view', 'documents.templates.manage']);

        Livewire::actingAs($manager)
            ->test(DocumentTemplatesIndex::class)
            ->set('title', 'نموذج عقد')
            ->set('uploadFile', UploadedFile::fake()->create('t.pdf', 10, 'application/pdf'))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, DocumentTemplate::count());
    }

    public function test_policies_and_duties_publish_activate_dashboard_slot(): void
    {
        $manager = User::factory()->create(['must_change_password' => false]);
        $manager->givePermissionTo('documents.policies.manage');

        Livewire::actingAs($manager)
            ->test(DocumentPoliciesIndex::class)
            ->set('policyTitle', 'لائحة السلوك')
            ->set('reviewDate', now()->addMonth()->toDateString())
            ->set('policyFile', UploadedFile::fake()->create('p.pdf', 10, 'application/pdf'))
            ->call('savePolicy')
            ->assertHasNoErrors();

        $this->assertTrue(Document::query()->where('is_policy', true)->exists());

        Livewire::actingAs($manager)
            ->test(DocumentPoliciesIndex::class)
            ->set('dutiesFile', UploadedFile::fake()->create('duties.pdf', 10, 'application/pdf'))
            ->call('publishDuties')
            ->assertHasNoErrors();

        $this->assertNotNull(OfficialDutiesDocument::latestPublished());
    }

    public function test_hr_can_enable_attendance_and_set_weekly_hours_on_profile(): void
    {
        $hr = User::factory()->create(['must_change_password' => false]);
        $hr->givePermissionTo(['hr.employees.view', 'hr.employees.update']);

        $employee = User::factory()->create([
            'must_change_password' => false,
            'attendance_enabled' => false,
        ]);

        Livewire::actingAs($hr)
            ->test(EmployeeProfileShow::class, ['user' => $employee])
            ->set('activeTab', 'job')
            ->set('attendanceEnabled', true)
            ->set('weeklyHours', '40')
            ->call('saveAttendanceSettings')
            ->assertHasNoErrors();

        $employee->refresh();
        $this->assertTrue((bool) $employee->attendance_enabled);
        $this->assertSame(40, $employee->profile?->weekly_hours);
    }

    public function test_overtime_gate_dropdown_on_salary_tab(): void
    {
        $hr = User::factory()->create(['must_change_password' => false]);
        $hr->givePermissionTo(['hr.employees.view', 'hr.salaries.view', 'hr.salaries.manage']);

        $employee = User::factory()->create(['must_change_password' => false]);

        Livewire::actingAs($hr)
            ->test(EmployeeProfileShow::class, ['user' => $employee])
            ->call('setTab', 'salary')
            ->set('overtimeGate', 'مفتوح')
            ->call('saveOvertimeGate')
            ->assertHasNoErrors();

        $this->assertTrue((bool) $employee->fresh()->profile?->overtime_unlocked);
    }

    public function test_audit_log_route_requires_dedicated_permission(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo('reports.view');

        $this->actingAs($user)->get(route('reports.audit-log'))->assertForbidden();

        $user->givePermissionTo('reports.audit-log.view');
        $this->actingAs($user)->get(route('reports.audit-log'))->assertOk();
    }
}
