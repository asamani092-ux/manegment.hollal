<?php

namespace Tests\Feature;

use App\Models\Custody;
use App\Models\User;
use App\Services\CustodyService;
use App\Services\OffboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 04-B3 — custody cycle, settlement reconciliation, offboarding hold.
 */
class CustodyTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_custody_cycle_closes_when_reconciled(): void
    {
        $employee = User::factory()->create();
        $executive = User::factory()->create();
        $service = app(CustodyService::class);

        $custody = $service->request($employee, 1000, 'شراء مستلزمات', null, null, null, $employee);
        $service->approve($custody, $executive);
        $service->disburse($custody);

        $this->assertSame('1000.00', $custody->fresh()->disbursed_amount);

        $service->addSettlementItem($custody, 'قرطاسية', 800);
        $service->close($custody, returnedAmount: 200);

        $this->assertSame(Custody::STATUS_CLOSED, $custody->fresh()->status);
        $this->assertSame('200.00', $custody->fresh()->returned_amount);
    }

    public function test_close_rejects_reconciliation_mismatch(): void
    {
        $employee = User::factory()->create();
        $service = app(CustodyService::class);

        $custody = $service->request($employee, 1000, 'عهدة', null, null, null, $employee);
        $service->approve($custody, User::factory()->create());
        $service->disburse($custody);
        $service->addSettlementItem($custody, 'بند', 800);

        $this->expectException(\RuntimeException::class);
        $service->close($custody, returnedAmount: 0); // 800 + 0 != 1000
    }

    public function test_offboarding_blocked_by_open_custody(): void
    {
        $employee = User::factory()->create();
        $service = app(CustodyService::class);
        $custody = $service->request($employee, 500, 'عهدة مفتوحة', null, null, null, $employee);
        $service->approve($custody, User::factory()->create());
        $service->disburse($custody);

        $holds = app(OffboardingService::class)->holds($employee);
        $this->assertNotEmpty($holds);

        $this->expectException(\RuntimeException::class);
        app(OffboardingService::class)->offboard($employee, User::factory()->create());
    }

    public function test_offboarding_allowed_after_custody_closed(): void
    {
        $employee = User::factory()->create();
        $service = app(CustodyService::class);
        $custody = $service->request($employee, 500, 'عهدة', null, null, null, $employee);
        $service->approve($custody, User::factory()->create());
        $service->disburse($custody);
        $service->addSettlementItem($custody, 'بند', 500);
        $service->close($custody);

        app(OffboardingService::class)->offboard($employee, User::factory()->create());

        $this->assertSame('منتهية_علاقته', $employee->fresh()->employment_status);
    }

    public function test_cannot_settle_before_disbursement(): void
    {
        $employee = User::factory()->create();
        $service = app(CustodyService::class);
        $custody = $service->request($employee, 300, 'عهدة', null, null, null, $employee);

        $this->expectException(\RuntimeException::class);
        $service->addSettlementItem($custody, 'بند', 100);
    }
}
