<?php

namespace Tests\Feature;

use App\Livewire\Departments\DepartmentsIndex;
use App\Livewire\Settings\GrantsIndex;
use App\Livewire\Settings\MailSettingsIndex;
use App\Livewire\Structure\OrgTreeIndex;
use App\Models\Department;
use App\Models\MailSetting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Report round 1 — STRUCT / ROLE / SET / MOB.
 */
class ReportRound1StructSetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_department_saves_owner(): void
    {
        $admin = User::factory()->create(['must_change_password' => false, 'is_active' => true]);
        $admin->givePermissionTo([
            'structure.departments.view',
            'structure.departments.create',
            'structure.departments.update',
        ]);
        $owner = User::factory()->create(['is_active' => true, 'name' => 'مسؤول القسم']);

        Livewire::actingAs($admin)
            ->test(DepartmentsIndex::class)
            ->call('openCreate')
            ->set('name', 'إدارة التشغيل')
            ->set('ownerUserId', $owner->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('departments', [
            'name' => 'إدارة التشغيل',
            'owner_user_id' => $owner->id,
        ]);
    }

    public function test_org_tree_has_jobs_and_committees_tabs(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['structure.departments.view']);

        Livewire::actingAs($user)
            ->test(OrgTreeIndex::class)
            ->assertSee('الوظائف')
            ->assertSee('اللجان')
            ->set('tab', 'jobs')
            ->assertSee('لا توجد وظائف');
    }

    public function test_grants_uses_searchable_role_dropdown(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $admin = User::factory()->create(['must_change_password' => false]);
        $admin->givePermissionTo(['roles.view', 'roles.update']);

        Livewire::actingAs($admin)
            ->test(GrantsIndex::class)
            ->call('setTab', 'perms')
            ->assertSee('بحث عن دور')
            ->set('roleQuery', 'Finance')
            ->assertViewHas('roles', function ($roles) {
                $names = $roles->pluck('name');

                return $names->contains('Finance') && ! $names->contains('Employee');
            });
    }

    public function test_smtp_fields_save_without_applying_live_mailer(): void
    {
        $admin = User::factory()->create(['must_change_password' => false]);
        $admin->givePermissionTo('settings.notifications.manage');

        Livewire::actingAs($admin)
            ->test(MailSettingsIndex::class)
            ->set('host', 'smtp.example.test')
            ->set('port', 587)
            ->set('encryption', 'tls')
            ->set('from_address', 'noreply@example.test')
            ->set('from_name', 'حلّل')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast', type: 'success');

        $this->assertDatabaseHas('mail_settings', [
            'host' => 'smtp.example.test',
            'from_address' => 'noreply@example.test',
        ]);
        $this->assertNotSame('smtp.example.test', config('mail.mailers.smtp.host'));
    }

    public function test_smtp_test_send_is_blocked_until_live_flag(): void
    {
        $admin = User::factory()->create(['must_change_password' => false]);
        $admin->givePermissionTo('settings.notifications.manage');

        $this->assertFalse(MailSetting::liveSendingEnabled());

        Livewire::actingAs($admin)
            ->test(MailSettingsIndex::class)
            ->assertSee('تُحفظ الحقول فقط')
            ->call('sendTest')
            ->assertDispatched('toast', type: 'error');
    }
}
