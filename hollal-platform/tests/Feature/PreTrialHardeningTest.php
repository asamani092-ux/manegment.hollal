<?php

namespace Tests\Feature;

use App\Livewire\Documents\DocumentVersionsIndex;
use App\Livewire\Hr\HrLifecycleIndex;
use App\Livewire\Hr\LeavesIndex;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\EmployeeProfile;
use App\Models\LeaveRequest;
use App\Models\OrgUnit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regressions for the pre-trial audit: self-approval, balance reservation,
 * schema drift on list screens, and confidentiality scoping of versions.
 */
class PreTrialHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        Notification::fake();
    }

    public function test_approver_cannot_approve_own_leave(): void
    {
        $admin = User::factory()->create(['must_change_password' => false]);
        $admin->assignRole('Super Admin');

        EmployeeProfile::create(['user_id' => $admin->id, 'annual_leave_balance' => 10]);

        $leave = LeaveRequest::create([
            'employee_id' => $admin->id,
            'type' => LeaveRequest::TYPE_ANNUAL,
            'from_date' => now()->toDateString(),
            'to_date' => now()->addDay()->toDateString(),
            'days_count' => 2,
            'status' => LeaveRequest::STATUS_SUBMITTED,
        ]);

        Livewire::actingAs($admin)
            ->test(LeavesIndex::class)
            ->call('approve', $leave->id)
            ->assertForbidden();

        $this->assertSame(LeaveRequest::STATUS_SUBMITTED, $leave->fresh()->status);
        $this->assertSame(10, (int) $admin->fresh()->profile->annual_leave_balance);
    }

    public function test_pending_annual_days_are_reserved_against_balance(): void
    {
        $manager = User::factory()->create(['must_change_password' => false, 'email' => 'mgr2@test.local']);
        $employee = User::factory()->create([
            'must_change_password' => false,
            'manager_id' => $manager->id,
            'email' => 'emp2@test.local',
        ]);
        $employee->assignRole('Employee');

        EmployeeProfile::create(['user_id' => $employee->id, 'annual_leave_balance' => 5]);

        Livewire::actingAs($employee)
            ->test(LeavesIndex::class)
            ->call('openForm')
            ->set('type', LeaveRequest::TYPE_ANNUAL)
            ->set('from_date', now()->toDateString())
            ->set('to_date', now()->addDays(3)->toDateString())
            ->set('reason', 'أولى')
            ->call('submitLeave')
            ->assertHasNoErrors();

        // 4 أيام محجوزة من رصيد 5 — طلب 3 أيام إضافية يجب أن يُرفض.
        Livewire::actingAs($employee)
            ->test(LeavesIndex::class)
            ->call('openForm')
            ->set('type', LeaveRequest::TYPE_ANNUAL)
            ->set('from_date', now()->addDays(10)->toDateString())
            ->set('to_date', now()->addDays(12)->toDateString())
            ->set('reason', 'ثانية')
            ->call('submitLeave');

        $this->assertSame(1, LeaveRequest::count());
    }

    public function test_overlapping_leave_is_rejected(): void
    {
        $employee = User::factory()->create(['must_change_password' => false, 'email' => 'emp3@test.local']);
        $employee->assignRole('Employee');
        EmployeeProfile::create(['user_id' => $employee->id, 'annual_leave_balance' => 30]);

        LeaveRequest::create([
            'employee_id' => $employee->id,
            'type' => LeaveRequest::TYPE_ANNUAL,
            'from_date' => now()->addDays(5)->toDateString(),
            'to_date' => now()->addDays(8)->toDateString(),
            'days_count' => 4,
            'status' => LeaveRequest::STATUS_APPROVED,
        ]);

        Livewire::actingAs($employee)
            ->test(LeavesIndex::class)
            ->call('openForm')
            ->set('type', LeaveRequest::TYPE_SICK)
            ->set('from_date', now()->addDays(7)->toDateString())
            ->set('to_date', now()->addDays(9)->toDateString())
            ->call('submitLeave');

        $this->assertSame(1, LeaveRequest::count());
    }

    public function test_jobs_index_renders_with_real_rows(): void
    {
        $admin = User::factory()->create(['must_change_password' => false]);
        $admin->assignRole('Super Admin');

        $unit = OrgUnit::create(['name' => 'وحدة البرامج', 'level' => OrgUnit::LEVEL_UNIT]);
        OrgUnit::create([
            'name' => 'أخصائي برامج',
            'level' => OrgUnit::LEVEL_JOB,
            'parent_id' => $unit->id,
            'manager_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('structure.jobs'))
            ->assertOk()
            ->assertSee('أخصائي برامج', false)
            ->assertSee($admin->name, false)
            ->assertDontSee('نشطة', false);
    }

    public function test_versions_of_invisible_documents_are_hidden(): void
    {
        $owner = User::factory()->create(['must_change_password' => false, 'email' => 'own@test.local']);
        $owner->assignRole('Employee');

        $other = User::factory()->create(['must_change_password' => false, 'email' => 'oth@test.local']);
        $other->assignRole('Employee');

        $document = Document::create([
            'title' => 'مستند إداري سري',
            'category' => 'عام',
            'path' => 'documents/secret.pdf',
            'current_version' => 1,
            'uploader_id' => $owner->id,
            'confidentiality' => 'managers',
        ]);

        DocumentVersion::create([
            'document_id' => $document->id,
            'version' => 1,
            'path' => 'documents/secret.pdf',
            'change_note' => 'نسخة سرية',
            'uploaded_by' => $owner->id,
        ]);

        Livewire::actingAs($other)
            ->test(DocumentVersionsIndex::class)
            ->assertDontSee('مستند إداري سري');
    }

    public function test_user_cannot_offboard_self(): void
    {
        $admin = User::factory()->create(['must_change_password' => false]);
        $admin->assignRole('Super Admin');

        Livewire::actingAs($admin)
            ->test(HrLifecycleIndex::class)
            ->call('startOffboarding', $admin->id)
            ->assertForbidden();

        $this->assertNotSame(User::STATUS_TERMINATED, $admin->fresh()->employment_status);
    }
}
