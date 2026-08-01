<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\Organization;
use App\Models\Partnership;
use App\Models\Program;
use App\Models\Project;
use App\Models\User;
use App\Support\NavigationHelper;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pre-trial guard: every screen must render against the demo dataset, not just
 * against empty tables (an empty paginator hides broken item queries).
 */
class DemoDataSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('phone', '0500000000')->firstOrFail();
        $this->admin->forceFill(['must_change_password' => false])->save();
    }

    public function test_demo_seed_fills_every_screen_domain(): void
    {
        $expected = [
            'contracts' => 5,
            'payrolls' => 12,
            'periodic_evaluations' => 4,
            'responsibilities' => 5,
            'attendance_records' => 15,
            'leave_requests' => 4,
            'assets' => 6,
            'revenues' => 6,
            'tax_invoices' => 3,
            'organizations' => 4,
            'partnerships' => 4,
            'programs' => 4,
            'project_visits' => 5,
            'measurement_forms' => 2,
            'documents' => 10,
            'document_versions' => 5,
            'committees' => 3,
            'recurring_task_templates' => 3,
            'weekly_reports' => 3,
            'exceptional_grants' => 3,
        ];

        foreach ($expected as $table => $minimum) {
            $this->assertGreaterThanOrEqual(
                $minimum,
                \DB::table($table)->count(),
                "الجدول {$table} لم يُعبَّأ ببيانات التجربة"
            );
        }
    }

    public function test_every_navigation_screen_renders_with_demo_data(): void
    {
        $routes = collect(NavigationHelper::allItems())
            ->pluck('route')
            ->push('dashboard')
            ->unique();

        foreach ($routes as $route) {
            $this->actingAs($this->admin)
                ->get(route($route))
                ->assertOk("المسار {$route} لم يُفتح ببيانات التجربة");
        }
    }

    public function test_detail_screens_render_with_demo_data(): void
    {
        $targets = [
            'users.profile' => User::query()->orderBy('id')->firstOrFail(),
            'projects.show' => Project::query()->orderBy('id')->firstOrFail(),
            'projects.execution' => Project::query()->orderBy('id')->firstOrFail(),
            'programs.show' => Program::query()->orderBy('id')->firstOrFail(),
            'organizations.show' => Organization::query()->orderBy('id')->firstOrFail(),
            'partnerships.show' => Partnership::query()->orderBy('id')->firstOrFail(),
            'meetings.minutes' => Meeting::query()->orderBy('id')->firstOrFail(),
        ];

        foreach ($targets as $route => $model) {
            $this->actingAs($this->admin)
                ->get(route($route, $model))
                ->assertOk("صفحة التفاصيل {$route} لم تُفتح ببيانات التجربة");
        }
    }
}
