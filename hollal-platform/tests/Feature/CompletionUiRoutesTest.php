<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\NavigationHelper;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompletionUiRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    public function test_grouped_navigation_exposes_glossary_labels(): void
    {
        $labels = collect(config('navigation.groups'))->pluck('label')->all();

        $this->assertContains('الموارد البشرية', $labels);
        $this->assertContains('الشراكات', $labels);
        $this->assertContains('إعدادات المنصة', $labels);
        $itemLabels = collect(NavigationHelper::allItems())->pluck('label')->all();
        $this->assertNotContains('الفريق', $itemLabels);
        $this->assertContains('دليل العاملين', $itemLabels);
    }

    public function test_super_admin_opens_new_completion_screens(): void
    {
        $admin = User::factory()->create(['must_change_password' => false, 'attendance_enabled' => true]);
        $admin->assignRole('Super Admin');

        foreach ([
            'attendance.index',
            'evaluations.index',
            'responsibilities.index',
            'hr-lifecycle.index',
            'visits.index',
            'measurement.index',
            'structure.jobs',
            'structure.committees',
            'documents.versions',
            'meetings.archive',
        ] as $route) {
            $this->actingAs($admin)
                ->get(route($route))
                ->assertOk();
        }
    }

    public function test_sidebar_shows_hr_group_label(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->assignRole('General Manager');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('الموارد البشرية', false)
            ->assertDontSee('>الفريق<', false);
    }
}
