<?php

namespace Tests\Feature;

use App\Livewire\Settings\GrantsIndex;
use App\Livewire\Settings\MailSettingsIndex;
use App\Livewire\Structure\OrgTreeIndex;
use App\Models\MailSetting;
use App\Models\OrgUnit;
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

    public function test_org_administration_can_be_created_with_manager(): void
    {
        $admin = User::factory()->create(['must_change_password' => false, 'is_active' => true]);
        $admin->givePermissionTo([
            'structure.view',
            'structure.manage',
        ]);
        $manager = User::factory()->create(['is_active' => true, 'name' => 'مسؤول القسم']);

        Livewire::actingAs($admin)
            ->test(OrgTreeIndex::class)
            ->call('openUnitModal')
            ->set('unitName', 'إدارة التشغيل')
            ->set('unitLevel', OrgUnit::LEVEL_ADMINISTRATION)
            ->call('saveUnit')
            ->assertHasNoErrors();

        $unit = OrgUnit::query()->where('name', 'إدارة التشغيل')->first();
        $this->assertNotNull($unit);
        $this->assertSame(OrgUnit::LEVEL_ADMINISTRATION, $unit->level);

        $unit->update(['manager_id' => $manager->id]);

        $this->assertDatabaseHas('org_units', [
            'name' => 'إدارة التشغيل',
            'level' => 'إدارة',
            'manager_id' => $manager->id,
        ]);
    }

    public function test_org_tree_has_jobs_and_committees_tabs(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['structure.view']);

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
