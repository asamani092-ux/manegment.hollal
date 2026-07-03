<?php

namespace Tests\Feature;

use App\Console\Commands\GenerateWeeklyReport;
use App\Livewire\Reports\ReportsIndex;
use App\Models\ExpenseRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WeeklyReport;
use App\Notifications\WeeklyReportGenerated;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class WeeklyReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_weekly_report_command_generates_record_with_expected_sections(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-03 16:00:00'));

        $employee = User::factory()->create(['phone' => '0501111101']);
        $project = Project::factory()->create(['status' => 'active']);

        Task::factory()->create([
            'title' => 'مهمة منجزة',
            'assigned_by' => $employee->id,
            'assigned_to' => $employee->id,
            'project_id' => $project->id,
            'status' => 'completed',
            'updated_at' => now()->subDay(),
        ]);

        Task::factory()->create([
            'title' => 'مهمة متأخرة',
            'assigned_by' => $employee->id,
            'assigned_to' => $employee->id,
            'due_date' => now()->subDays(2),
            'status' => 'overdue',
        ]);

        ExpenseRequest::factory()->approved()->create([
            'amount' => 1500,
            'updated_at' => now()->subDay(),
        ]);

        Artisan::call(GenerateWeeklyReport::class);

        $report = WeeklyReport::first();
        $this->assertNotNull($report);
        $this->assertNotEmpty($report->done);
        $this->assertNotEmpty($report->overdue);
        $this->assertNotEmpty($report->project_status);
        $this->assertEquals('1500.00', (string) $report->week_spend);
        $this->assertIsArray($report->open_decisions);
        $this->assertNotNull($report->week_start);
        $this->assertNotNull($report->week_end);
        $this->assertNotNull($report->generated_at);

        Carbon::setTestNow();
    }

    public function test_user_without_reports_view_cannot_access_reports_page(): void
    {
        $user = User::factory()->create([
            'phone' => '0501111102',
            'must_change_password' => false,
        ]);

        $this->actingAs($user)->get('/reports')->assertForbidden();
    }

    public function test_user_with_reports_view_can_access_reports_page(): void
    {
        $user = User::factory()->create([
            'phone' => '0501111103',
            'must_change_password' => false,
        ]);
        $user->givePermissionTo('reports.view');

        Livewire::actingAs($user)
            ->test(ReportsIndex::class)
            ->assertSuccessful();
    }

    public function test_weekly_report_notifies_managers(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-03 16:00:00'));

        $manager = User::factory()->create(['phone' => '0501111104']);
        User::factory()->create(['phone' => '0501111105', 'manager_id' => $manager->id]);

        Artisan::call(GenerateWeeklyReport::class);

        Notification::assertSentTo($manager, WeeklyReportGenerated::class);

        Carbon::setTestNow();
    }
}
