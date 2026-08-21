<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\EmployeeProfile;
use App\Models\PayrollRun;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\AttendanceDeductionService;
use App\Services\AttendanceService;
use App\Services\PayrollRunService;
use App\Support\Setting;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** ATT-1…4 — hybrid attendance, cycle deductions, barcode, CSV. */
class AttendanceCycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformSettingsSeeder::class);
        Setting::set('attendance.grace_minutes', 15);
        Setting::set('attendance.absence_multiplier', 1.5);
        Setting::set('attendance.cycle_days', 30);
        Setting::set('hr.daily_base_hours', 8);
        Setting::set('attendance.site_barcode_token', 'hollal-site-demo');
        Setting::set('attendance.office_start_time', '08:00');
    }

    public function test_late_charges_full_minutes_after_grace(): void
    {
        $svc = app(AttendanceDeductionService::class);
        $this->assertSame(0, $svc->chargeableLateMinutes(10));
        $this->assertSame(20, $svc->chargeableLateMinutes(20));
    }

    public function test_absence_and_late_deduction_formulas(): void
    {
        $employee = User::factory()->create(['attendance_enabled' => true, 'name' => 'موظف حضور']);
        EmployeeProfile::create(['user_id' => $employee->id]);
        SalaryComponent::create([
            'employee_id' => $employee->id,
            'type' => SalaryComponent::TYPE_BASE,
            'label_ar' => 'أساسي',
            'amount' => 3000,
            'valid_from' => now()->subMonth()->toDateString(),
            'is_active' => true,
        ]);

        // One late day only → many absences in cycle window around mid-month
        Carbon::setTestNow(Carbon::parse('2026-08-10 09:00:00'));
        AttendanceRecord::create([
            'employee_id' => $employee->id,
            'date' => '2026-08-10',
            'check_in_at' => Carbon::parse('2026-08-10 08:30:00'),
            'type' => 'حضور',
            'source' => 'يدوي',
            'late_minutes' => 30,
            'declared_by' => $employee->id,
        ]);

        $svc = app(AttendanceDeductionService::class);
        $cycle = $svc->currentCycle(Carbon::parse('2026-08-10'));
        $summary = $svc->employeeCycleSummary($employee, $cycle['from'], $cycle['to'], 3000);

        $this->assertSame(30, $summary['chargeable_late_minutes']);
        $this->assertGreaterThan(0, $summary['late_deduction']);
        $this->assertGreaterThan(0, $summary['absence_deduction']);
        $this->assertEqualsWithDelta(
            $summary['late_deduction'] + $summary['absence_deduction'],
            $summary['total_deduction'],
            0.01
        );
        Carbon::setTestNow();
    }

    public function test_approve_cycle_applies_to_payroll_draft(): void
    {
        $hr = User::factory()->create(['must_change_password' => false]);
        $hr->givePermissionTo('hr.employees.update');

        $employee = User::factory()->create(['attendance_enabled' => true, 'is_active' => true]);
        EmployeeProfile::create(['user_id' => $employee->id]);
        SalaryComponent::create([
            'employee_id' => $employee->id,
            'type' => SalaryComponent::TYPE_BASE,
            'label_ar' => 'أساسي',
            'amount' => 6000,
            'valid_from' => now()->subMonths(2)->toDateString(),
            'is_active' => true,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-15'));
        $svc = app(AttendanceDeductionService::class);
        $cycle = $svc->currentCycle(now());
        $approval = $svc->approveCycle($cycle['from'], $cycle['to'], $hr);
        $this->assertSame('معتمد', $approval->status);

        $run = app(PayrollRunService::class)->generate('2026-08');
        $this->assertNotNull($run->cycle_from);
        $applied = $svc->applyApprovedToPayrollDraft($run, $approval);
        $this->assertGreaterThanOrEqual(1, $applied);

        $item = $run->items()->where('employee_id', $employee->id)->first();
        $this->assertNotNull($item);
        $vars = collect($item->variables ?? []);
        $this->assertTrue($vars->contains(fn ($v) => ($v['label'] ?? '') === 'خصم حضور الدورة'));
        Carbon::setTestNow();
    }

    public function test_barcode_and_csv_import(): void
    {
        $employee = User::factory()->create(['attendance_enabled' => true]);
        EmployeeProfile::create([
            'user_id' => $employee->id,
            'fingerprint_id' => 'FP-100',
            'is_field_worker' => true,
        ]);
        $employee->load('profile');

        $att = app(AttendanceService::class);
        $rec = $att->checkInViaBarcode($employee, 'hollal-site-demo');
        $this->assertSame('باركود', $rec->source);

        $field = $att->startFieldWork($employee, 'موقع تجريبي');
        $this->assertSame('بانتظار', $field->approval_status);
        $att->approveFieldWork($field, $employee);
        $this->assertSame('معتمد', $field->fresh()->approval_status);

        $csv = "fingerprint_id,date,check_in,check_out\nFP-100,2026-08-01,08:05,16:00\n";
        $path = storage_path('app/test-att.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $csv);
        $result = $att->importCsv($path, $employee);
        $this->assertSame(1, $result['rows']);
        @unlink($path);
    }

    public function test_cycle_page_requires_hr_update(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo('hr.employees.view');
        $this->actingAs($user)->get(route('attendance.cycle'))->assertForbidden();

        $hr = User::factory()->create(['must_change_password' => false]);
        $hr->givePermissionTo('hr.employees.update');
        $this->actingAs($hr)->get(route('attendance.cycle'))->assertOk();
    }
}
