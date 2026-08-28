<?php

namespace Database\Seeders;

use App\Models\Custody;
use App\Models\ExpenseCategory;
use App\Models\ExpenseRequest;
use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\OrgUnit;
use App\Models\PayrollRun;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Operational demo data for UAT — populates «يحتاج تدخلك» and key flows.
 * Time: O(n) inserts | Space: O(n).
 */
class DemoTrialSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(OnboardingSeeder::class);

        $executive = User::where('phone', '0502222222')->first();
        $finance = User::where('phone', '0504444444')->first();
        $employee = User::where('phone', '0505555555')->first();
        $manager = User::where('phone', '0501111111')->first();

        if (! $executive || ! $finance || ! $employee) {
            return;
        }

        Task::factory()->create([
            'title' => 'مهمة متأخرة — تجربة',
            'assigned_to' => $employee->id,
            'assigned_by' => $executive->id,
            'due_date' => now()->subDays(3),
            'status' => 'overdue',
        ]);

        $category = ExpenseCategory::query()->first();
        if ($category) {
            ExpenseRequest::factory()->pending()->create([
                'requester_id' => $employee->id,
                'type' => 'operational',
                'amount' => 750,
                'reason' => 'طلب صرف تجريبي بانتظار الموافقة',
                'category_id' => $category->id,
            ]);
        }

        $meeting = Meeting::factory()->create([
            'title' => 'اجتماع تجريبي — محضر بانتظار الاعتماد',
            'chair_id' => $executive->id,
            'secretary_id' => $executive->id,
            'approval_status' => Meeting::APPROVAL_DRAFT,
            'scheduled_at' => now()->subDay(),
        ]);

        MeetingItem::factory()->create([
            'meeting_id' => $meeting->id,
            'topic' => 'قرار تجريبي متأخر',
            'decision' => 'متابعة التنفيذ',
            'responsible_id' => $employee->id,
            'due_date' => now()->subDays(35),
            'status' => 'open',
        ]);

        Custody::create([
            'employee_id' => $employee->id,
            'amount' => 500,
            'purpose' => 'عهدة تجريبية بانتظار الاعتماد',
            'requested_by' => $employee->id,
            'status' => Custody::STATUS_REQUESTED,
        ]);

        PayrollRun::create([
            'month' => now()->format('Y-m'),
            'status' => PayrollRun::STATUS_SUBMITTED,
            'submitted_by' => $manager?->id,
            'submitted_at' => now(),
        ]);

        // Attendance actions abort unless the flag is on; UAT needs the button live.
        User::whereIn('phone', ['0500000000', '0501111111', '0502222222', '0505555555'])
            ->update(['attendance_enabled' => true]);

        $this->call([
            DemoHrSeeder::class,
            DemoFinanceSeeder::class,
            DemoPartnershipsProjectsSeeder::class,
            DemoDocsStructureSeeder::class,
            DemoOpsSeeder::class,
        ]);

        $this->attachDemoUsersToJobCards();
    }

    /** Org tree shows a member count only when users point at a وظيفة node. */
    protected function attachDemoUsersToJobCards(): void
    {
        $jobCards = OrgUnit::where('level', OrgUnit::LEVEL_JOB)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($jobCards === []) {
            return;
        }

        $users = User::whereNull('org_unit_id')->orderBy('id')->get(['id']);

        foreach ($users as $index => $user) {
            $user->forceFill(['org_unit_id' => $jobCards[$index % count($jobCards)]])->save();
        }
    }
}
