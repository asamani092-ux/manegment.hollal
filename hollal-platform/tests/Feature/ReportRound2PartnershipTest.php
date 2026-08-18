<?php

namespace Tests\Feature;

use App\Livewire\Partnerships\OrganizationsIndex;
use App\Livewire\Partnerships\OrganizationShow;
use App\Livewire\Partnerships\PartnerPortal;
use App\Livewire\Partnerships\PartnershipShow;
use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\Partnership;
use App\Models\PartnershipContract;
use App\Models\Program;
use App\Models\ProgramPrice;
use App\Models\Quote;
use App\Models\User;
use App\Notifications\PartnershipPortalInvite;
use App\Services\PartnerPortalService;
use App\Services\PartnershipContractService;
use App\Services\QuoteService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ReportRound2PartnershipTest extends TestCase
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
            'partnerships.organizations.view',
            'partnerships.organizations.manage',
            'partnerships.pipeline.view',
            'partnerships.pipeline.manage',
            'partnerships.contracts.confirm',
            'partnerships.links.manage',
            'partnerships.quotes.create',
        ]);

        return $user;
    }

    public function test_catalog_link_portal_acceptance_and_internal_approval_are_end_to_end(): void
    {
        Notification::fake();
        $manager = $this->manager();
        $organization = Organization::create(['name' => 'جمعية الاختبار']);
        OrganizationContact::create([
            'organization_id' => $organization->id,
            'name' => 'مسؤول الجهة',
            'email' => 'partner@example.test',
            'is_primary' => true,
        ]);
        $program = Program::create(['name' => 'برنامج الاختبار', 'stage' => Program::STAGE_ACTIVE]);
        ProgramPrice::create([
            'program_id' => $program->id,
            'service_type' => ProgramPrice::SERVICE_TRAINING,
            'unit_price' => 1000,
        ]);

        Livewire::actingAs($manager)
            ->test(OrganizationsIndex::class)
            ->call('openQuickPartnership', $organization->id)
            ->set('quickOwnerId', $manager->id)
            ->set('quickProgramIds', [$program->id])
            ->call('createQuickPartnership')
            ->assertHasNoErrors();

        $partnership = Partnership::with('allowedPrograms')->firstOrFail();
        $this->assertSame(Partnership::STAGE_OPPORTUNITY, $partnership->stage);
        $this->assertTrue($partnership->allowedPrograms->contains($program));
        $this->assertSame(0, $partnership->quotes()->count());

        $quote = app(QuoteService::class)->create($partnership, [[
            'program_id' => $program->id,
            'service_type' => ProgramPrice::SERVICE_TRAINING,
            'quantity' => 1,
        ]], author: $manager);
        $this->assertSame(Partnership::STAGE_OPPORTUNITY, $partnership->fresh()->stage);
        $this->assertSame('1150.00', (string) $quote->total);

        $executive = User::factory()->create(['must_change_password' => false]);
        $executive->givePermissionTo(['partnerships.quotes.approve', 'partnerships.quotes.finalize']);
        app(QuoteService::class)->approve($quote, $executive);
        $this->assertSame(Partnership::STAGE_QUOTE, $partnership->fresh()->stage);

        $link = app(PartnerPortalService::class)->issue($partnership, $manager);
        $portal = Livewire::test(PartnerPortal::class, ['token' => $link->token])
            ->set('selectedProgramIds', [$program->id])
            ->set('programQuantities', [$program->id => '2'])
            ->set('programServices', [$program->id => ProgramPrice::SERVICE_TRAINING])
            ->call('acceptQuote', $quote->id)
            ->assertHasNoErrors();

        $acceptedQuote = $quote->fresh();
        $this->assertSame(Quote::STATUS_ACCEPTED, $acceptedQuote->status);
        $this->assertSame('1150.00', (string) $acceptedQuote->total);

        $contract = PartnershipContract::query()->where('quote_id', $acceptedQuote->id)->firstOrFail();
        $this->assertSame(PartnershipContract::STATUS_AWAITING_SIGNATURE, $contract->status);
        $this->assertFalse($contract->requires_first_payment);

        $png = 'data:image/png;base64,'.base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        $portal->set('signatureName', 'مدير الجهة')
            ->set('signaturePosition', 'مدير تنفيذي')
            ->set('signaturePadData', $png)
            ->call('signElectronically', $contract->id)
            ->assertHasNoErrors();

        $this->assertTrue($partnership->fresh()->awaiting_internal_approval);

        Livewire::actingAs($manager)
            ->test(PartnershipShow::class, ['partnership' => $partnership])
            ->call('confirmContract', $contract->id)
            ->assertHasNoErrors();

        $this->assertFalse($partnership->fresh()->awaiting_internal_approval);
        $this->assertSame(PartnershipContract::STATUS_CONFIRMED, $contract->fresh()->status);
    }

    public function test_link_email_and_whatsapp_copy_use_the_full_portal_url(): void
    {
        Notification::fake();
        $manager = $this->manager();
        $partnership = Partnership::create(['entity_name' => 'جهة الرابط', 'stage' => Partnership::STAGE_QUOTE]);
        $organization = Organization::create(['name' => 'جهة الرابط']);
        $partnership->forceFill(['organization_id' => $organization->id])->save();
        OrganizationContact::create([
            'organization_id' => $organization->id,
            'name' => 'جهة اتصال',
            'email' => 'contact@example.test',
        ]);

        $link = app(PartnerPortalService::class)->issue($partnership, $manager);
        $url = app(PartnerPortalService::class)->emailLink($link);

        $this->assertSame(route('partner.portal', ['token' => $link->token]), $url);
        $this->assertStringContainsString($url, app(PartnerPortalService::class)->whatsappText($link));
        Notification::assertSentOnDemand(PartnershipPortalInvite::class);
        $this->assertDatabaseHas('partner_portal_activities', [
            'partner_link_id' => $link->id,
            'action' => 'portal.invite_emailed',
        ]);
    }

    public function test_internal_review_can_return_a_signed_contract_with_notes_and_contacts_are_crud(): void
    {
        $manager = $this->manager();
        $organization = Organization::create(['name' => 'جهة المراجعة']);

        Livewire::actingAs($manager)
            ->test(OrganizationShow::class, ['organization' => $organization])
            ->call('openContactCreate')
            ->set('contactName', 'مسؤول جديد')
            ->set('contactEmail', 'new-contact@example.test')
            ->set('contactPrimary', true)
            ->call('saveContact')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('organization_contacts', [
            'organization_id' => $organization->id,
            'email' => 'new-contact@example.test',
            'is_primary' => true,
        ]);

        $program = Program::create(['name' => 'برنامج المراجعة', 'stage' => Program::STAGE_ACTIVE]);
        ProgramPrice::create([
            'program_id' => $program->id,
            'service_type' => ProgramPrice::SERVICE_TRAINING,
            'unit_price' => 100,
        ]);
        $partnership = Partnership::create([
            'organization_id' => $organization->id,
            'owner_id' => $manager->id,
            'entity_name' => $organization->name,
            'stage' => Partnership::STAGE_QUOTE,
        ]);
        $partnership->allowedPrograms()->attach($program);
        $quote = app(QuoteService::class)->create($partnership, [[
            'program_id' => $program->id,
            'service_type' => ProgramPrice::SERVICE_TRAINING,
            'quantity' => 1,
        ]]);
        $executive = User::factory()->create(['must_change_password' => false]);
        $executive->givePermissionTo(['partnerships.quotes.approve', 'partnerships.quotes.finalize']);
        app(QuoteService::class)->approve($quote, $executive);
        app(QuoteService::class)->accept($quote->fresh());
        $contract = app(PartnershipContractService::class)->createFromQuote(
            $quote->fresh(),
            [['amount' => 100, 'due_on' => now()->toDateString()]],
            requiresFirstPayment: false,
        );
        app(PartnershipContractService::class)->uploadSignedCopy(
            $contract,
            UploadedFile::fake()->create('signed.pdf', 10, 'application/pdf'),
            'مسؤول الجهة',
        );

        Livewire::actingAs($manager)
            ->test(PartnershipShow::class, ['partnership' => $partnership])
            ->set('internalApprovalNotes', 'يرجى تحديث بند التنفيذ')
            ->call('returnContract', $contract->id)
            ->assertHasNoErrors();

        $this->assertFalse($partnership->fresh()->awaiting_internal_approval);
        $this->assertSame('يرجى تحديث بند التنفيذ', $partnership->fresh()->internal_approval_notes);
        $this->assertSame(PartnershipContract::STATUS_AWAITING_SIGNATURE, $contract->fresh()->status);
    }

    public function test_execution_days_are_not_marked_stale_and_guest_tool_is_removed(): void
    {
        $manager = $this->manager();
        $partnership = Partnership::create([
            'entity_name' => 'تنفيذ قديم',
            'stage' => Partnership::STAGE_EXECUTION,
            'stage_entered_at' => now()->subDays(30),
        ]);

        $this->assertSame(30, $partnership->executionDays());
        $this->assertNotContains($partnership->id, app(\App\Services\PartnershipPipelineService::class)->stale()->pluck('id')->all());

        Livewire::actingAs($manager)
            ->test(\App\Livewire\Partnerships\PartnershipsPipeline::class)
            ->assertSee('مدة التنفيذ 30 يومًا');

        $this->assertFalse(Route::has('partnership.guest'));
        $this->assertStringNotContainsString('guest', (string) file_get_contents(config_path('uat_tools.php')));
        $this->assertStringNotContainsString('حلال', (string) file_get_contents(resource_path('views/livewire/projects/projects-index.blade.php')));
    }
}
