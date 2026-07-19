<?php

namespace Tests\Feature;

use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\User;
use App\Notifications\PayrollExecuted;
use App\Services\PayrollRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * 04-B2 — finance-side payroll execution: approval gate, per-row execution,
 * all-executed trigger, amounts stay read-only.
 */
class PayrollExecutionTest extends TestCase
{
    use RefreshDatabase;

    private function submittedRun(User $hr, int $items = 1): PayrollRun
    {
        $run = PayrollRun::create([
            'month' => '2026-07',
            'status' => PayrollRun::STATUS_SUBMITTED,
            'submitted_by' => $hr->id,
            'submitted_at' => now(),
        ]);

        for ($i = 0; $i < $items; $i++) {
            $item = new PayrollRunItem([
                'employee_id' => User::factory()->create()->id,
                'base' => 5000,
                'net' => 5000,
                'gross' => 5000,
            ]);
            $item->payroll_run_id = $run->id;
            $item->save();
        }

        return $run;
    }

    public function test_execution_blocked_before_finance_approval(): void
    {
        $run = $this->submittedRun(User::factory()->create());
        $item = $run->items()->first();

        $this->expectException(\InvalidArgumentException::class);
        app(PayrollRunService::class)->executeItem($item, 'TRX-1', '2026-07-28');
    }

    public function test_finance_approve_requires_submitted_status(): void
    {
        $run = PayrollRun::create(['month' => '2026-08', 'status' => PayrollRun::STATUS_DRAFT]);

        $this->expectException(\InvalidArgumentException::class);
        app(PayrollRunService::class)->financeApprove($run, User::factory()->create());
    }

    public function test_all_rows_executed_marks_run_executed_and_notifies_hr(): void
    {
        Notification::fake();

        $hr = User::factory()->create();
        $run = $this->submittedRun($hr, items: 2);
        $service = app(PayrollRunService::class);

        $service->financeApprove($run, User::factory()->create());

        $items = $run->items()->get();
        $service->executeItem($items[0], 'TRX-1', '2026-07-28');
        $this->assertSame(PayrollRun::STATUS_SUBMITTED, $run->fresh()->status); // not all yet

        $service->executeItem($items[1], 'TRX-2', '2026-07-28');

        $this->assertSame(PayrollRun::STATUS_EXECUTED, $run->fresh()->status);
        Notification::assertSentTo($hr, PayrollExecuted::class);
    }

    public function test_execution_does_not_change_amounts(): void
    {
        $hr = User::factory()->create();
        $run = $this->submittedRun($hr);
        $item = $run->items()->first();
        $originalNet = $item->net;

        app(PayrollRunService::class)->financeApprove($run, User::factory()->create());
        app(PayrollRunService::class)->executeItem($item, 'TRX-9', '2026-07-28');

        $this->assertSame($originalNet, $item->fresh()->net);
        $this->assertNotNull($item->fresh()->executed_at);
    }
}
