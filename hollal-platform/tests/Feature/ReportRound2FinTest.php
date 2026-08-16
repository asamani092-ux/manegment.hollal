<?php

namespace Tests\Feature;

use App\Livewire\Expenses\ExpensesIndex;
use App\Livewire\Finance\CustodiesIndex;
use App\Livewire\Finance\RevenuesIndex;
use App\Models\CompanyProfile;
use App\Models\Custody;
use App\Models\ExpenseCategory;
use App\Models\ExpenseRequest;
use App\Models\Revenue;
use App\Models\User;
use App\Services\CustodyService;
use App\Services\ExpenseApprovalService;
use App\Services\TaxInvoicePdfService;
use App\Services\TaxInvoiceService;
use App\Support\PdfArabic;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Report round 2 — Batch 2 Finance.
 */
class ReportRound2FinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    public function test_expense_reject_visible_on_all_tab_and_return_reopens_for_requester(): void
    {
        $category = ExpenseCategory::create(['name_ar' => 'تصنيف تجريبي', 'is_active' => true]);
        $requester = User::factory()->create(['must_change_password' => false]);
        $requester->givePermissionTo('finance.expenses.create');
        $approver = User::factory()->create(['must_change_password' => false]);
        $approver->assignRole('Super Admin');

        $expense = ExpenseRequest::create([
            'requester_id' => $requester->id,
            'type' => 'operational',
            'amount' => 100,
            'reason' => 'طلب للمراجعة',
            'payment_method' => 'transfer',
            'category_id' => $category->id,
            'status' => 'pending',
            'current_approval_stage' => ExpenseApprovalService::STAGE_EXECUTIVE,
            'approval_stages' => [ExpenseApprovalService::STAGE_EXECUTIVE, ExpenseApprovalService::STAGE_FINANCE],
        ]);

        Livewire::actingAs($approver)
            ->test(ExpensesIndex::class)
            ->set('activeTab', 'all')
            ->assertSee('رفض', false)
            ->call('openRejectModal', $expense->id)
            ->assertSet('rejectExpenseId', $expense->id)
            ->call('openReturnModal', $expense->id)
            ->set('returnReason', 'يرجى إرفاق فاتورة أوضح')
            ->call('confirmReturnExpense')
            ->assertHasNoErrors();

        $this->assertSame(ExpenseRequest::STATUS_RETURNED, $expense->fresh()->status);
        $this->assertTrue($requester->can('update', $expense->fresh()));

        Livewire::actingAs($requester)
            ->test(ExpensesIndex::class)
            ->call('openExpenseEdit', $expense->id)
            ->set('reason', 'طلب بعد التعديل')
            ->call('saveExpense', true)
            ->assertHasNoErrors();

        $this->assertSame('pending', $expense->fresh()->status);
    }

    public function test_super_admin_can_approve_executive_stage_without_executive_role_name(): void
    {
        $super = User::factory()->create(['must_change_password' => false]);
        $super->assignRole('Super Admin');
        $expense = ExpenseRequest::factory()->create([
            'status' => 'pending',
            'current_approval_stage' => ExpenseApprovalService::STAGE_EXECUTIVE,
            'approval_stages' => [ExpenseApprovalService::STAGE_EXECUTIVE, ExpenseApprovalService::STAGE_FINANCE],
        ]);

        $this->assertTrue(app(ExpenseApprovalService::class)->canApprove($super, $expense));
    }

    public function test_custody_disburse_requires_proof_file(): void
    {
        Storage::fake('local');
        $employee = User::factory()->create();
        $finance = User::factory()->create(['must_change_password' => false]);
        $finance->givePermissionTo('finance.custodies.disburse');
        $service = app(CustodyService::class);
        $custody = $service->request($employee, 500, 'عهدة إثبات', null, null, null, $employee);
        $service->approve($custody, User::factory()->create());

        Livewire::actingAs($finance)
            ->test(CustodiesIndex::class)
            ->call('openDisburse', $custody->id)
            ->call('disburseCustody')
            ->assertHasErrors(['disbursementProof']);

        Livewire::actingAs($finance)
            ->test(CustodiesIndex::class)
            ->call('openDisburse', $custody->id)
            ->set('disbursementProof', UploadedFile::fake()->create('proof.pdf', 20, 'application/pdf'))
            ->call('disburseCustody')
            ->assertHasNoErrors();

        $this->assertSame(Custody::STATUS_DISBURSED, $custody->fresh()->status);
        $this->assertNotEmpty($custody->fresh()->disbursement_proof_path);
    }

    public function test_revenue_evidence_download_route(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('revenues/test.pdf', '%PDF-1.4 demo');
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['finance.revenues.view', 'finance.revenues.manage']);

        $revenue = Revenue::create([
            'source_type' => Revenue::SOURCE_MANUAL,
            'amount' => 1200,
            'received_at' => now()->toDateString(),
            'status' => Revenue::STATUS_RECORDED,
            'external_document_path' => 'revenues/test.pdf',
        ]);

        Livewire::actingAs($user)
            ->test(RevenuesIndex::class)
            ->assertDontSee('إضافة للموازنة', false)
            ->assertSee('معاينة', false)
            ->assertSee('تحميل', false);

        $this->actingAs($user)
            ->get(route('revenues.files.download', $revenue).'?inline=1')
            ->assertOk()
            ->assertHeader('Content-Disposition', \App\Support\DownloadHeaders::contentDisposition('test.pdf', 'inline'));

        $this->actingAs($user)
            ->get(route('revenues.files.download', $revenue))
            ->assertOk()
            ->assertHeader('Content-Disposition', \App\Support\DownloadHeaders::contentDisposition('test.pdf', 'attachment'));
    }

    public function test_tax_invoice_pdf_contains_amiri_and_seller_vat(): void
    {
        CompanyProfile::current()->update([
            'name' => 'مؤسسة حلّل للاختبار',
            'tax_number' => '300000000000003',
            'commercial_register' => '1010999888',
            'address' => 'الرياض',
        ]);

        $invoice = app(TaxInvoiceService::class)->issue(
            items: [['description' => 'خدمة', 'quantity' => 1, 'unit_price' => 100]],
            buyer: ['name' => 'مشتري', 'vat_number' => '310011122233344'],
        );

        $pdf = app(TaxInvoicePdfService::class)->render($invoice);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame('amiri', PdfArabic::defaultFont());
        $this->assertSame('300000000000003', $invoice->seller_vat_number);
        $this->assertTrue(is_file(resource_path('fonts/Amiri-Regular.ttf')));
        $this->assertNotEmpty($invoice->qr_payload);
        $this->assertSame('مؤسسة حلّل للاختبار', $invoice->seller_name);
    }

    public function test_budget_addition_form_not_on_revenues_page(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['finance.revenues.view', 'finance.revenues.manage', 'finance.budgets.view']);

        Livewire::actingAs($user)
            ->test(RevenuesIndex::class)
            ->assertDontSee('إرسال للاعتماد', false)
            ->assertDontSee('requestBudgetAdd', false)
            ->assertSee('لوحة الموازنات', false);
    }
}
