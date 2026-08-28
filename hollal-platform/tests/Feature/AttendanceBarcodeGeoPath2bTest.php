<?php

namespace Tests\Feature;

use App\Livewire\Hr\AttendanceIndex;
use App\Models\AttendanceLocation;
use App\Models\AttendanceRecord;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\Setting;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Path-2ب: fixed site barcode, multi geofence locations, first-wins punch rule.
 */
class AttendanceBarcodeGeoPath2bTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformSettingsSeeder::class);
        Setting::set('attendance.site_barcode_token', 'hollal-site-demo');
        Setting::set('attendance.office_start_time', '08:00');
    }

    public function test_barcode_check_in_requires_attendance_enabled_and_valid_token(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 08:10:00'));

        $disabled = User::factory()->create(['attendance_enabled' => false]);
        $svc = app(AttendanceService::class);

        try {
            $svc->checkInViaBarcode($disabled, 'hollal-site-demo');
            $this->fail('Expected exception for disabled attendance');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('غير مُفعّل', $e->getMessage());
        }

        $employee = User::factory()->create(['attendance_enabled' => true]);
        EmployeeProfile::create(['user_id' => $employee->id]);

        try {
            $svc->checkInViaBarcode($employee, 'wrong-token');
            $this->fail('Expected invalid barcode exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('غير صالح', $e->getMessage());
        }

        $record = $svc->checkInViaBarcode($employee, 'hollal-site-demo');
        $this->assertSame(AttendanceService::SOURCE_BARCODE, $record->source);
        $this->assertSame(AttendanceService::TYPE_PRESENT, $record->type);
        $this->assertNotNull($record->check_in_at);

        Carbon::setTestNow();
    }

    public function test_geo_check_in_inside_and_outside_radius(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 08:05:00'));

        $hq = AttendanceLocation::create([
            'name' => 'المقر الرئيسي',
            'latitude' => 24.7136,
            'longitude' => 46.6753,
            'radius_meters' => 200,
            'is_active' => true,
        ]);
        AttendanceLocation::create([
            'name' => 'فرع متوقف',
            'latitude' => 21.3891,
            'longitude' => 39.8579,
            'radius_meters' => 100,
            'is_active' => false,
        ]);

        $employee = User::factory()->create(['attendance_enabled' => true]);
        EmployeeProfile::create(['user_id' => $employee->id]);
        $svc = app(AttendanceService::class);

        try {
            $svc->checkInViaLocation($employee, 24.8000, 46.8000);
            $this->fail('Expected outside geofence exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('خارج نطاق', $e->getMessage());
        }

        // Near HQ (~50m offset approx 0.00045 deg)
        $record = $svc->checkInViaLocation($employee, 24.7139, 46.6755);
        $this->assertSame(AttendanceService::SOURCE_LOCATION, $record->source);
        $this->assertSame($hq->id, (int) $record->attendance_location_id);
        $this->assertSame('المقر الرئيسي', $record->field_location);

        Carbon::setTestNow();
    }

    public function test_first_wins_keeps_earlier_channel_until_fingerprint_import(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 08:00:00'));

        AttendanceLocation::create([
            'name' => 'المقر',
            'latitude' => 24.7136,
            'longitude' => 46.6753,
            'radius_meters' => 500,
            'is_active' => true,
        ]);

        $employee = User::factory()->create(['attendance_enabled' => true]);
        EmployeeProfile::create([
            'user_id' => $employee->id,
            'fingerprint_id' => 'FP-FIRST',
        ]);
        $svc = app(AttendanceService::class);

        $first = $svc->checkInViaBarcode($employee, 'hollal-site-demo');
        $firstAt = $first->check_in_at?->toDateTimeString();
        $this->assertSame(AttendanceService::SOURCE_BARCODE, $first->source);

        Carbon::setTestNow(Carbon::parse('2026-08-24 08:30:00'));

        try {
            $svc->checkInViaLocation($employee, 24.7136, 46.6753);
            $this->fail('Expected first-wins exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('الأسبق', $e->getMessage());
        }

        try {
            $svc->checkIn($employee);
            $this->fail('Expected first-wins on manual too');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('الأسبق', $e->getMessage());
        }

        $kept = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', '2026-08-24')
            ->first();
        $this->assertSame(AttendanceService::SOURCE_BARCODE, $kept->source);
        $this->assertSame($firstAt, $kept->check_in_at?->toDateTimeString());

        // Path-1 fingerprint import replaces.
        $csv = "fingerprint_id,date,check_in,check_out\nFP-FIRST,2026-08-24,07:55,16:00\n";
        $path = storage_path('app/test-path2b-first.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $csv);
        $imported = $svc->importCsv($path, $employee);
        @unlink($path);

        $this->assertSame(1, $imported['rows']);
        $after = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', '2026-08-24')
            ->first();
        $this->assertSame(AttendanceService::SOURCE_FINGERPRINT, $after->source);
        $this->assertSame('07:55', $after->check_in_at?->format('H:i'));

        Carbon::setTestNow();
    }

    public function test_hr_can_manage_barcode_and_locations_from_attendance_index(): void
    {
        $hr = User::factory()->create(['must_change_password' => false]);
        $hr->givePermissionTo('hr.employees.update');
        $hr->givePermissionTo('hr.employees.view');

        Livewire::actingAs($hr)
            ->test(AttendanceIndex::class)
            ->set('siteBarcodeToken', 'new-fixed-token')
            ->call('saveSiteBarcode')
            ->assertHasNoErrors();

        $this->assertSame('new-fixed-token', Setting::get('attendance.site_barcode_token'));

        Livewire::actingAs($hr)
            ->test(AttendanceIndex::class)
            ->call('openLocationForm')
            ->set('locationName', 'فرع الشمال')
            ->set('locationLatitude', '24.8000000')
            ->set('locationLongitude', '46.7000000')
            ->set('locationRadius', 120)
            ->call('saveLocation')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('attendance_locations', [
            'name' => 'فرع الشمال',
            'radius_meters' => 120,
            'is_active' => 1,
        ]);
    }

    public function test_inactive_location_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-25 09:00:00'));

        AttendanceLocation::create([
            'name' => 'متوقف',
            'latitude' => 24.7136,
            'longitude' => 46.6753,
            'radius_meters' => 300,
            'is_active' => false,
        ]);

        $employee = User::factory()->create(['attendance_enabled' => true]);
        EmployeeProfile::create(['user_id' => $employee->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('خارج نطاق');
        app(AttendanceService::class)->checkInViaLocation($employee, 24.7136, 46.6753);
    }
}
