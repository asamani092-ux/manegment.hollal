<?php

namespace Tests\Feature;

use App\Livewire\Finance\TaxInvoicesIndex;
use App\Models\CompanyProfile;
use App\Models\TaxInvoice;
use App\Models\TaxInvoiceNote;
use App\Models\TaxInvoiceTemplate;
use App\Models\User;
use App\Services\TaxInvoicePdfService;
use App\Services\TaxInvoiceService;
use App\Support\Setting;
use App\Support\TlvQr;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 04-B7 / Wave D-deep — tax invoicing Phase A: unbroken sequence, derived
 * totals, TLV QR, credit/debit notes, issue-from-payment, internal/external
 * mode, uploadable per-type letterhead templates.
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
            ->set('buyerVatNumber', '300000000000003')
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

    public function test_screen_can_issue_a_simplified_invoice(): void
    {
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();
        $user->givePermissionTo(['finance.tax_invoices.view', 'finance.tax_invoices.issue']);

        Livewire::actingAs($user)->test(TaxInvoicesIndex::class)
            ->call('openIssueModal')
            ->set('buyerName', 'عميل نقاط بيع')
            ->set('invoiceType', TaxInvoice::TYPE_SIMPLIFIED)
            ->set('lines', [['description' => 'خدمة', 'quantity' => '1', 'unit_price' => '100']])
            ->call('issue')
            ->assertHasNoErrors();

        $invoice = TaxInvoice::firstOrFail();
        $this->assertSame(TaxInvoice::TYPE_SIMPLIFIED, $invoice->invoice_type);
    }

    public function test_templates_default_to_no_letterhead(): void
    {
        $templates = $this->service()->templates();

        $this->assertCount(2, $templates);
        $this->assertTrue($templates->every(fn (TaxInvoiceTemplate $t) => $t->letterhead_path === null));
        $this->assertEqualsCanonicalizing(
            [TaxInvoice::TYPE_STANDARD, TaxInvoice::TYPE_SIMPLIFIED],
            $templates->pluck('type')->all(),
        );
    }

    public function test_uploading_a_letterhead_persists_the_path_per_type(): void
    {
        $template = $this->service()->saveTemplateLetterhead(TaxInvoice::TYPE_STANDARD, 'tax-invoice-templates/full.png');

        $this->assertSame('tax-invoice-templates/full.png', $template->letterhead_path);
        $this->assertDatabaseHas('tax_invoice_templates', [
            'type' => TaxInvoice::TYPE_STANDARD,
            'letterhead_path' => 'tax-invoice-templates/full.png',
        ]);

        // The simplified template is untouched by the full-type upload.
        $simplified = TaxInvoiceTemplate::forType(TaxInvoice::TYPE_SIMPLIFIED);
        $this->assertNull($simplified);
    }

    public function test_removing_a_letterhead_clears_the_path_only_for_that_type(): void
    {
        $this->service()->saveTemplateLetterhead(TaxInvoice::TYPE_STANDARD, 'a.png');
        $this->service()->saveTemplateLetterhead(TaxInvoice::TYPE_SIMPLIFIED, 'b.png');

        $this->service()->removeTemplateLetterhead(TaxInvoice::TYPE_STANDARD);

        $this->assertNull(TaxInvoiceTemplate::forType(TaxInvoice::TYPE_STANDARD)->letterhead_path);
        $this->assertSame('b.png', TaxInvoiceTemplate::forType(TaxInvoice::TYPE_SIMPLIFIED)->letterhead_path);
    }

    public function test_invalid_template_type_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service()->saveTemplateLetterhead('غير معروف', 'x.png');
    }

    public function test_pdf_html_includes_letterhead_background_when_uploaded(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('letterhead.png', 10, 'image/png');
        $path = $file->store('tax-invoice-templates', 'public');
        $this->service()->saveTemplateLetterhead(TaxInvoice::TYPE_STANDARD, $path);

        $invoice = $this->issue();
        $html = app(TaxInvoicePdfService::class)->buildHtml($invoice);

        $this->assertStringContainsString('tax-invoice-letterhead', $html);
        $this->assertStringContainsString('position:fixed', $html);
    }

    public function test_pdf_html_has_no_letterhead_image_without_an_upload(): void
    {
        $invoice = $this->issue();
        $html = app(TaxInvoicePdfService::class)->buildHtml($invoice);

        $this->assertStringNotContainsString('tax-invoice-letterhead', $html);
    }

    public function test_pdf_html_labels_simplified_invoices_distinctly(): void
    {
        $invoice = $this->service()->issue(
            items: [['description' => 'خدمة', 'quantity' => 1, 'unit_price' => 50]],
            buyer: ['name' => 'عميل'],
            invoiceType: TaxInvoice::TYPE_SIMPLIFIED,
        );

        $html = app(TaxInvoicePdfService::class)->buildHtml($invoice);

        $this->assertStringContainsString('فاتورة ضريبية مبسطة', $html);
    }

    public function test_pdf_html_surfaces_company_data_from_the_profile(): void
    {
        CompanyProfile::current()->update([
            'name' => 'مؤسسة الاختبار',
            'tax_number' => '399999999900003',
            'address' => 'الرياض، حي الاختبار',
        ]);

        $invoice = $this->issue();
        $html = app(TaxInvoicePdfService::class)->buildHtml($invoice);

        $this->assertStringContainsString('مؤسسة الاختبار', $html);
        $this->assertStringContainsString('الرياض، حي الاختبار', $html);
    }

    public function test_pdf_html_embeds_zatca_qr_image_with_arabic_caption(): void
    {
        $invoice = $this->issue();
        $html = app(TaxInvoicePdfService::class)->buildHtml($invoice);

        $this->assertStringContainsString('رمز الفاتورة الإلكتروني (ZATCA)', $html);
        $this->assertStringContainsString('alt="ZATCA QR"', $html);
        $this->assertStringNotContainsString('TLV base64', $html);
    }
}
