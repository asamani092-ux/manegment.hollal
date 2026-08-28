<?php

namespace Tests\Feature;

use App\Models\AttendanceColumnMap;
use App\Models\AttendanceCycleApproval;
use App\Models\AttendanceRecord;
use App\Models\EmployeeProfile;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\AttendanceDeductionService;
use App\Services\AttendanceService;
use App\Support\Setting;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Round 4 batch 3-path1 — interactive import, replace, manual hours, column learning. */
class AttendanceImportPath1Test extends TestCase
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
        Setting::set('attendance.office_start_time', '08:00');
        Setting::set('attendance.cycle_start_day', 25);
    }

    private function writeCsv(string $name, string $body): string
    {
        $path = storage_path('app/'.$name);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $body);

        return $path;
    }

    public function test_column_mapping_from_arabic_or_latin_headers(): void
    {
        $svc = app(AttendanceService::class);
        $headers = ['رقم الجهاز', 'تاريخ اليوم', 'دخول', 'خروج'];
        $map = $svc->guessMappingFromHeaders($headers);

        $this->assertSame(0, $map['fingerprint']);
        $this->assertSame(1, $map['date']);
        $this->assertSame(2, $map['check_in']);
        $this->assertSame(3, $map['check_out']);

        $path = $this->writeCsv(
            'map-test.csv',
            "رقم الجهاز,تاريخ اليوم,دخول,خروج\nFP-1,2026-08-05,08:00,16:00\n"
        );
        $parsed = $svc->parseFileHeaders($path);
        $this->assertSame(['رقم الجهاز', 'تاريخ اليوم', 'دخول', 'خروج'], $parsed);

        $mapped = $svc->mapDataRows(
            [['FP-1', '2026-08-05', '08:00', '16:00']],
            $map
        );
        $this->assertSame('FP-1', $mapped[0]['fingerprint']);
        $this->assertSame('08:00', $mapped[0]['check_in']);
        @unlink($path);
    }

    public function test_replace_upload_clears_prior_fingerprint_month_and_wins_over_platform(): void
    {
        $hr = User::factory()->create(['must_change_password' => false]);
        $employee = User::factory()->create(['attendance_enabled' => true, 'is_active' => true]);
        EmployeeProfile::create(['user_id' => $employee->id, 'fingerprint_id' => 'FP-R1']);

        // Prior fingerprint import for August
        AttendanceRecord::create([
            'employee_id' => $employee->id,
            'date' => '2026-08-02',
            'check_in_at' => Carbon::parse('2026-08-02 07:00:00'),
            'check_out_at' => Carbon::parse('2026-08-02 15:00:00'),
            'type' => 'حضور',
            'source' => 'بصمة',
            'declared_by' => $hr->id,
        ]);
        // Platform punch same day as new import — fingerprint must win
        AttendanceRecord::create([
            'employee_id' => $employee->id,
            'date' => '2026-08-10',
            'check_in_at' => Carbon::parse('2026-08-10 09:00:00'),
            'type' => 'حضور',
            'source' => 'يدوي',
            'declared_by' => $employee->id,
        ]);

        $path = $this->writeCsv(
            'replace-test.csv',
            "fingerprint_id,date,check_in,check_out\nFP-R1,2026-08-10,08:05,16:00\n"
        );

        $result = app(AttendanceService::class)->importFile($path, $hr, 'جهاز تجريبي', '2026-08');
        $this->assertSame(1, $result['rows']);

        $this->assertFalse(
            AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->whereDate('date', '2026-08-02')
                ->exists(),
            'Prior fingerprint rows for the month must be replaced'
        );

        $day = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', '2026-08-10')
            ->first();
        $this->assertNotNull($day);
        $this->assertSame('بصمة', $day->source);
        $this->assertSame('08:05', $day->check_in_at->format('H:i'));
        @unlink($path);
    }

    public function test_manual_late_hours_deduction_from_settings_formulas(): void
    {
        $hr = User::factory()->create(['must_change_password' => false]);
        $employee = User::factory()->create([
            'attendance_enabled' => true,
            'is_active' => true,
            'name' => 'موظف يدوي',
        ]);
        EmployeeProfile::create(['user_id' => $employee->id]);
        SalaryComponent::create([
            'employee_id' => $employee->id,
            'type' => SalaryComponent::TYPE_BASE,
            'label_ar' => 'أساسي',
            'amount' => 3000,
            'valid_from' => '2026-01-01',
            'is_active' => true,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-15'));
        $svc = app(AttendanceDeductionService::class);
        $cycle = $svc->currentCycle(now());

        // hourValue = (3000/30)/8 = 12.5 → 2h late = 25
        $expectedLate = $svc->lateDeductionFromHours(2.0, 3000);
        $this->assertEqualsWithDelta(25.0, $expectedLate, 0.01);

        $svc->saveManualIndicator($employee, $cycle['from'], $cycle['to'], 2.0, 1, $hr);
        $summary = $svc->employeeCycleSummary($employee, $cycle['from'], $cycle['to'], 3000);

        $this->assertSame('يدوي', $summary['source']);
        $this->assertEqualsWithDelta(25.0, $summary['late_deduction'], 0.01);
        $this->assertEqualsWithDelta(
            $svc->absenceDeductionFromDays(1, 3000),
            $summary['absence_deduction'],
            0.01
        );
        $this->assertEqualsWithDelta(
            $summary['late_deduction'] + $summary['absence_deduction'],
            $summary['total_deduction'],
            0.01
        );
        Carbon::setTestNow();
    }

    public function test_column_map_is_learned_per_source_and_suggested_next_time(): void
    {
        $hr = User::factory()->create(['must_change_password' => false]);
        $employee = User::factory()->create(['attendance_enabled' => true, 'is_active' => true]);
        EmployeeProfile::create(['user_id' => $employee->id, 'fingerprint_id' => 'FP-L1']);

        $path = $this->writeCsv(
            'learn-test.csv',
            "كود,يوم,من,إلى\nFP-L1,2026-08-11,08:10,16:00\n"
        );

        $svc = app(AttendanceService::class);
        $headers = $svc->parseFileHeaders($path);
        $mapping = [
            'fingerprint' => 0,
            'date' => 1,
            'check_in' => 2,
            'check_out' => 3,
        ];

        $import = $svc->stageImport($path, $hr, 'جهاز الفرع', '2026-08', $mapping);
        $this->assertSame('مسودة', $import->status);
        $result = $svc->commitReplaceImport($import, $hr);
        $this->assertSame(1, $result['rows']);

        $learned = AttendanceColumnMap::query()->where('source_label', 'جهاز الفرع')->first();
        $this->assertNotNull($learned);
        $this->assertSame($mapping, $learned->mapping);
        $this->assertSame(['كود', 'يوم', 'من', 'إلى'], $learned->headers);

        $suggested = $svc->suggestMapping('جهاز الفرع', ['كود', 'يوم', 'من', 'إلى']);
        $this->assertSame($mapping, $suggested);
        @unlink($path);
    }

    public function test_unknown_fingerprint_requires_manual_match_before_commit(): void
    {
        $hr = User::factory()->create(['must_change_password' => false]);
        $employee = User::factory()->create([
            'attendance_enabled' => true,
            'is_active' => true,
            'name' => 'موظف بلا بصمة',
        ]);
        EmployeeProfile::create(['user_id' => $employee->id, 'fingerprint_id' => null]);

        $path = $this->writeCsv(
            'unmatched.csv',
            "fingerprint_id,date,check_in,check_out\nUNKNOWN-99,2026-08-12,08:00,16:00\n"
        );

        $svc = app(AttendanceService::class);
        $import = $svc->stageImport($path, $hr, 'مصدر', '2026-08', [
            'fingerprint' => 0,
            'date' => 1,
            'check_in' => 2,
            'check_out' => 3,
        ]);

        $this->assertSame('بانتظار_مطابقة', $import->status);
        $this->assertCount(1, $import->unmatched_rows);

        $this->expectException(\InvalidArgumentException::class);
        $svc->commitReplaceImport($import, $hr);
    }

    public function test_manual_match_then_commit_and_correction_flow(): void
    {
        $hr = User::factory()->create(['must_change_password' => false]);
        $hr->givePermissionTo('hr.employees.update');
        $employee = User::factory()->create([
            'attendance_enabled' => true,
            'is_active' => true,
        ]);
        EmployeeProfile::create(['user_id' => $employee->id]);

        $path = $this->writeCsv(
            'match-commit.csv',
            "fingerprint_id,date,check_in,check_out\nNEW-FP,2026-08-13,08:00,16:00\n"
        );
        $svc = app(AttendanceService::class);
        $import = $svc->stageImport($path, $hr, 'مصدر', '2026-08', [
            'fingerprint' => 0,
            'date' => 1,
            'check_in' => 2,
            'check_out' => 3,
        ]);
        $svc->applyManualFingerprintMatches($import, [0 => $employee->id]);
        $import = $import->fresh();
        $this->assertSame('مسودة', $import->status);
        $this->assertSame('NEW-FP', $employee->fresh()->profile->fingerprint_id);

        $result = $svc->commitReplaceImport($import, $hr);
        $this->assertSame(1, $result['rows']);

        Carbon::setTestNow(Carbon::parse('2026-08-15'));
        $deduction = app(AttendanceDeductionService::class);
        $cycle = $deduction->currentCycle(now());
        $approval = $deduction->approveCycle($cycle['from'], $cycle['to'], $hr);
        $this->assertSame(AttendanceCycleApproval::STATUS_APPROVED, $approval->status);

        $deduction->requestCorrection($approval, 'تعديل ساعات بعد مراجعة الجهاز', $hr);
        $this->assertSame(AttendanceCycleApproval::STATUS_CORRECTION_PENDING, $approval->fresh()->status);

        $deduction->approveCorrection($approval->fresh(), $hr);
        $this->assertSame(AttendanceCycleApproval::STATUS_DRAFT, $approval->fresh()->status);

        @unlink($path);
        Carbon::setTestNow();
    }
}
