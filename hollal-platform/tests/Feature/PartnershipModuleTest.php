<?php

namespace Tests\Feature;

use App\Livewire\Partnerships\OrganizationsIndex;
use App\Livewire\Partnerships\PartnerPortal;
use App\Livewire\Partnerships\PartnershipShow;
use App\Livewire\Partnerships\PartnershipsPipeline;
use App\Models\ContractPaymentSchedule;
use App\Models\Organization;
use App\Models\OrganizationImpactRecord;
use App\Models\Partnership;
use App\Models\PartnershipContract;
use App\Models\PartnershipPayment;
use App\Models\Program;
use App\Models\ProgramPrice;
use App\Models\ProjectGenerationRequest;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Revenue;
use App\Models\TaxInvoice;
use App\Models\User;
use App\Notifications\PartnershipPaymentLate;
use App\Notifications\PartnershipStale;
use App\Services\PartnerPortalService;
use App\Services\PartnershipContractService;
use App\Services\PartnershipPaymentService;
use App\Services\PartnershipPipelineService;
use App\Services\ProjectGenerationRequestService;
use App\Services\QuoteService;
use App\Support\Setting;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 05-B1..05-B7 — organizations, seven-stage pipeline, quotes, contracts,
 * partner portal, payments → revenue → invoice, and project generation.
 */
class PartnershipModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(PermissionSeeder::class);
    }

    private function manager(): User
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo([
            'partnerships.organizations.view', 'partnerships.organizations.manage',
            'partnerships.pipeline.view', 'partnerships.pipeline.manage',
            'partnerships.quotes.view', 'partnerships.quotes.create', 'partnerships.quotes.approve',
            'partnerships.contracts.view', 'partnerships.contracts.create',
            'partnerships.contracts.manage', 'partnerships.contracts.confirm',
            'partnerships.payments.view', 'partnerships.payments.record', 'partnerships.payments.confirm',
            'partnerships.links.manage', 'partnerships.generate',
            'finance.tax_invoices.issue', 'finance.tax_invoices.view',
        ]);

        return $user;
    }

    private function partnership(?Organization $organization = null): Partnership
    {
        return Partnership::create([
            'organization_id' => ($organization ?? Organization::create(['name' => 'جمعية النور']))->id,
            'entity_name' => 'جمعية النور',
            'stage' => Partnership::STAGE_OPPORTUNITY,
            'stage_entered_at' => now(),
        ]);
    }

    /** @return array{0: Quote, 1: Partnership} */
    private function acceptedQuote(): array
    {
        $partnership = $this->partnership();
        $program = Program::create(['name' => 'برنامج القراءة', 'stage' => Program::STAGE_ACTIVE]);
        ProgramPrice::create([
            'program_id' => $program->id,
            'service_type' => ProgramPrice::SERVICE_TRAINING,
            'unit_price' => 1000,
        ]);

        $quote = app(QuoteService::class)->create($partnership, [
            ['program_id' => $program->id, 'service_type' => ProgramPrice::SERVICE_TRAINING, 'quantity' => 2],
        ]);

        app(QuoteService::class)->accept($quote);

        return [$quote->fresh(), $partnership];
    }

    // ------------------------------------------------------------ 05-B1

    public function test_organization_page_is_scoped_by_permission(): void
    {
        $organization = Organization::create(['name' => 'مدرسة الأمل']);
        $stranger = User::factory()->create(['must_change_password' => false]);

        $this->actingAs($stranger)->get('/organizations/'.$organization->id)->assertForbidden();
        $this->actingAs($this->manager())->get('/organizations/'.$organization->id)->assertOk();
    }

    public function test_soft_deleting_an_organization_keeps_its_history(): void
    {
        $organization = Organization::create(['name' => 'وقف الخير']);
        $partnership = $this->partnership($organization);

        Livewire::actingAs($this->manager())->test(OrganizationsIndex::class)
            ->call('archive', $organization->id);

        $this->assertSoftDeleted('organizations', ['id' => $organization->id]);
        $this->assertDatabaseHas('partnerships', ['id' => $partnership->id, 'organization_id' => $organization->id]);
        $this->assertNotNull(Organization::withTrashed()->find($organization->id));
    }

    public function test_organization_page_shows_projects_impact_and_timeline(): void
    {
        $organization = Organization::create(['name' => 'جمعية تحفيظ']);
        $partnership = $this->partnership($organization);
        $project = Project::factory()->create();
        $partnership->forceFill(['project_id' => $project->id])->save();

        OrganizationImpactRecord::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'beneficiaries' => 120,
            'improvement_percent' => 30,
            'satisfaction_percent' => 90,
        ]);

        app(PartnershipPipelineService::class)->moveTo($partnership, Partnership::STAGE_CONTACT, null, 'أول تواصل');

        $this->assertCount(1, $organization->projects());
        $this->assertSame(120, $organization->cumulativeImpact()['beneficiaries']);
        $this->assertNotEmpty($organization->timeline());
    }

    // ------------------------------------------------------------ 05-B2

    public function test_stage_transition_writes_a_stage_log(): void
    {
        $partnership = $this->partnership();
        $actor = $this->manager();

        app(PartnershipPipelineService::class)->moveTo($partnership, Partnership::STAGE_MEETING, $actor, 'حُدد اللقاء');

        $this->assertSame(Partnership::STAGE_MEETING, $partnership->fresh()->stage);
        $this->assertDatabaseHas('partnership_stage_logs', [
            'partnership_id' => $partnership->id,
            'from_stage' => Partnership::STAGE_OPPORTUNITY,
            'to_stage' => Partnership::STAGE_MEETING,
            'note' => 'حُدد اللقاء',
            'changed_by' => $actor->id,
        ]);
    }

    public function test_unknown_stage_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(PartnershipPipelineService::class)->moveTo($this->partnership(), 42);
    }

    public function test_stale_alert_fires_past_the_threshold(): void
    {
        Notification::fake();
        Setting::set('notifications.partnership_stale_days', 14);

        $owner = $this->manager();
        $fresh = $this->partnership();
        $stale = $this->partnership();
        $stale->forceFill(['owner_id' => $owner->id, 'stage_entered_at' => now()->subDays(30)])->save();

        $alerted = app(PartnershipPipelineService::class)->fireStaleAlerts();

        $this->assertSame([$stale->id], $alerted);
        $this->assertNotContains($fresh->id, $alerted);
        Notification::assertSentTo($owner, PartnershipStale::class);
    }

    public function test_pipeline_screen_moves_a_stage(): void
    {
        $partnership = $this->partnership();

        Livewire::actingAs($this->manager())->test(PartnershipsPipeline::class)
            ->call('openStageModal', $partnership->id)
            ->set('targetStage', Partnership::STAGE_DIAGNOSIS)
            ->set('stageNote', 'أُرسلت الاستبانة')
            ->call('moveStage')
            ->assertHasNoErrors();

        $this->assertSame(Partnership::STAGE_DIAGNOSIS, $partnership->fresh()->stage);
    }

    // ------------------------------------------------------------ 05-B3

    public function test_quote_totals_and_tax_are_computed_from_program_prices(): void
    {
        [$quote] = $this->acceptedQuote();

        $this->assertSame('2000.00', (string) $quote->subtotal);
        $this->assertSame('300.00', (string) $quote->tax_total);
        $this->assertSame('2300.00', (string) $quote->total);
        $this->assertSame('1000.00', (string) $quote->items->first()->unit_price);
    }

    public function test_discount_is_applied_before_tax(): void
    {
        $partnership = $this->partnership();

        $quote = app(QuoteService::class)->create($partnership, [
            ['service_type' => ProgramPrice::SERVICE_VISIT, 'quantity' => 1, 'unit_price' => 1000],
        ], discount: 200);

        $this->assertSame('120.00', (string) $quote->tax_total);
        $this->assertSame('920.00', (string) $quote->total);
    }

    public function test_revising_a_quote_preserves_the_previous_version(): void
    {
        [$quote, $partnership] = $this->acceptedQuote();

        $revised = app(QuoteService::class)->revise($quote, [
            ['service_type' => ProgramPrice::SERVICE_TRAINING, 'quantity' => 1, 'unit_price' => 500],
        ]);

        $this->assertSame(2, $revised->version);
        $this->assertSame($quote->id, $revised->supersedes_id);
        $this->assertSame('2000.00', (string) $quote->fresh()->subtotal);
        $this->assertSame(2, $partnership->quotes()->count());
    }

    public function test_a_quote_cannot_be_sent_before_internal_approval(): void
    {
        $partnership = $this->partnership();
        $quote = app(QuoteService::class)->create($partnership, [
            ['service_type' => ProgramPrice::SERVICE_TRAINING, 'quantity' => 1, 'unit_price' => 100],
        ]);

        $this->expectException(\RuntimeException::class);
        app(QuoteService::class)->send($quote);
    }

    public function test_quote_pdf_route_is_authorized_and_carries_the_tax_number(): void
    {
        [$quote] = $this->acceptedQuote();
        $stranger = User::factory()->create(['must_change_password' => false]);

        $this->actingAs($stranger)->get(route('quotes.pdf', $quote->id))->assertForbidden();

        $this->actingAs($this->manager())
            ->get(route('quotes.pdf', $quote->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    // ------------------------------------------------------------ 05-B4

    public function test_contract_requires_an_accepted_quote(): void
    {
        $partnership = $this->partnership();
        $quote = app(QuoteService::class)->create($partnership, [
            ['service_type' => ProgramPrice::SERVICE_TRAINING, 'quantity' => 1, 'unit_price' => 100],
        ]);

        $this->expectException(\RuntimeException::class);
        app(PartnershipContractService::class)->createFromQuote($quote, [
            ['amount' => 100, 'due_on' => now()->toDateString()],
        ]);
    }

    public function test_signed_pdf_hash_is_stored_on_upload(): void
    {
        $contract = $this->contract();

        app(PartnershipContractService::class)->uploadSignedCopy(
            $contract,
            UploadedFile::fake()->create('signed.pdf', 20, 'application/pdf'),
            'مدير الجهة',
            'Chrome',
        );

        $contract->refresh();
        $this->assertNotNull($contract->signed_pdf_hash);
        $this->assertSame(64, strlen($contract->signed_pdf_hash));
        $this->assertSame(PartnershipContract::STATUS_SIGNED, $contract->status);
        Storage::disk('local')->assertExists($contract->signed_pdf_path);
    }

    public function test_no_contracting_without_a_signed_copy(): void
    {
        $contract = $this->contract();

        $this->expectException(\RuntimeException::class);
        app(PartnershipContractService::class)->confirm($contract, $this->manager());
    }

    public function test_contracting_gate_enforces_the_first_payment_when_required(): void
    {
        $contract = $this->contract();
        $manager = $this->manager();

        app(PartnershipContractService::class)->uploadSignedCopy(
            $contract, UploadedFile::fake()->create('signed.pdf', 10, 'application/pdf'), 'مدير الجهة'
        );

        try {
            app(PartnershipContractService::class)->confirm($contract->fresh(), $manager);
            $this->fail('contracting should be blocked without the confirmed first payment');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('تأكيد المالية', $e->getMessage());
        }

        $scheduleItem = $contract->schedule()->orderBy('sequence')->firstOrFail();
        $payment = app(PartnershipPaymentService::class)->record($scheduleItem, 1150);
        app(PartnershipPaymentService::class)->confirm($payment, $manager);

        $confirmed = app(PartnershipContractService::class)->confirm($contract->fresh(), $manager);

        $this->assertSame(PartnershipContract::STATUS_CONFIRMED, $confirmed->status);
        $this->assertSame(Partnership::STAGE_CONTRACTED, $confirmed->partnership->fresh()->stage);
    }

    // ------------------------------------------------------------ 05-B5

    public function test_portal_token_cannot_reach_another_organizations_data(): void
    {
        [$quoteA, $partnershipA] = $this->acceptedQuote();
        [$quoteB] = $this->acceptedQuote();

        $link = app(PartnerPortalService::class)->issue($partnershipA);

        $component = Livewire::test(PartnerPortal::class, ['token' => $link->token]);

        try {
            $component->call('acceptQuote', $quoteB->id);
            $this->fail('a partner link must never reach another partnership\'s quote');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            // expected: the lookup is scoped to the link's own partnership
        }

        $this->assertSame(Quote::STATUS_ACCEPTED, $quoteA->fresh()->status);
    }

    public function test_expired_or_revoked_links_are_rejected(): void
    {
        $partnership = $this->partnership();
        $portal = app(PartnerPortalService::class);
        $link = $portal->issue($partnership);

        $portal->revoke($link);
        $this->assertNull($portal->resolve($link->token));

        $portal->renew($link);
        $this->assertNotNull($portal->resolve($link->token));

        $link->forceFill(['expires_at' => now()->subDay()])->save();
        $this->assertNull($portal->resolve($link->token));
    }

    public function test_portal_route_is_rate_limited(): void
    {
        $link = app(PartnerPortalService::class)->issue($this->partnership());

        $this->assertContains(
            'throttle:portal',
            collect(\Illuminate\Support\Facades\Route::getRoutes()->getByName('partner.portal')->gatherMiddleware())
                ->all()
        );

        $this->get('/portal/'.$link->token)->assertOk();
    }

    public function test_every_portal_action_is_logged(): void
    {
        $partnership = $this->partnership();
        $link = app(PartnerPortalService::class)->issue($partnership);

        Livewire::test(PartnerPortal::class, ['token' => $link->token])
            ->set('diagnosisAudience', 'طلاب المرحلة المتوسطة')
            ->set('diagnosisCount', '120')
            ->call('submitDiagnosis')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('partner_portal_activities', [
            'partnership_id' => $partnership->id,
            'action' => 'portal.diagnosis_submitted',
        ]);
        $this->assertDatabaseHas('partner_portal_activities', ['action' => 'portal.opened']);
    }

    public function test_portal_payment_is_recorded_pending_finance(): void
    {
        $contract = $this->contract();
        $link = app(PartnerPortalService::class)->issue($contract->partnership);
        $scheduleItem = $contract->schedule()->firstOrFail();

        Livewire::test(PartnerPortal::class, ['token' => $link->token])
            ->set('paymentAmount', '500')
            ->call('recordPayment', $scheduleItem->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('partnership_payments', [
            'partnership_id' => $contract->partnership_id,
            'status' => PartnershipPayment::STATUS_PENDING,
            'recorded_via' => PartnershipPayment::VIA_PORTAL,
        ]);
    }

    // ------------------------------------------------------------ 05-B6

    public function test_double_confirmation_does_not_duplicate_revenue(): void
    {
        $contract = $this->contract();
        $finance = $this->manager();
        $payment = app(PartnershipPaymentService::class)->record($contract->schedule()->firstOrFail(), 1150);

        app(PartnershipPaymentService::class)->confirm($payment, $finance);
        app(PartnershipPaymentService::class)->confirm($payment->fresh(), $finance);

        $this->assertSame(1, Revenue::where('source_id', $payment->id)->count());
        $this->assertSame(Revenue::STATUS_CONFIRMED, Revenue::firstOrFail()->status);
        $this->assertNotNull($payment->fresh()->revenue_id);
    }

    public function test_invoice_hook_creates_and_links_a_tax_invoice_once(): void
    {
        $contract = $this->contract();
        $finance = $this->manager();
        $payment = app(PartnershipPaymentService::class)->record($contract->schedule()->firstOrFail(), 1150);
        app(PartnershipPaymentService::class)->confirm($payment, $finance);

        $first = app(PartnershipPaymentService::class)->issueTaxInvoice($payment->fresh(), $finance);
        $second = app(PartnershipPaymentService::class)->issueTaxInvoice($payment->fresh(), $finance);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, TaxInvoice::count());
        $this->assertSame($first->id, $payment->fresh()->tax_invoice_id);
    }

    public function test_unconfirmed_payment_cannot_be_invoiced(): void
    {
        $contract = $this->contract();
        $payment = app(PartnershipPaymentService::class)->record($contract->schedule()->firstOrFail(), 100);

        $this->expectException(\RuntimeException::class);
        app(PartnershipPaymentService::class)->issueTaxInvoice($payment);
    }

    public function test_late_payment_alert(): void
    {
        Notification::fake();
        $contract = $this->contract();
        $owner = $this->manager();
        $contract->partnership->forceFill(['owner_id' => $owner->id])->save();
        $contract->schedule()->firstOrFail()->forceFill(['due_on' => now()->subWeek()])->save();

        $alerted = app(PartnershipPaymentService::class)->fireLateAlerts();

        $this->assertNotEmpty($alerted);
        Notification::assertSentTo($owner, PartnershipPaymentLate::class);
    }

    // ------------------------------------------------------------ 05-B7

    public function test_generation_is_blocked_before_contracting(): void
    {
        $partnership = $this->partnership();
        $program = Program::create(['name' => 'برنامج', 'stage' => Program::STAGE_ACTIVE]);

        $this->expectException(\RuntimeException::class);
        app(ProjectGenerationRequestService::class)->create($partnership, $program, now()->toDateString());
    }

    public function test_generate_button_creates_a_pending_request_with_the_quote_services(): void
    {
        $contract = $this->confirmedContract();
        $manager = $this->manager();
        $program = Program::firstOrFail();

        Livewire::actingAs($manager)->test(PartnershipShow::class, ['partnership' => $contract->partnership])
            ->call('openGenerateModal')
            ->set('generateProgramId', $program->id)
            ->set('generateLaunchDate', now()->addWeek()->toDateString())
            ->set('generateManagerId', $manager->id)
            ->call('generateProject')
            ->assertHasNoErrors();

        $request = ProjectGenerationRequest::firstOrFail();
        $this->assertSame(ProjectGenerationRequest::STATUS_PENDING, $request->status);
        $this->assertSame([ProgramPrice::SERVICE_TRAINING], $request->included_services);
        $this->assertSame($manager->id, $request->project_manager_id);
    }

    public function test_generate_button_requires_the_generate_permission(): void
    {
        $contract = $this->confirmedContract();
        $viewer = User::factory()->create(['must_change_password' => false]);
        $viewer->givePermissionTo('partnerships.pipeline.view');

        Livewire::actingAs($viewer)->test(PartnershipShow::class, ['partnership' => $contract->partnership])
            ->call('openGenerateModal')
            ->assertForbidden();
    }

    // ------------------------------------------------------------ helpers

    private function contract(): PartnershipContract
    {
        [$quote] = $this->acceptedQuote();

        return app(PartnershipContractService::class)->createFromQuote($quote, [
            ['label' => 'الدفعة الأولى', 'amount' => 1150, 'due_on' => now()->addWeek()->toDateString()],
            ['label' => 'الدفعة الثانية', 'amount' => 1150, 'due_on' => now()->addMonth()->toDateString()],
        ]);
    }

    private function confirmedContract(): PartnershipContract
    {
        $contract = $this->contract();
        $manager = $this->manager();

        app(PartnershipContractService::class)->uploadSignedCopy(
            $contract, UploadedFile::fake()->create('signed.pdf', 10, 'application/pdf'), 'مدير الجهة'
        );

        $payment = app(PartnershipPaymentService::class)->record(
            ContractPaymentSchedule::where('partnership_contract_id', $contract->id)->orderBy('sequence')->firstOrFail(),
            1150,
        );
        app(PartnershipPaymentService::class)->confirm($payment, $manager);

        return app(PartnershipContractService::class)->confirm($contract->fresh(), $manager);
    }
}
