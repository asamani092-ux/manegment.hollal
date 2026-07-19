<?php

namespace Tests\Feature;

use App\Livewire\Settings\GrantsIndex;
use App\Livewire\Structure\OrgTreeIndex;
use App\Models\Committee;
use App\Models\Department;
use App\Models\ExceptionalGrant;
use App\Models\Meeting;
use App\Models\OrgUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\BackupService;
use App\Services\OrgStructureService;
use App\Services\PermissionGrantService;
use App\Support\Setting;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 09-B1 — org tree, transfers, committees.
 * 10-B1 — grant screen, exceptional grants, review matrix.
 * 11-B1 — remaining settings sections read live, with old/new logging.
 */
class StructureRolesSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(PermissionSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo([
            'structure.departments.view', 'structure.departments.create', 'structure.departments.update',
            'roles.view', 'roles.update', 'settings.manage',
        ]);

        return $user;
    }

    // ------------------------------------------------------------ 09-B1

    public function test_org_tree_enforces_administration_unit_job_order(): void
    {
        $service = app(OrgStructureService::class);

        $administration = $service->createUnit('إدارة البرامج', OrgUnit::LEVEL_ADMINISTRATION);
        $unit = $service->createUnit('وحدة التدريب', OrgUnit::LEVEL_UNIT, $administration);
        $job = $service->createUnit('مدرب أول', OrgUnit::LEVEL_JOB, $unit);

        $this->assertSame($administration->id, $unit->parent_id);
        $this->assertTrue($job->isJobCard());

        $this->expectException(\InvalidArgumentException::class);
        $service->createUnit('وظيفة تحت إدارة', OrgUnit::LEVEL_JOB, $administration);
    }

    public function test_root_must_be_an_administration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(OrgStructureService::class)->createUnit('وحدة يتيمة', OrgUnit::LEVEL_UNIT);
    }

    public function test_tree_builds_the_visual_hierarchy(): void
    {
        $service = app(OrgStructureService::class);
        $administration = $service->createUnit('إدارة', OrgUnit::LEVEL_ADMINISTRATION);
        $unit = $service->createUnit('وحدة', OrgUnit::LEVEL_UNIT, $administration);
        $service->createUnit('وظيفة', OrgUnit::LEVEL_JOB, $unit);

        $tree = $service->tree();

        $this->assertCount(1, $tree);
        $this->assertCount(1, $tree->first()->children);
        $this->assertCount(1, $tree->first()->children->first()->children);
    }

    public function test_transfer_preserves_history_without_overwriting(): void
    {
        $service = app(OrgStructureService::class);
        $administration = $service->createUnit('إدارة', OrgUnit::LEVEL_ADMINISTRATION);
        $first = $service->createUnit('وحدة أولى', OrgUnit::LEVEL_UNIT, $administration);
        $second = $service->createUnit('وحدة ثانية', OrgUnit::LEVEL_UNIT, $administration);

        $employee = User::factory()->create();
        $service->transfer($employee, $first, null, 'تعيين أولي', $this->admin());
        $service->transfer($employee->fresh(), $second, null, 'إعادة توزيع', $this->admin());

        $history = $service->historyFor($employee->fresh());

        $this->assertCount(2, $history);
        $this->assertSame($first->id, $history->last()->to_org_unit_id);
        $this->assertSame($first->id, $history->first()->from_org_unit_id);
        $this->assertSame($second->id, $employee->fresh()->org_unit_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'structure.transfer']);
    }

    public function test_committee_links_to_meetings(): void
    {
        $chair = $this->admin();
        $committee = Committee::create(['name' => 'لجنة الجودة', 'chair_id' => $chair->id]);
        $committee->members()->attach($chair->id, ['role_label' => 'رئيس']);

        $meeting = Meeting::factory()->create();
        $meeting->forceFill(['committee_id' => $committee->id])->save();

        $this->assertSame(1, $committee->meetings()->count());
        $this->assertSame($committee->id, $meeting->fresh()->committee_id);
        $this->assertSame(1, $committee->members()->count());
    }

    public function test_structure_screen_creates_a_unit(): void
    {
        Livewire::actingAs($this->admin())->test(OrgTreeIndex::class)
            ->call('openUnitModal')
            ->set('unitName', 'إدارة الشراكات')
            ->set('unitLevel', OrgUnit::LEVEL_ADMINISTRATION)
            ->call('saveUnit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('org_units', ['name' => 'إدارة الشراكات', 'level' => 'إدارة']);
    }

    // ------------------------------------------------------------ 10-B1

    public function test_exceptional_grant_requires_a_reason(): void
    {
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        app(PermissionGrantService::class)->grantException($user, 'reports.view', '   ');
    }

    public function test_exceptional_grant_is_recorded_and_audited(): void
    {
        $user = User::factory()->create();
        $admin = $this->admin();

        $grant = app(PermissionGrantService::class)->grantException(
            $user, 'reports.view', 'تغطية إجازة زميل', $admin, now()->addMonth()->toDateString()
        );

        $this->assertTrue($user->fresh()->can('reports.view'));
        $this->assertTrue($grant->isActive());
        $this->assertDatabaseHas('audit_logs', ['action' => 'permissions.exceptional_granted']);
    }

    public function test_revoking_an_exception_removes_the_permission(): void
    {
        $user = User::factory()->create();
        $grant = app(PermissionGrantService::class)->grantException($user, 'reports.view', 'مؤقت');

        app(PermissionGrantService::class)->revokeException($grant, $this->admin());

        $this->assertFalse($user->fresh()->can('reports.view'));
        $this->assertFalse($grant->fresh()->isActive());
    }

    public function test_unknown_permission_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(PermissionGrantService::class)->grantException(User::factory()->create(), 'nope.nope', 'سبب');
    }

    public function test_role_change_writes_an_audit_log(): void
    {
        $role = Role::create(['name' => 'دور اختبار', 'guard_name' => 'web']);

        app(PermissionGrantService::class)->syncRolePermissions($role, ['reports.view'], $this->admin());

        $this->assertSame(['reports.view'], $role->fresh()->permissions()->pluck('name')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'permissions.role_synced']);
    }

    public function test_matrix_marks_role_and_exceptional_sources(): void
    {
        $role = Role::create(['name' => 'دور مصفوفة', 'guard_name' => 'web']);
        $role->syncPermissions(['dashboard.view']);
        $user = User::factory()->create(['name' => 'موظف المصفوفة']);
        $user->assignRole($role);
        app(PermissionGrantService::class)->grantException($user, 'reports.view', 'استثناء مؤقت');

        $row = app(PermissionGrantService::class)->matrix()
            ->firstWhere(fn (array $row) => $row['user']->id === $user->id);

        $this->assertSame('دور', $row['permissions']['dashboard.view']);
        $this->assertSame('استثناء', $row['permissions']['reports.view']);

        $csv = app(PermissionGrantService::class)->matrixCsv();
        $this->assertStringContainsString('موظف المصفوفة', $csv);
        $this->assertStringContainsString('استثناء', $csv);
    }

    public function test_grant_screen_toggles_and_saves(): void
    {
        $role = Role::create(['name' => 'دور الشاشة', 'guard_name' => 'web']);

        Livewire::actingAs($this->admin())->test(GrantsIndex::class)
            ->call('selectRole', $role->id)
            ->call('toggleSection', 'reports', true)
            ->call('saveRole')
            ->assertHasNoErrors();

        $this->assertContains('reports.view', $role->fresh()->permissions()->pluck('name')->all());
    }

    public function test_grant_screen_requires_a_reason_for_exceptions(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($this->admin())->test(GrantsIndex::class)
            ->set('grantUserId', $user->id)
            ->set('grantPermission', 'reports.view')
            ->set('grantReason', '')
            ->call('grantException')
            ->assertHasErrors(['grantReason']);

        $this->assertSame(0, ExceptionalGrant::count());
    }

    public function test_matrix_export_is_authorized(): void
    {
        $stranger = User::factory()->create(['must_change_password' => false]);

        $this->actingAs($stranger)->get('/settings/grants')->assertForbidden();
        $this->actingAs($this->admin())->get('/settings/grants')->assertOk();
    }

    // ------------------------------------------------------------ 11-B1

    public function test_every_remaining_setting_key_is_seeded_and_readable(): void
    {
        $this->seed(PlatformSettingsSeeder::class);

        foreach ([
            'general.platform_name', 'general.logo_path', 'general.timezone',
            'aging.task_stale_days', 'aging.project_stale_days',
            'notifications.partnership_stale_days', 'notifications.decision_stale_days',
            'links.default_expiry_days', 'links.max_active_per_partnership',
            'finance.tax_rate', 'finance.budget_alert_threshold', 'finance.tax.mode',
            'hr.evaluation_cycle', 'attendance.monthly_working_days',
            'maintenance.enabled', 'maintenance.message',
            'backup.last_run_at', 'backup.retention_days',
        ] as $key) {
            $this->assertDatabaseHas('platform_settings', ['key' => $key]);
        }

        $this->assertSame(22, (int) Setting::get('attendance.monthly_working_days'));
        $this->assertSame(7, (int) Setting::get('aging.task_stale_days'));
    }

    public function test_setting_change_logs_old_and_new_values(): void
    {
        $this->seed(PlatformSettingsSeeder::class);
        $admin = $this->admin();

        Setting::set('aging.task_stale_days', '10', $admin);

        $this->assertSame(10, (int) Setting::get('aging.task_stale_days'));
        $log = \App\Models\AuditLog::where('action', 'settings.updated')->latest('id')->firstOrFail();
        $this->assertSame('7', $log->metadata['old_value']);
        $this->assertSame('10', $log->metadata['new_value']);
    }

    public function test_maintenance_mode_blocks_non_admins_and_lets_admins_through(): void
    {
        $this->seed(PlatformSettingsSeeder::class);
        $admin = $this->admin();
        $employee = User::factory()->create(['must_change_password' => false]);
        $employee->givePermissionTo('dashboard.view');

        $this->actingAs($employee)->get('/dashboard')->assertOk();

        Setting::set('maintenance.enabled', true, $admin);

        $this->actingAs($employee)->get('/dashboard')->assertStatus(503);
        $this->actingAs($admin)->get('/settings')->assertOk();
    }

    public function test_manual_backup_records_its_run(): void
    {
        $this->seed(PlatformSettingsSeeder::class);

        $path = app(BackupService::class)->run($this->admin());

        Storage::disk('local')->assertExists($path);
        $this->assertNotNull(app(BackupService::class)->status()['last_run_at']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'backup.created']);
    }
}
