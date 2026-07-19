<?php

namespace Tests\Feature;

use App\Livewire\Finance\FinancialDocumentsIndex;
use App\Models\CustodySettlementItem;
use App\Models\ExpenseRequest;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Revenue;
use App\Models\User;
use App\Services\FinancialDocumentsService;
use App\Services\RevenueService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 04-B4 — idempotent auto-revenue + read-only financial documents index.
 */
class RevenueAndDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_revenue_is_created_exactly_once_per_payment(): void
    {
        $service = app(RevenueService::class);
        $finance = User::factory()->create();

        $first = $service->recordFromPartnershipPayment(paymentId: 55, amount: 10000, categoryId: null, confirmedBy: $finance->id);
        $second = $service->recordFromPartnershipPayment(paymentId: 55, amount: 10000, categoryId: null, confirmedBy: $finance->id);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Revenue::where('source_id', 55)->count());
        $this->assertSame(Revenue::STATUS_CONFIRMED, $first->status);
    }

    public function test_index_aggregates_all_document_types(): void
    {
        ExpenseRequest::create([
            'requester_id' => User::factory()->create()->id,
            'type' => 'operational', 'amount' => 100, 'reason' => 'x',
            'payment_method' => 'transfer', 'status' => 'draft',
            'official_document_path' => 'expenses/official/a.pdf',
        ]);
        Revenue::create(['source_type' => 'يدوي', 'amount' => 500, 'external_document_path' => 'revenues/b.pdf', 'status' => 'مسجل']);
        $run = PayrollRun::create(['month' => '2026-07', 'status' => 'منفذ']);
        $item = new PayrollRunItem(['employee_id' => User::factory()->create()->id, 'base' => 1, 'net' => 1, 'proof_file' => 'payroll/p.pdf']);
        $item->payroll_run_id = $run->id;
        $item->save();
        CustodySettlementItem::create(['custody_id' => \App\Models\Custody::create([
            'employee_id' => User::factory()->create()->id, 'amount' => 1, 'purpose' => 'x', 'status' => 'تسوية',
        ])->id, 'description' => 'y', 'amount' => 1, 'invoice_file' => 'custody/c.pdf']);

        $types = app(FinancialDocumentsService::class)->all()->pluck('type')->unique();

        $this->assertTrue($types->contains('expense_invoice'));
        $this->assertTrue($types->contains('revenue_document'));
        $this->assertTrue($types->contains('payroll_proof'));
        $this->assertTrue($types->contains('custody_invoice'));
    }

    public function test_index_screen_is_read_only(): void
    {
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();
        $user->givePermissionTo('finance.revenues.view');

        Livewire::actingAs($user)->test(FinancialDocumentsIndex::class)->assertOk();

        // No upload/save affordance on the read-only index.
        $this->assertFalse(method_exists(FinancialDocumentsIndex::class, 'save'));
        $this->assertFalse(method_exists(FinancialDocumentsIndex::class, 'upload'));
    }
}
