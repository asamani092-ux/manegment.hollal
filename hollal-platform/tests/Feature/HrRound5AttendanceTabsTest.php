<?php

namespace Tests\Feature;

use App\Livewire\Hr\AttendanceIndex;
use App\Livewire\Hr\HeaderAttendancePunch;
use App\Models\AttendanceLocation;
use App\Models\User;
use App\Models\WorkShift;
use App\Support\Setting;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HR Round 5 batch ج — attendance page in-page tabs (profile-style).
 */
class HrRound5AttendanceTabsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformSettingsSeeder::class);
    }

    public function test_hr_sees_all_attendance_tabs_and_can_switch(): void
    {
        $hr = User::factory()->create(['must_change_password' => false]);
        $hr->givePermissionTo(['hr.employees.view', 'hr.employees.update']);

        Livewire::actingAs($hr)
            ->test(AttendanceIndex::class)
            ->assertSet('activeTab', 'enablement')
            ->assertSeeHtml("setTab('enablement')")
            ->assertSeeHtml("setTab('shifts')")
            ->assertSeeHtml("setTab('barcode')")
            ->assertSeeHtml("setTab('pending')")
            ->assertSeeHtml("setTab('log')")
            ->assertSeeHtml("setTab('print')")
            ->assertSee('تفعيل', false)
            ->assertSee('ورديات وإسناد', false)
            ->assertSee('باركود ومواقع', false)
            ->assertSee('اعتماد معلّق', false)
            ->assertSee('سجل', false)
            ->assertSee('طباعة', false)
            ->call('setTab', 'shifts')
            ->assertSet('activeTab', 'shifts')
            ->assertSee('الورديات والإسناد', false)
            ->call('setTab', 'barcode')
            ->assertSet('activeTab', 'barcode')
            ->assertSee('باركود المقر ومواقع الحضور', false)
            ->call('setTab', 'log')
            ->assertSet('activeTab', 'log')
            ->assertSee('سجل الحضور والانصراف', false);
    }

    public function test_employee_without_manage_sees_log_and_print_only(): void
    {
        $employee = User::factory()->create([
            'must_change_password' => false,
            'attendance_enabled' => true,
        ]);

        Livewire::actingAs($employee)
            ->test(AttendanceIndex::class)
            ->assertSet('activeTab', 'log')
            ->assertSeeHtml("setTab('log')")
            ->assertSeeHtml("setTab('print')")
            ->assertDontSeeHtml("setTab('enablement')")
            ->assertDontSeeHtml("setTab('shifts')")
            ->assertDontSeeHtml("setTab('barcode')")
            ->call('setTab', 'print')
            ->assertSet('activeTab', 'print')
            ->assertSee('طباعة السجل الشهري', false);
    }

    public function test_shift_assign_and_barcode_still_work_via_tabs(): void
    {
        $hr = User::factory()->create(['must_change_password' => false]);
        $hr->givePermissionTo(['hr.employees.view', 'hr.employees.update']);
        $employee = User::factory()->create([
            'must_change_password' => false,
            'is_active' => true,
            'name' => 'موظف تبويب',
        ]);

        Livewire::actingAs($hr)
            ->test(AttendanceIndex::class)
            ->call('setTab', 'shifts')
            ->call('openShiftForm')
            ->set('shiftName', 'وردية تبويب')
            ->set('shiftStart', '08:00')
            ->set('shiftEnd', '16:00')
            ->set('shiftGrace', 10)
            ->set('shiftWeekdays', [0, 1, 2, 3, 4])
            ->call('saveShift')
            ->assertHasNoErrors();

        $shift = WorkShift::query()->where('name', 'وردية تبويب')->first();
        $this->assertNotNull($shift);

        Livewire::actingAs($hr)
            ->test(AttendanceIndex::class)
            ->call('setTab', 'shifts')
            ->set('assignEmployeeId', $employee->id)
            ->set('assignShiftId', $shift->id)
            ->call('assignShift')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('employees_profile', [
            'user_id' => $employee->id,
            'work_shift_id' => $shift->id,
        ]);

        Livewire::actingAs($hr)
            ->test(AttendanceIndex::class)
            ->call('setTab', 'barcode')
            ->set('siteBarcodeToken', 'tab-barcode-token')
            ->call('saveSiteBarcode')
            ->assertHasNoErrors()
            ->call('openLocationForm')
            ->set('locationName', 'موقع تبويب')
            ->set('locationLatitude', '24.7000000')
            ->set('locationLongitude', '46.6800000')
            ->set('locationRadius', 100)
            ->call('saveLocation')
            ->assertHasNoErrors();

        $this->assertSame('tab-barcode-token', Setting::get('attendance.site_barcode_token'));
        $this->assertDatabaseHas('attendance_locations', [
            'name' => 'موقع تبويب',
            'radius_meters' => 100,
            'is_active' => 1,
        ]);
        $this->assertInstanceOf(AttendanceLocation::class, AttendanceLocation::query()->where('name', 'موقع تبويب')->first());
    }

    public function test_header_attendance_punch_still_renders(): void
    {
        $user = User::factory()->create([
            'must_change_password' => false,
            'attendance_enabled' => true,
        ]);

        Livewire::actingAs($user)
            ->test(HeaderAttendancePunch::class)
            ->assertSee('تسجيل الحضور');
    }

    public function test_query_tab_selects_panel_on_mount(): void
    {
        $hr = User::factory()->create(['must_change_password' => false]);
        $hr->givePermissionTo(['hr.employees.view', 'hr.employees.update']);

        Livewire::actingAs($hr)
            ->withQueryParams(['tab' => 'print'])
            ->test(AttendanceIndex::class)
            ->assertSet('activeTab', 'print')
            ->assertSee('طباعة السجل الشهري', false);
    }
}
