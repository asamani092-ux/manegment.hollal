<?php

namespace Tests\Feature;

use App\Livewire\Partnerships\DiagnosisQuestionsIndex;
use App\Livewire\Partnerships\PartnerPortal;
use App\Livewire\Partnerships\PartnershipShow;
use App\Models\DiagnosisAnswer;
use App\Models\DiagnosisQuestion;
use App\Models\Organization;
use App\Models\Partnership;
use App\Models\Program;
use App\Models\ProgramPrice;
use App\Models\Quote;
use App\Models\User;
use App\Services\DiagnosisQuestionService;
use App\Services\PartnerPortalService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Partner completes programs → diagnosis → quote accept without admin pricing.
 * Time: O(q + items) | Space: O(q + items)
 */
class PartnerPortalSelfServeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_portal_shows_five_sections_and_builds_priced_quote_from_selection(): void
    {
        [$partnership, $program, $link] = $this->openPortal();

        $portal = Livewire::test(PartnerPortal::class, ['token' => $link->token])
            ->assertSee('1. البرامج')
            ->assertSee('2. التشخيص')
            ->assertSee('3. عروض الأسعار')
            ->assertSee('4. الدفعات')
            ->assertSee('5. العقد')
            ->assertDontSee('سعر الوحدة')
            ->set('selectedProgramIds', [$program->id])
            ->set('programQuantities', [$program->id => '2'])
            ->set('programServices', [$program->id => ProgramPrice::SERVICE_TRAINING])
            ->call('confirmPrograms')
            ->assertHasNoErrors();

        $quote = $partnership->quotes()->first();
        $this->assertNotNull($quote);
        $this->assertSame(Quote::STATUS_DRAFT, $quote->status);
        $this->assertSame('2300.00', (string) $quote->total);
        $this->assertTrue($partnership->fresh()->allowedPrograms->contains('id', $program->id));
        $this->assertSame(Partnership::STAGE_OPPORTUNITY, $partnership->fresh()->stage);

        Livewire::actingAs($this->manager())
            ->test(PartnershipShow::class, ['partnership' => $partnership->fresh()])
            ->assertSee('برنامج الاختبار')
            ->assertSee('مسودة');
    }

    public function test_quantity_change_updates_the_same_draft_and_accept_advances_after_diagnosis(): void
    {
        [$partnership, $program, $link] = $this->openPortal();

        $portal = Livewire::test(PartnerPortal::class, ['token' => $link->token])
            ->set('selectedProgramIds', [$program->id])
            ->set('programQuantities', [$program->id => '1'])
            ->set('programServices', [$program->id => ProgramPrice::SERVICE_TRAINING])
            ->call('confirmPrograms')
            ->assertHasNoErrors();

        $quoteId = (int) $partnership->quotes()->value('id');
        $this->assertSame('1150.00', (string) $partnership->quotes()->first()->total);

        $portal->set('programQuantities', [$program->id => '3'])
            ->call('confirmPrograms')
            ->assertHasNoErrors();

        $this->assertSame(1, $partnership->quotes()->count());
        $this->assertSame($quoteId, (int) $partnership->quotes()->value('id'));
        $this->assertSame('3450.00', (string) $partnership->quotes()->first()->total);

        $portal->set('diagnosisAudience', 'طلاب')
            ->set('diagnosisCount', '40')
            ->call('submitDiagnosis')
            ->assertHasNoErrors();

        $this->assertSame(Partnership::STAGE_DIAGNOSIS, $partnership->fresh()->stage);

        $portal->call('acceptQuote', $quoteId)->assertHasNoErrors();

        $this->assertSame(Quote::STATUS_ACCEPTED, $partnership->quotes()->first()->status);
        $this->assertSame(Partnership::STAGE_QUOTE, $partnership->fresh()->stage);
    }

    public function test_managed_diagnosis_question_appears_and_answers_persist_on_file(): void
    {
        $manager = $this->manager();
        $extra = app(DiagnosisQuestionService::class)->create([
            'label' => 'احتياج إضافي',
            'type' => 'text',
            'required' => true,
            'sort_order' => 10,
        ]);

        Livewire::actingAs($manager)
            ->test(DiagnosisQuestionsIndex::class)
            ->assertSee('الفئة')
            ->assertSee('احتياج إضافي')
            ->set('label', 'عدد القاعات')
            ->set('type', 'number')
            ->set('required', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('diagnosis_questions', ['label' => 'عدد القاعات', 'type' => 'number']);

        [$partnership, $program, $link] = $this->openPortal();

        Livewire::test(PartnerPortal::class, ['token' => $link->token, 'page' => 'diagnosis'])
            ->assertSee('احتياج إضافي')
            ->set('diagnosisAudience', 'معلمون')
            ->set('diagnosisCount', '15')
            ->set('diagnosisAnswers.'.$extra->id, 'تدريب ميداني')
            ->call('submitDiagnosis')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('diagnosis_answers', [
            'partnership_id' => $partnership->id,
            'question_id' => $extra->id,
            'value' => 'تدريب ميداني',
        ]);
        $this->assertSame(1, DiagnosisAnswer::query()->where('partnership_id', $partnership->id)->where('question_id', $extra->id)->count());

        Livewire::actingAs($manager)
            ->test(PartnershipShow::class, ['partnership' => $partnership->fresh()])
            ->assertSee('احتياج إضافي')
            ->assertSee('تدريب ميداني');
    }

    public function test_each_portal_option_is_its_own_page_and_quote_contract_are_solo(): void
    {
        [$partnership, $program, $link] = $this->openPortal();
        unset($partnership, $program);

        $this->get('/portal/'.$link->token)
            ->assertOk()
            ->assertSee('حفظ الاختيار وبناء العرض')
            ->assertDontSee('قبول العرض')
            ->assertDontSee('اعتماد التوقيع');

        $this->get(route('partner.portal.page', ['token' => $link->token, 'page' => 'quotes']))
            ->assertOk()
            ->assertSee('قبول العرض')
            ->assertSee('3. عروض الأسعار')
            ->assertDontSee('حفظ الاختيار وبناء العرض')
            ->assertDontSee('اعتماد التوقيع');

        $this->get(route('partner.portal.page', ['token' => $link->token, 'page' => 'contract']))
            ->assertOk()
            ->assertSee('5. العقد')
            ->assertDontSee('حفظ الاختيار وبناء العرض')
            ->assertDontSee('قبول العرض');

        $this->get('/portal/'.$link->token.'/unknown')->assertNotFound();
    }

    /** @return array{0: Partnership, 1: Program, 2: \App\Models\PartnerLink} */
    private function openPortal(): array
    {
        $organization = Organization::create(['name' => 'جهة البوابة']);
        $partnership = Partnership::create([
            'organization_id' => $organization->id,
            'entity_name' => $organization->name,
            'stage' => Partnership::STAGE_OPPORTUNITY,
            'stage_entered_at' => now(),
        ]);
        $program = Program::create(['name' => 'برنامج الاختبار', 'stage' => Program::STAGE_ACTIVE]);
        ProgramPrice::create([
            'program_id' => $program->id,
            'service_type' => ProgramPrice::SERVICE_TRAINING,
            'unit_price' => 1000,
            'is_active' => true,
        ]);
        $link = app(PartnerPortalService::class)->issue($partnership);

        return [$partnership, $program, $link];
    }

    private function manager(): User
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo([
            'partnerships.organizations.view',
            'partnerships.organizations.manage',
            'partnerships.pipeline.view',
            'partnerships.pipeline.manage',
            'partnerships.quotes.view',
            'partnerships.quotes.create',
            'partnerships.links.manage',
        ]);

        return $user;
    }
}
