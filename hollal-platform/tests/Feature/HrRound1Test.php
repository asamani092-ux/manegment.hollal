<?php

namespace Tests\Feature;

use App\Livewire\Users\EmployeeProfileShow;
use App\Livewire\Users\UsersIndex;
use App\Models\Contract;
use App\Models\EmployeeDocument;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\OffboardingService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** HR round-1: directory fields, documents, early offboarding clearance. */
class HrRound1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    private function hrAdmin(): User
    {
        $user = User::factory()->create([
            'must_change_password' => false,
            'is_active' => true,
        ]);
        $user->assignRole('Super Admin');

        return $user;
    }

    public function test_create_user_persists_hr_profile_fields(): void
    {
        $admin = $this->hrAdmin();

        Livewire::actingAs($admin)
            ->test(UsersIndex::class)
            ->call('openCreateModal')
            ->set('name', 'موظف تجريبي')
            ->set('email', 'emp@example.com')
            ->set('phone', '0555555555')
            ->set('password', 'password123')
            ->set('roleName', 'Employee')
            ->set('job_title', 'محاسب')
            ->set('employment_type', 'دوام_كامل')
            ->set('hire_date', '2026-01-15')
            ->set('onboarding_assignee_id', $admin->id)
            ->call('save')
            ->assertHasNoErrors();

        $user = User::query()->where('email', 'emp@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('Employee'));
        $this->assertDatabaseHas('employees_profile', [
            'user_id' => $user->id,
            'job_title' => 'محاسب',
            'employment_type' => 'دوام_كامل',
        ]);
    }

    public function test_profile_can_change_role_and_save_document(): void
    {
        Storage::fake('local');
        $admin = $this->hrAdmin();
        $target = User::factory()->create(['must_change_password' => false]);
        $target->assignRole('Employee');
        EmployeeProfile::create(['user_id' => $target->id, 'job_title' => 'موظف']);

        Livewire::actingAs($admin)
            ->test(EmployeeProfileShow::class, ['user' => $target])
            ->call('openEdit')
            ->set('editRoleName', 'Finance')
            ->set('editHireDate', '2025-06-01')
            ->set('editNationalId', '1234567890')
            ->call('saveProfile')
            ->assertHasNoErrors();

        $this->assertTrue($target->fresh()->hasRole('Finance'));
        $this->assertSame('1234567890', $target->fresh()->profile?->national_id);

        Livewire::actingAs($admin)
            ->test(EmployeeProfileShow::class, ['user' => $target])
            ->call('openDocumentModal')
            ->set('docType', EmployeeDocument::TYPE_ID)
            ->set('docNumber', 'ID-99')
            ->set('docExpiryDate', now()->addDays(20)->toDateString())
            ->set('docFile', UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'))
            ->call('saveDocument')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('employee_documents', [
            'user_id' => $target->id,
            'type' => 'هوية',
            'document_number' => 'ID-99',
        ]);
    }

    public function test_early_offboarding_requires_clearance_document(): void
    {
        $admin = $this->hrAdmin();
        $employee = User::factory()->create([
            'must_change_password' => false,
            'is_active' => true,
            'employment_status' => User::STATUS_ACTIVE,
        ]);
        EmployeeProfile::create(['user_id' => $employee->id]);
        Contract::create([
            'employee_id' => $employee->id,
            'start_date' => now()->subYear()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'status' => 'active',
        ]);

        $svc = app(OffboardingService::class);
        $svc->offboard($employee, $admin);
        $employee->refresh();

        $this->assertTrue($svc->isEarlyTermination($employee));
        $holds = $svc->holds($employee);
        $this->assertTrue(collect($holds)->contains(fn ($h) => str_contains($h, 'مخالصة')));

        EmployeeDocument::create([
            'user_id' => $employee->id,
            'type' => EmployeeDocument::TYPE_CLEARANCE,
            'file_path' => 'employee-documents/clearance.pdf',
            'uploaded_by' => $admin->id,
        ]);

        // Complete checklist tasks so only clearance was the blocker
        \App\Models\Task::query()
            ->where('related_user_id', $employee->id)
            ->where('role_label', 'إنهاء_علاقة')
            ->update(['status' => \App\Services\TaskLifecycleService::STATUS_COMPLETED]);

        $holds = $svc->holds($employee->fresh());
        $this->assertFalse(collect($holds)->contains(fn ($h) => str_contains($h, 'مخالصة')));

        $svc->complete($employee->fresh(), $admin);
        $this->assertSame(User::STATUS_TERMINATED, $employee->fresh()->employment_status);
    }

    public function test_contracts_without_contract_filter(): void
    {
        $admin = $this->hrAdmin();
        $with = User::factory()->create(['is_active' => true, 'name' => 'مع عقد']);
        $without = User::factory()->create(['is_active' => true, 'name' => 'بدون عقد']);
        Contract::create([
            'employee_id' => $with->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'status' => 'active',
        ]);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\Contracts\ContractsIndex::class)
            ->call('toggleWithoutContract')
            ->assertSee('بدون عقد', false)
            ->assertDontSee('مع عقد', false);
    }
}
