<?php

namespace Tests\Feature;

use App\Models\PayrollAdjustment;
use App\Models\User;
use App\Models\Violation;
use App\Services\PayrollAdjustmentService;
use App\Services\ViolationService;
use Database\Seeders\ReferenceListsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HrRebuildFollowOnTest extends TestCase
{
    use RefreshDatabase;

    public function test_violation_window_and_decision_gate_and_draft_seed(): void
    {
        $this->seed(ReferenceListsSeeder::class);
        $item = app(\App\Services\ReferenceListService::class)->createDraft('violations', 'follow-sample', 'مسودة للاختبار', [
            'category' => 'سلوك العامل',
            'origin' => 'company',
            'penalties' => [['type' => 'warning', 'value' => 0]],
        ], now()->toDateString());
        $this->assertSame('draft', $item->status);

        $employee = User::factory()->create();
        $published = app(\App\Services\ReferenceListService::class)->publish($item, null, 'تفعيل للاختبار');
        Violation::query()->create([
            'employee_id' => $employee->id,
            'reference_item_id' => $published->id,
            'status' => 'applied',
            'occurred_on' => '2026-01-01',
            'discovered_on' => '2026-01-01',
        ]);
        $service = app(ViolationService::class);
        $this->assertSame(2, $service->occurrenceIndex($employee->id, $published->id, Carbon::parse('2026-06-20')));
        $this->assertSame(1, $service->occurrenceIndex($employee->id, $published->id, Carbon::parse('2026-07-02')));

        $open = Violation::query()->create([
            'employee_id' => $employee->id,
            'reference_item_id' => $published->id,
            'status' => 'awaiting_statement',
            'occurred_on' => now()->toDateString(),
            'discovered_on' => now()->toDateString(),
            'statement_deadline_on' => now()->addDays(2)->toDateString(),
        ]);
        $this->expectException(\RuntimeException::class);
        $service->assertCanDecide($open);
    }

    public function test_payroll_run_blocked_while_proposed_lines_exist(): void
    {
        $employee = User::factory()->create();
        $line = PayrollAdjustment::query()->create([
            'employee_id' => $employee->id,
            'month' => '2026-08',
            'amount' => 10,
            'computed_amount' => 10,
            'status' => 'proposed',
        ]);
        $this->expectException(\RuntimeException::class);
        app(PayrollAdjustmentService::class)->assertNoProposed('2026-08');
        app(PayrollAdjustmentService::class)->modify($line, 8, 'تخفيض');
    }

    public function test_modify_records_reason(): void
    {
        $employee = User::factory()->create();
        $line = PayrollAdjustment::query()->create([
            'employee_id' => $employee->id,
            'month' => '2026-09',
            'amount' => 10,
            'computed_amount' => 10,
            'status' => 'proposed',
        ]);
        $updated = app(PayrollAdjustmentService::class)->modify($line, 7, 'مراجعة');
        $this->assertSame('modified', $updated->status);
        $this->assertSame('7.00', number_format((float) $updated->amount, 2, '.', ''));
        $this->assertSame('10.00', number_format((float) $updated->computed_amount, 2, '.', ''));
    }

    public function test_generate_posts_approved_adjustment_into_run(): void
    {
        $employee = User::factory()->create([
            'is_active' => true,
            'employment_status' => \App\Models\User::STATUS_ACTIVE,
        ]);
        $month = now()->format('Y-m');
        PayrollAdjustment::query()->create([
            'employee_id' => $employee->id,
            'month' => $month,
            'amount' => 40,
            'computed_amount' => 40,
            'status' => 'approved',
            'reason' => 'مكافأة',
        ]);

        $run = app(\App\Services\PayrollRunService::class)->generate($month);
        $item = $run->items()->where('employee_id', $employee->id)->first();
        $this->assertNotNull($item);
        $this->assertSame('earning', $item->variables[0]['kind'] ?? null);
        $this->assertSame('posted', PayrollAdjustment::query()->first()->status);
    }
}
