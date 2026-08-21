<?php

namespace Tests\Feature;

use App\Livewire\Settings\SettingsIndex;
use App\Livewire\Structure\OrgTreeIndex;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** DOC/REP/STR/SET polish checks. */
class CoreToolsPolishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    public function test_attendance_settings_have_arabic_help(): void
    {
        $this->assertStringContainsString('دورة', SettingsIndex::helpFor('attendance.cycle_start_day'));
        $this->assertStringContainsString('باركود', SettingsIndex::helpFor('attendance.site_barcode_token'));
    }

    public function test_audit_labels_cover_accounting_actions(): void
    {
        $this->assertSame('ترحيل قيد يومي', AuditLog::labelFor('journal.posted'));
        $this->assertSame('إنشاء حساب في الدليل', AuditLog::labelFor('chart_of_accounts.created'));
    }

    public function test_org_tree_accepts_tab_query(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->assignRole('General Manager');

        Livewire::actingAs($user)
            ->withQueryParams(['tab' => 'jobs'])
            ->test(OrgTreeIndex::class)
            ->assertSet('tab', 'jobs');
    }
}
