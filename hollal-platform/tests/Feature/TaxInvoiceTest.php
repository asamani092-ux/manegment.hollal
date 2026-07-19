<?php

namespace Tests\Feature;

use App\Livewire\Finance\TaxInvoicesIndex;
use App\Models\TaxInvoice;
use App\Models\TaxInvoiceNote;
use App\Models\User;
use App\Services\TaxInvoiceService;
use App\Support\Setting;
use App\Support\TlvQr;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 04-B7 — tax invoicing Phase A: unbroken sequence, derived totals, TLV QR,
 * credit/debit notes, issue-from-payment, internal/external mode.
 */
class TaxInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TaxInvoiceService
    {
        return app(TaxInvoiceService::class);
    }

    /** @param list<array<string, mixed>> $items */
    private function issue(array $items = [], ?User $issuer = null): TaxInvoice
    {
        return $this->service()->issue(
            items: $items === [] ? [['description' => 'خدمة', 'quantity' => 1, 'unit_price' => 1000]] : $items,
            buyer: ['name' => 'شركة المشتري', 'vat_number' => '310000000000003'],
            issuer: $issuer,
        );
    }

    public function test_sequence_is_unbroken_across_issues(): void
    {
        $numbers = [];

        for ($i = 0; $i < 12; $i++) {
            $numbers[] = $this->issue()->sequence;
        }

        $this->assertSame(range(1, 12), $numbers);
        $this->assertSame(12, TaxInvoice::count());
    }

    public function test_sequence_survives_a_failed_issue_without_reuse(): void
    {
        $first = $this->issue();

        try {
            $this->service()->issue(items: [], buyer: ['name' => 'x']);
            $this->fail('expected an exception for an invoice without items');
        } catch (\InvalidArgumentException) {
            // expected
        }

        $second = $this->issue();

        $this->assertSame($first->sequence + 1, $second->sequence);
        $this->assertNotSame($first->number, $second->number);
    }

    public function test_totals_are_derived_from_line_items(): void
    {
        $invoice = $this->issue([
            ['description' => 'بند أ', 'quantity' => 2, 'unit_price' => 500],
            ['description' => 'بند ب', 'quantity' => 1, 'unit_price' => 250],
        ]);

        $this->assertSame('1250.00', (string) $invoice->subtotal);
        $this->assertSame('187.50', (string) $invoice->vat_total);
        $this->assertSame('1437.50', (string) $invoice->total);
        $this->assertTrue($invoice->totalsMatchItems());
        $this->assertCount(2, $invoice->items);
    }

    public function test_qr_payload_contains_required_tlv_tags(): void
    {
        $invoice = $this->issue();
        $decoded = TlvQr::decode((string) $invoice->qr_payload);

        $this->assertTrue(TlvQr::hasRequiredTags($decoded));
        $this->assertSame($invoice->seller_name, $decoded[TlvQr::TAG_SELLER_NAME]);
        $this->assertSame($invoice->seller_vat_number, $decoded[TlvQr::TAG_SELLER_VAT]);
        $this->assertSame('1150.00', $decoded[TlvQr::TAG_TOTAL]);
        $this->assertSame('150.00', $decoded[TlvQr::TAG_VAT_TOTAL]);
    }

    public function test_credit_note_links_to_the_original_invoice(): void
    {
        $invoice = $this->issue();

        $note = $this->service()->issueNote($invoice, TaxInvoiceNote::TYPE_CREDIT, 200, 'خصم متفق عليه');

        $this->assertSame($invoice->id, $note->tax_invoice_id);
        $this->assertSame(TaxInvoiceNote::TYPE_CREDIT, $note->note_type);
        $this->assertSame('200.00', (string) $note->subtotal);
        $this->assertSame('30.00', (string) $note->vat_total);
        $this->assertSame('230.00', (string) $note->total);
        $this->assertStringStartsWith('CRN-', $note->number);
        $this->assertTrue(TlvQr::hasRequiredTags(TlvQr::decode((string) $note->qr_payload)));
    }

    public function test_debit_note_uses_its_own_unbroken_sequence(): void
    {
        $invoice = $this->issue();

        $first = $this->service()->issueNote($invoice, TaxInvoiceNote::TYPE_DEBIT, 100, 'فرق سعر');
        $second = $this->service()->issueNote($invoice, TaxInvoiceNote::TYPE_CREDIT, 50, 'تصحيح');

        $this->assertSame(1, $first->sequence);
        $this->assertSame(2, $second->sequence);
        $this->assertStringStartsWith('DBN-', $first->number);
    }

    public function test_note_type_and_amount_are_validated(): void
    {
        $invoice = $this->issue();

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->issueNote($invoice, 'غير معروف', 10, 'سبب');
    }

    public function test_issue_from_payment_is_idempotent(): void
    {
        $first = $this->service()->issueFromPayment(paymentId: 77, amount: 4000, buyerName: 'جهة الشراكة');
        $second = $this->service()->issueFromPayment(paymentId: 77, amount: 4000, buyerName: 'جهة الشراكة');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, TaxInvoice::where('source_id', 77)->count());
        $this->assertSame(TaxInvoice::SOURCE_PAYMENT, $first->source_type);
    }

    public function test_invoicing_mode_comes_from_platform_settings(): void
    {
        $this->assertSame(TaxInvoice::MODE_INTERNAL, $this->service()->mode());

        Setting::set('finance.tax.mode', TaxInvoice::MODE_EXTERNAL);

        $this->assertSame(TaxInvoice::MODE_EXTERNAL, $this->service()->mode());
        $this->assertSame(TaxInvoice::MODE_EXTERNAL, $this->issue()->mode);
    }

    public function test_screen_issues_an_invoice_with_derived_totals(): void
    {
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();
        $user->givePermissionTo(['finance.tax_invoices.view', 'finance.tax_invoices.issue']);

        Livewire::actingAs($user)->test(TaxInvoicesIndex::class)
            ->call('openIssueModal')
            ->set('buyerName', 'عميل تجريبي')
            ->set('lines', [['description' => 'استشارة', 'quantity' => '3', 'unit_price' => '100']])
            ->call('issue')
            ->assertHasNoErrors();

        $invoice = TaxInvoice::firstOrFail();
        $this->assertSame('300.00', (string) $invoice->subtotal);
        $this->assertSame('345.00', (string) $invoice->total);
        $this->assertSame($user->id, $invoice->issued_by);
    }

    public function test_screen_requires_the_issue_permission(): void
    {
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();
        $user->givePermissionTo('finance.tax_invoices.view');

        Livewire::actingAs($user)->test(TaxInvoicesIndex::class)
            ->call('openIssueModal')
            ->assertForbidden();
    }

    public function test_index_route_is_protected(): void
    {
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create(['must_change_password' => false]);

        $this->actingAs($user)->get('/tax-invoices')->assertForbidden();
    }

    public function test_invoice_pdf_downloads(): void
    {
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo('finance.tax_invoices.view');
        $invoice = $this->issue();

        $this->actingAs($user)
            ->get(route('tax-invoices.pdf', $invoice->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }
}
