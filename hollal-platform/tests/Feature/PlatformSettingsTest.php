<?php

namespace Tests\Feature;

use App\Livewire\Settings\SettingsIndex;
use App\Models\ExpenseCategory;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\Setting;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 00-B6 — platform settings helper (typed reads, cached, audited) and
 * category trees.
 */
class PlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformSettingsSeeder::class);
    }

    public function test_setting_get_returns_typed_values(): void
    {
        $this->assertSame('SAR', Setting::get('finance.currency'));
        $this->assertSame(48, Setting::get('notifications.task_escalation_hours'));
        $this->assertTrue(Setting::get('finance.skip_missing_dept_manager'));
        $this->assertSame([90, 60, 30], Setting::get('notifications.contract_expiry_days'));
        $this->assertSame('fallback', Setting::get('does.not.exist', 'fallback'));
    }

    public function test_set_logs_old_and_new_value_and_busts_cache(): void
    {
        $this->assertSame('SAR', Setting::get('finance.currency')); // primes cache

        Setting::set('finance.currency', 'USD');

        // Cache busted — fresh read returns the new value.
        $this->assertSame('USD', Setting::get('finance.currency'));

        $setting = PlatformSetting::where('key', 'finance.currency')->first();
        $this->assertSame('SAR', $setting->old_value);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'settings.updated',
            'target_type' => PlatformSetting::class,
            'target_id' => $setting->id,
        ]);
    }

    public function test_settings_screen_persists_changes(): void
    {
        $admin = User::factory()->create(['must_change_password' => false]);
        $admin->givePermissionTo('settings.manage');

        Livewire::actingAs($admin)
            ->test(SettingsIndex::class)
            ->set('values.finance__currency', 'EUR')
            ->call('save')
            ->assertDispatched('toast', type: 'success');

        $this->assertSame('EUR', Setting::get('finance.currency'));
    }

    public function test_settings_screen_requires_permission(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);

        Livewire::actingAs($user)
            ->test(SettingsIndex::class)
            ->assertForbidden();
    }

    public function test_category_soft_delete_is_safe(): void
    {
        $category = ExpenseCategory::create(['name_ar' => 'فئة اختبار']);

        $category->delete();

        $this->assertSoftDeleted($category);
        $this->assertSame(0, ExpenseCategory::where('name_ar', 'فئة اختبار')->count());
        $this->assertSame(1, ExpenseCategory::withTrashed()->where('name_ar', 'فئة اختبار')->count());
    }

    public function test_disabled_category_excluded_from_active_scope(): void
    {
        ExpenseCategory::create(['name_ar' => 'مفعّلة', 'is_active' => true]);
        ExpenseCategory::create(['name_ar' => 'معطّلة', 'is_active' => false]);

        $active = ExpenseCategory::active()->pluck('name_ar');

        $this->assertTrue($active->contains('مفعّلة'));
        $this->assertFalse($active->contains('معطّلة'));
    }
}
