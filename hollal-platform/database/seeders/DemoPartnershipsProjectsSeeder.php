<?php

namespace Database\Seeders;

use App\Models\ContractPaymentSchedule;
use App\Models\MeasurementForm;
use App\Models\MeasurementQuestion;
use App\Models\MeasurementResponse;
use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\Partnership;
use App\Models\PartnershipContract;
use App\Models\PartnershipPayment;
use App\Models\PartnershipStageLog;
use App\Models\Program;
use App\Models\ProgramPrice;
use App\Models\ProgramVersion;
use App\Models\Project;
use App\Models\ProjectEntityMember;
use App\Models\ProjectVisit;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo data for the partnerships (05-B*) and project execution (06B-B*) screens
 * so UAT runs against non-empty lists, boards and filters.
 *
 * Every section is keyed on a natural key through firstOrCreate, so a second run
 * is a no-op.
 *
 * Time: O(n) inserts over a fixed dataset | Space: O(1) beyond the created rows.
 */
class DemoPartnershipsProjectsSeeder extends Seeder
{
    private const TAX_RATE = 0.15;

    public function run(): void
    {
        $admin = User::where('phone', '0500000000')->first();
        $manager = User::where('phone', '0501111111')->first();
        $executive = User::where('phone', '0502222222')->first();
        $projectManager = User::where('phone', '0503333333')->first();

        if (! $admin || ! $manager || ! $executive) {
            $this->command?->warn('DemoPartnershipsProjectsSeeder: المستخدمون الأساسيون غير موجودين — تم التخطي.');

            return;
        }

        $projectManager ??= $manager;

        $organizations = $this->seedOrganizations();
        $programs = $this->seedPrograms($manager);
        $this->seedPartnerships($organizations, $manager, $executive);
        // Quotes/contracts/projects are left empty — UAT starts at diagnosis readiness.
    }

    /** @return array<string, Organization> */
    private function seedOrganizations(): array
    {
        $definitions = [
            'khraj' => [
                'name' => 'جمعية تحفيظ القرآن الكريم بمحافظة الخرج',
                'type' => 'جمعية تحفيظ',
                'city' => 'الخرج',
                'tax_number' => '310011122233344',
                'roles' => ['متعاقدة', 'جهة تنفيذ'],
                'notes' => 'شريك متكرر منذ 1445هـ، لديه 18 حلقة نسائية ورجالية.',
                'contacts' => [
                    ['name' => 'عبدالله الحربي', 'position' => 'المدير التنفيذي', 'phone' => '0551000101', 'email' => 'ceo@khraj-demo.org', 'is_primary' => true],
                    ['name' => 'منى القحطاني', 'position' => 'مشرفة الحلقات النسائية', 'phone' => '0551000102', 'email' => 'halaqat@khraj-demo.org', 'is_primary' => false],
                ],
            ],
            'education' => [
                'name' => 'إدارة التعليم بمنطقة الرياض',
                'type' => 'جهة حكومية',
                'city' => 'الرياض',
                'tax_number' => '300055566677788',
                'roles' => ['متعاقدة'],
                'notes' => 'التنسيق يتم عبر إدارة النشاط الطلابي، ويشترط خطاب رسمي قبل كل زيارة.',
                'contacts' => [
                    ['name' => 'فهد السبيعي', 'position' => 'مدير النشاط الطلابي', 'phone' => '0552000201', 'email' => 'activity@edu-demo.gov', 'is_primary' => true],
                ],
            ],
            'manarat' => [
                'name' => 'شركة منارات التعليم للتدريب',
                'type' => 'شركة تعليمية',
                'city' => 'جدة',
                'tax_number' => '310099988877766',
                'roles' => ['جهة تنفيذ'],
                'notes' => 'مزود تدريب معتمد، يتم التعاقد معه لتنفيذ ورش المعلمين.',
                'contacts' => [
                    ['name' => 'ريم العتيبي', 'position' => 'مديرة تطوير الأعمال', 'phone' => '0553000301', 'email' => 'bd@manarat-demo.com', 'is_primary' => true],
                ],
            ],
            'waqf' => [
                'name' => 'وقف البر الخيري بالدمام',
                'type' => 'وقف',
                'city' => 'الدمام',
                'tax_number' => '310044455566677',
                'roles' => ['مانحة'],
                'notes' => 'يمول برامج القياس والأثر ويطلب تقريراً ربعياً.',
                'contacts' => [
                    ['name' => 'سلطان الدوسري', 'position' => 'أمين الوقف', 'phone' => '0554000401', 'email' => 'waqf@birr-demo.org', 'is_primary' => true],
                ],
            ],
        ];

        $organizations = [];

        foreach ($definitions as $key => $definition) {
            $contacts = $definition['contacts'];
            unset($definition['contacts']);

            $organization = Organization::firstOrCreate(['name' => $definition['name']], $definition);
            if (empty($organization->tax_number) && ! empty($definition['tax_number'])) {
                $organization->update(['tax_number' => $definition['tax_number']]);
            }

            foreach ($contacts as $contact) {
                OrganizationContact::firstOrCreate(
                    ['organization_id' => $organization->id, 'phone' => $contact['phone']],
                    [...$contact, 'organization_id' => $organization->id],
                );
            }

            $organizations[$key] = $organization;
        }

        return $organizations;
    }

    /** @return array<string, Program> */
    private function seedPrograms(User $manager): array
    {
        $definitions = [
            'itqan' => [
                'name' => 'برنامج إتقان لتجويد التلاوة',
                'description' => 'برنامج تدريبي لطلاب حلقات التحفيظ يرفع مستوى التلاوة والأحكام خلال فصل دراسي واحد.',
                'stage' => Program::STAGE_ACTIVE,
                'target_audience' => 'طلاب حلقات التحفيظ من 10 إلى 15 سنة',
                'sessions_count' => 12,
                'hours_count' => 24,
                'execution_requirements' => 'قاعة تتسع لـ 25 طالباً، سماعات، ومشرف من الجهة.',
                'prices' => [
                    ProgramPrice::SERVICE_PACKAGE => 85,
                    ProgramPrice::SERVICE_TRAINING => 3500,
                    ProgramPrice::SERVICE_VISIT => 1200,
                ],
            ],
            'leaders' => [
                'name' => 'برنامج بناء القادة الصغار',
                'description' => 'برنامج مهارات قيادية وحياتية لطلاب المرحلة المتوسطة بالتعاون مع المدارس.',
                'stage' => Program::STAGE_ACTIVE,
                'target_audience' => 'طلاب المرحلة المتوسطة',
                'sessions_count' => 8,
                'hours_count' => 16,
                'execution_requirements' => 'مسرح المدرسة، وجهاز عرض، ومنسق من إدارة النشاط.',
                'prices' => [
                    ProgramPrice::SERVICE_PACKAGE => 65,
                    ProgramPrice::SERVICE_TRAINING => 4200,
                    ProgramPrice::SERVICE_MEASUREMENT => 950,
                ],
            ],
            'teacher' => [
                'name' => 'برنامج المعلم الملهم',
                'description' => 'ورش تأهيلية لمعلمي الحلقات في إدارة الصف وأساليب التحفيز.',
                'stage' => Program::STAGE_ACTIVE,
                'target_audience' => 'معلمو ومعلمات حلقات التحفيظ',
                'sessions_count' => 6,
                'hours_count' => 18,
                'execution_requirements' => 'قاعة تدريب، وحضور لا يقل عن 15 معلماً.',
                'prices' => [
                    ProgramPrice::SERVICE_TRAINING => 5200,
                    ProgramPrice::SERVICE_CONSULTATION => 1800,
                ],
            ],
            'impact' => [
                'name' => 'برنامج قياس الأثر التربوي',
                'description' => 'حزمة أدوات قياس قبلي وبعدي لقياس أثر البرامج التربوية على المستفيدين.',
                'stage' => Program::STAGE_DEVELOPMENT,
                'target_audience' => 'الجهات الشريكة ومدراء البرامج',
                'sessions_count' => 4,
                'hours_count' => 8,
                'execution_requirements' => 'قائمة مستفيدين محدثة، وصلاحية دخول لمنسق الجهة.',
                'prices' => [
                    ProgramPrice::SERVICE_MEASUREMENT => 1500,
                    ProgramPrice::SERVICE_CONSULTATION => 2100,
                ],
            ],
        ];

        $programs = [];

        foreach ($definitions as $key => $definition) {
            $prices = $definition['prices'];
            unset($definition['prices']);

            $program = Program::firstOrCreate(['name' => $definition['name']], $definition);

            foreach ($prices as $serviceType => $unitPrice) {
                ProgramPrice::firstOrCreate(
                    ['program_id' => $program->id, 'service_type' => $serviceType],
                    ['unit_price' => $unitPrice, 'currency' => 'SAR', 'is_active' => true],
                );
            }

            $version = ProgramVersion::firstOrCreate(
                ['program_id' => $program->id, 'version_label' => 'v1.0'],
                [
                    'changed_by' => $manager->id,
                    'change_reason' => 'الإصدار الأول المعتمد للبرنامج',
                    'is_current' => true,
                    'notes' => 'اعتماد بطاقة البرنامج وأسعار الخدمات.',
                    'approved_by' => $manager->id,
                    'approved_at' => now()->subMonths(3),
                ],
            );

            if ($program->current_version_id === null) {
                $program->forceFill(['current_version_id' => $version->id])->save();
            }

            $programs[$key] = $program;
        }

        return $programs;
    }

    /**
     * Partnerships ready at diagnosis — workspace opens here for the new cycle.
     *
     * @param  array<string, Organization>  $organizations
     * @return array<string, Partnership>
     */
    private function seedPartnerships(array $organizations, User $manager, User $executive): array
    {
        $definitions = [
            'khraj' => [
                'organization' => $organizations['khraj'],
                'owner' => $manager,
                'stage' => Partnership::STAGE_DIAGNOSIS,
                'status' => 'active',
                'entity_name' => $organizations['khraj']->name,
                'contact_person' => 'عبدالله الحربي',
                'contact_phone' => '0551000101',
                'expected_value' => 138000,
                'stage_entered_at' => now(),
                'type_quantity' => 'جاهز للتشخيص — 3 حلقات',
                'portal_features' => Partnership::defaultPortalFeatures(),
                'journey' => [
                    [Partnership::STAGE_OPPORTUNITY, 'فرصة واردة من ملتقى الجمعيات', 14],
                    [Partnership::STAGE_CONTACT, 'تواصل هاتفي مع المدير التنفيذي', 10],
                    [Partnership::STAGE_MEETING, 'لقاء تعريفي في مقر الجمعية', 5],
                    [Partnership::STAGE_DIAGNOSIS, 'جاهزية التشخيص ومساحة العمل', 0],
                ],
            ],
            'education' => [
                'organization' => $organizations['education'],
                'owner' => $executive,
                'stage' => Partnership::STAGE_DIAGNOSIS,
                'status' => 'active',
                'entity_name' => $organizations['education']->name,
                'contact_person' => 'فهد السبيعي',
                'contact_phone' => '0552000201',
                'expected_value' => 96000,
                'stage_entered_at' => now(),
                'type_quantity' => 'جاهز للتشخيص — 4 مدارس',
                'portal_features' => Partnership::defaultPortalFeatures(),
                'journey' => [
                    [Partnership::STAGE_OPPORTUNITY, 'ترشيح من شريك سابق', 12],
                    [Partnership::STAGE_CONTACT, 'خطاب رسمي لإدارة النشاط الطلابي', 8],
                    [Partnership::STAGE_MEETING, 'عرض تعريفي أمام لجنة النشاط', 3],
                    [Partnership::STAGE_DIAGNOSIS, 'جاهزية التشخيص ومساحة العمل', 0],
                ],
            ],
            'manarat' => [
                'organization' => $organizations['manarat'],
                'owner' => $manager,
                'stage' => Partnership::STAGE_DIAGNOSIS,
                'status' => 'active',
                'entity_name' => $organizations['manarat']->name,
                'contact_person' => 'ريم العتيبي',
                'contact_phone' => '0553000301',
                'expected_value' => 42000,
                'stage_entered_at' => now(),
                'type_quantity' => 'جاهز للتشخيص — ورش معلمين',
                'portal_features' => Partnership::defaultPortalFeatures(),
                'journey' => [
                    [Partnership::STAGE_OPPORTUNITY, 'طلب وارد عبر الموقع', 9],
                    [Partnership::STAGE_CONTACT, 'مكالمة تعريفية', 6],
                    [Partnership::STAGE_MEETING, 'اجتماع عن بعد', 2],
                    [Partnership::STAGE_DIAGNOSIS, 'جاهزية التشخيص ومساحة العمل', 0],
                ],
            ],
            'waqf' => [
                'organization' => $organizations['waqf'],
                'owner' => $executive,
                'stage' => Partnership::STAGE_DIAGNOSIS,
                'status' => 'active',
                'entity_name' => $organizations['waqf']->name,
                'contact_person' => 'سلطان الدوسري',
                'contact_phone' => '0554000401',
                'expected_value' => 60000,
                'stage_entered_at' => now(),
                'type_quantity' => 'جاهز للتشخيص — قياس أثر',
                'portal_features' => Partnership::defaultPortalFeatures(),
                'journey' => [
                    [Partnership::STAGE_OPPORTUNITY, 'فرصة تمويل برامج القياس', 7],
                    [Partnership::STAGE_CONTACT, 'إرسال الملف التعريفي', 4],
                    [Partnership::STAGE_MEETING, 'لقاء أولي', 1],
                    [Partnership::STAGE_DIAGNOSIS, 'جاهزية التشخيص ومساحة العمل', 0],
                ],
            ],
        ];

        $partnerships = [];

        foreach ($definitions as $key => $definition) {
            $journey = $definition['journey'];
            $organization = $definition['organization'];
            $owner = $definition['owner'];
            unset($definition['journey'], $definition['organization'], $definition['owner']);

            $partnership = Partnership::firstOrCreate(
                ['organization_id' => $organization->id, 'entity_name' => $definition['entity_name']],
                [...$definition, 'organization_id' => $organization->id, 'owner_id' => $owner->id],
            );

            if (! $partnership->stageLogs()->exists()) {
                $previous = null;

                foreach ($journey as [$stage, $note, $daysAgo]) {
                    $log = PartnershipStageLog::create([
                        'partnership_id' => $partnership->id,
                        'from_stage' => $previous,
                        'to_stage' => $stage,
                        'note' => $note,
                        'changed_by' => $owner->id,
                    ]);

                    $this->backdate($log, now()->subDays($daysAgo));
                    $previous = $stage;
                }
            }

            $partnerships[$key] = $partnership;
        }

        return $partnerships;
    }

    /**
     * Two quotes: one still awaiting the entity's answer, one accepted and turned
     * into a contract.
     *
     * @param  array<string, Partnership>  $partnerships
     * @param  array<string, Program>  $programs
     * @return array<string, Quote>
     */
    private function seedQuotes(array $partnerships, array $programs, User $executive): array
    {
        $definitions = [
            'manarat' => [
                'partnership' => $partnerships['manarat'],
                'attributes' => [
                    'version' => 1,
                    'status' => Quote::STATUS_SENT,
                    'discount' => 1000,
                    'tax_rate' => self::TAX_RATE,
                    'entity_notes' => 'الجهة طلبت تنفيذ الورش على دفعتين خلال الفصل الثاني.',
                    'approved_by' => $executive->id,
                    'approved_at' => now()->subDays(19),
                    'sent_at' => now()->subDays(17),
                ],
                'items' => [
                    [$programs['teacher'], ProgramPrice::SERVICE_TRAINING, 'ورشة المعلم الملهم — يومان تدريبيان', 6, 5200],
                    [$programs['teacher'], ProgramPrice::SERVICE_CONSULTATION, 'استشارة تربوية لمتابعة أثر الورش', 4, 1800],
                ],
            ],
            'education' => [
                'partnership' => $partnerships['education'],
                'attributes' => [
                    'version' => 1,
                    'status' => Quote::STATUS_ACCEPTED,
                    'discount' => 2000,
                    'tax_rate' => self::TAX_RATE,
                    'entity_notes' => 'اعتمدت اللجنة العرض مع تثبيت الأسعار للعام الدراسي الحالي.',
                    'approved_by' => $executive->id,
                    'approved_at' => now()->subDays(28),
                    'sent_at' => now()->subDays(26),
                    'accepted_at' => now()->subDays(20),
                ],
                'items' => [
                    [$programs['leaders'], ProgramPrice::SERVICE_PACKAGE, 'حقائب بناء القادة الصغار — 4 مدارس', 400, 65],
                    [$programs['leaders'], ProgramPrice::SERVICE_TRAINING, 'أيام تدريبية داخل المدارس', 12, 4200],
                    [$programs['leaders'], ProgramPrice::SERVICE_MEASUREMENT, 'قياس قبلي وبعدي لكل مدرسة', 8, 950],
                ],
            ],
        ];

        $quotes = [];

        foreach ($definitions as $key => $definition) {
            /** @var Partnership $partnership */
            $partnership = $definition['partnership'];

            $quote = Quote::firstOrCreate(
                ['partnership_id' => $partnership->id, 'version' => $definition['attributes']['version']],
                [...$definition['attributes'], 'partnership_id' => $partnership->id],
            );

            if (! $quote->items()->exists()) {
                foreach ($definition['items'] as [$program, $serviceType, $description, $quantity, $unitPrice]) {
                    QuoteItem::create([
                        'quote_id' => $quote->id,
                        'program_id' => $program->id,
                        'service_type' => $serviceType,
                        'description' => $description,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'line_total' => round($quantity * $unitPrice, 2),
                    ]);
                }

                $this->recalculateQuote($quote);
            }

            $quotes[$key] = $quote;
        }

        return $quotes;
    }

    /**
     * Two signed contracts with their payment schedule; the executing one carries a
     * recorded payment still awaiting finance confirmation.
     *
     * @param  array<string, Partnership>  $partnerships
     * @param  array<string, Quote>  $quotes
     */
    private function seedContractsAndPayments(array $partnerships, array $quotes, User $admin): void
    {
        $khrajContract = PartnershipContract::firstOrCreate(
            ['partnership_id' => $partnerships['khraj']->id],
            [
                'quote_id' => null,
                'status' => PartnershipContract::STATUS_CONFIRMED,
                'starts_on' => now()->subDays(45)->toDateString(),
                'ends_on' => now()->addDays(135)->toDateString(),
                'hollal_commitments' => 'تنفيذ 12 جلسة تدريبية، وتزويد الحلقات بالحقائب، وتقديم تقرير زيارة ميدانية شهري.',
                'partner_commitments' => 'توفير القاعات، وتفريغ مشرف متابعة، وتسليم كشوف المستفيدين قبل الانطلاق.',
                'total_value' => 138000,
                'requires_first_payment' => false,
                'signature_name' => 'عبدالله الحربي',
                'signature_device' => 'جهاز الجهة — متصفح Chrome',
                'signed_at' => now()->subDays(46),
                'confirmed_by' => $admin->id,
                'confirmed_at' => now()->subDays(45),
            ],
        );

        $khrajSchedule = [
            [1, 'الدفعة الأولى عند التوقيع', 55200, now()->subDays(40)],
            [2, 'الدفعة الثانية بعد الجلسة السادسة', 41400, now()->addDays(15)],
            [3, 'الدفعة الأخيرة عند تسليم التقرير الختامي', 41400, now()->addDays(120)],
        ];

        foreach ($khrajSchedule as [$sequence, $label, $amount, $dueOn]) {
            ContractPaymentSchedule::firstOrCreate(
                ['partnership_contract_id' => $khrajContract->id, 'sequence' => $sequence],
                ['label' => $label, 'amount' => $amount, 'due_on' => $dueOn->toDateString()],
            );
        }

        $firstInstalment = ContractPaymentSchedule::query()
            ->where('partnership_contract_id', $khrajContract->id)
            ->where('sequence', 1)
            ->first();

        if ($firstInstalment) {
            PartnershipPayment::firstOrCreate(
                [
                    'partnership_id' => $partnerships['khraj']->id,
                    'contract_payment_schedule_id' => $firstInstalment->id,
                ],
                [
                    'amount' => 55200,
                    'paid_on' => now()->subDays(38)->toDateString(),
                    'status' => PartnershipPayment::STATUS_PENDING,
                    'recorded_via' => PartnershipPayment::VIA_INTERNAL,
                ],
            );
        }

        $educationContract = PartnershipContract::firstOrCreate(
            ['partnership_id' => $partnerships['education']->id],
            [
                'quote_id' => $quotes['education']->id,
                'status' => PartnershipContract::STATUS_SIGNED,
                'starts_on' => now()->subDays(5)->toDateString(),
                'ends_on' => now()->addDays(175)->toDateString(),
                'hollal_commitments' => 'تنفيذ 12 يوماً تدريبياً في 4 مدارس، وتسليم تقرير قياس قبلي وبعدي.',
                'partner_commitments' => 'إصدار خطاب تسهيل المهمة، وتحديد منسق لكل مدرسة، وتأمين قاعات النشاط.',
                'total_value' => (float) $quotes['education']->total,
                'requires_first_payment' => true,
                'signature_name' => 'فهد السبيعي',
                'signature_device' => 'جهاز الجهة — متصفح Edge',
                'signed_at' => now()->subDays(9),
            ],
        );

        $educationSchedule = [
            [1, 'دفعة مقدمة 40% عند اعتماد العقد', round(((float) $quotes['education']->total) * 0.4, 2), now()->addDays(10)],
            [2, 'الدفعة الختامية 60% بعد تسليم تقرير الأثر', round(((float) $quotes['education']->total) * 0.6, 2), now()->addDays(150)],
        ];

        foreach ($educationSchedule as [$sequence, $label, $amount, $dueOn]) {
            ContractPaymentSchedule::firstOrCreate(
                ['partnership_contract_id' => $educationContract->id, 'sequence' => $sequence],
                ['label' => $label, 'amount' => $amount, 'due_on' => $dueOn->toDateString()],
            );
        }
    }

    /**
     * @param  array<string, Partnership>  $partnerships
     * @param  array<string, Program>  $programs
     * @return array<string, Project>
     */
    private function seedProjects(array $partnerships, array $programs, User $projectManager, User $executive): array
    {
        $definitions = [
            'itqan' => [
                'attributes' => [
                    'name' => 'مشروع إتقان — حلقات جمعية الخرج',
                    'partnership_id' => $partnerships['khraj']->id,
                    'program_id' => $programs['itqan']->id,
                    'kind' => 'شراكة',
                    'launch_date' => now()->subDays(40)->toDateString(),
                    'manager_id' => $projectManager->id,
                    'start_date' => now()->subDays(40)->toDateString(),
                    'end_date' => now()->addDays(120)->toDateString(),
                    'budget' => 138000,
                    'status' => 'active',
                    'idea_goal' => 'رفع مستوى إتقان التلاوة لدى 75 طالباً في ثلاث حلقات خلال فصل دراسي.',
                    'target_audience' => 'طلاب حلقات التحفيظ من 10 إلى 15 سنة',
                    'required_outputs' => 'تنفيذ 12 جلسة، تقرير زيارة شهري، قياس قبلي وبعدي.',
                    'current_phase' => 'التنفيذ',
                ],
                'link_partnership' => 'khraj',
                'entity_members' => [
                    ['name' => 'منى القحطاني', 'role_label' => 'منسقة الجهة', 'phone' => '0551000102', 'email' => 'halaqat@khraj-demo.org'],
                    ['name' => 'ماجد الشمري', 'role_label' => 'مشرف الحلقات', 'phone' => '0551000103', 'email' => null],
                ],
            ],
            'leaders' => [
                'attributes' => [
                    'name' => 'مشروع القادة الصغار — مدارس الرياض',
                    'partnership_id' => $partnerships['education']->id,
                    'program_id' => $programs['leaders']->id,
                    'kind' => 'شراكة',
                    'launch_date' => now()->addDays(12)->toDateString(),
                    'manager_id' => $projectManager->id,
                    'start_date' => now()->addDays(12)->toDateString(),
                    'end_date' => now()->addDays(170)->toDateString(),
                    'budget' => 96000,
                    'status' => 'on_hold',
                    'idea_goal' => 'تنمية المهارات القيادية لدى 400 طالب في أربع مدارس متوسطة.',
                    'target_audience' => 'طلاب المرحلة المتوسطة',
                    'required_outputs' => '12 يوماً تدريبياً، تقرير قياس لكل مدرسة، حفل ختامي.',
                    'current_phase' => 'بانتظار خطاب تسهيل المهمة',
                ],
                'link_partnership' => 'education',
                'entity_members' => [
                    ['name' => 'فهد السبيعي', 'role_label' => 'مدير جهة', 'phone' => '0552000201', 'email' => 'activity@edu-demo.gov'],
                ],
            ],
            'teacher' => [
                'attributes' => [
                    'name' => 'مشروع المعلم الملهم — النسخة الداخلية',
                    'partnership_id' => null,
                    'program_id' => $programs['teacher']->id,
                    'kind' => 'داخلي',
                    'launch_date' => now()->subMonths(6)->toDateString(),
                    'manager_id' => $executive->id,
                    'start_date' => now()->subMonths(6)->toDateString(),
                    'end_date' => now()->subDays(20)->toDateString(),
                    'budget' => 48000,
                    'status' => 'completed',
                    'idea_goal' => 'تأهيل 30 معلماً من الحلقات الداخلية على إدارة الصف والتحفيز.',
                    'target_audience' => 'معلمو ومعلمات حلقات التحفيظ',
                    'required_outputs' => '6 ورش تدريبية، دليل المعلم، تقرير ختامي.',
                    'current_phase' => 'مغلق',
                    'lesson_learned' => 'تقسيم الورش على يومين متتاليين رفع نسبة الحضور من 62% إلى 91%.',
                    'delivered_at' => now()->subDays(25),
                    'closed_at' => now()->subDays(20),
                    'closed_by' => $executive->id,
                ],
                'link_partnership' => null,
                'entity_members' => [],
            ],
        ];

        $projects = [];

        foreach ($definitions as $key => $definition) {
            $project = Project::firstOrCreate(
                ['name' => $definition['attributes']['name']],
                $definition['attributes'],
            );

            $project->team()->syncWithoutDetaching([$projectManager->id, $executive->id]);

            foreach ($definition['entity_members'] as $member) {
                ProjectEntityMember::firstOrCreate(
                    ['project_id' => $project->id, 'name' => $member['name']],
                    [...$member, 'project_id' => $project->id],
                );
            }

            if ($definition['link_partnership'] !== null) {
                $partnership = $partnerships[$definition['link_partnership']];

                if ($partnership->project_id === null) {
                    $partnership->forceFill(['project_id' => $project->id])->save();
                }
            }

            $projects[$key] = $project;
        }

        return $projects;
    }

    /**
     * Five visits — past reported ones, upcoming scheduled ones, and a cancelled one.
     *
     * @param  array<string, Project>  $projects
     */
    private function seedVisits(array $projects, User $projectManager, User $executive): void
    {
        $definitions = [
            [
                'project' => $projects['itqan'],
                'visitor' => $projectManager,
                'scheduled_on' => now()->subDays(30),
                'purpose' => 'زيارة انطلاق المشروع والاطلاع على جاهزية الحلقات',
                'status' => ProjectVisit::STATUS_DONE,
                'notes' => 'الحلقات الثلاث جاهزة والحضور 68 طالباً من 75.',
                'positives' => 'التزام المشرفين بالجدول، وتفاعل ممتاز من الطلاب.',
                'challenges' => 'ضعف الصوتيات في القاعة الثانية.',
                'recommendations' => ['تزويد القاعة الثانية بسماعات', 'رفع كشف حضور أسبوعي'],
                'reported_at' => now()->subDays(29),
                'approved_by' => $executive,
                'approved_at' => now()->subDays(28),
            ],
            [
                'project' => $projects['itqan'],
                'visitor' => $projectManager,
                'scheduled_on' => now()->subDays(8),
                'purpose' => 'زيارة متابعة بعد الجلسة السادسة',
                'status' => ProjectVisit::STATUS_DONE,
                'notes' => 'تم قياس مستوى 3 طلاب عشوائياً، والنتائج فوق المستهدف.',
                'positives' => 'تحسن واضح في أحكام التجويد.',
                'challenges' => 'غياب متكرر لأربعة طلاب.',
                'recommendations' => ['تواصل الجهة مع أولياء أمور الغائبين'],
                'reported_at' => now()->subDays(7),
                'approved_by' => null,
                'approved_at' => null,
            ],
            [
                'project' => $projects['itqan'],
                'visitor' => $executive,
                'scheduled_on' => now()->addDays(14),
                'purpose' => 'زيارة تقييم منتصف المشروع',
                'status' => ProjectVisit::STATUS_SCHEDULED,
                'notes' => null,
                'positives' => null,
                'challenges' => null,
                'recommendations' => null,
                'reported_at' => null,
                'approved_by' => null,
                'approved_at' => null,
            ],
            [
                'project' => $projects['leaders'],
                'visitor' => $projectManager,
                'scheduled_on' => now()->addDays(25),
                'purpose' => 'زيارة تمهيدية لمدرستي النموذج قبل الانطلاق',
                'status' => ProjectVisit::STATUS_SCHEDULED,
                'notes' => null,
                'positives' => null,
                'challenges' => null,
                'recommendations' => null,
                'reported_at' => null,
                'approved_by' => null,
                'approved_at' => null,
            ],
            [
                'project' => $projects['teacher'],
                'visitor' => $executive,
                'scheduled_on' => now()->subDays(60),
                'purpose' => 'زيارة ورشة إدارة الصف',
                'status' => ProjectVisit::STATUS_CANCELLED,
                'notes' => 'أُلغيت لتعارضها مع إجازة منتصف الفصل وأُعيد جدولتها داخلياً.',
                'positives' => null,
                'challenges' => null,
                'recommendations' => null,
                'reported_at' => null,
                'approved_by' => null,
                'approved_at' => null,
            ],
        ];

        foreach ($definitions as $definition) {
            /** @var Project $project */
            $project = $definition['project'];

            ProjectVisit::firstOrCreate(
                [
                    'project_id' => $project->id,
                    'purpose' => $definition['purpose'],
                ],
                [
                    'scheduled_on' => $definition['scheduled_on']->toDateString(),
                    'visitor_id' => $definition['visitor']->id,
                    'status' => $definition['status'],
                    'notes' => $definition['notes'],
                    'positives' => $definition['positives'],
                    'challenges' => $definition['challenges'],
                    'recommendations' => $definition['recommendations'],
                    'reported_at' => $definition['reported_at'],
                    'approved_by' => $definition['approved_by']?->id,
                    'approved_at' => $definition['approved_at'],
                ],
            );
        }
    }

    /**
     * @param  array<string, Program>  $programs
     * @param  array<string, Project>  $projects
     */
    private function seedMeasurement(array $programs, array $projects): void
    {
        $testForm = MeasurementForm::firstOrCreate(
            ['program_id' => $programs['itqan']->id, 'title' => 'اختبار إتقان التلاوة — قبلي/بعدي'],
            ['kind' => MeasurementForm::KIND_TEST],
        );

        $this->seedQuestions($testForm, [
            ['نطق الحروف من مخارجها الصحيحة', 10],
            ['تطبيق أحكام النون الساكنة والتنوين', 10],
            ['تطبيق أحكام المدود', 10],
            ['الطلاقة وسلامة الوقف والابتداء', 20],
        ]);

        $satisfactionForm = MeasurementForm::firstOrCreate(
            ['program_id' => $programs['leaders']->id, 'title' => 'استبانة رضا المستفيدين — بناء القادة الصغار'],
            ['kind' => MeasurementForm::KIND_SATISFACTION],
        );

        $this->seedQuestions($satisfactionForm, [
            ['وضوح أهداف البرنامج ومحتواه', 5],
            ['أسلوب المدرب وقدرته على التحفيز', 5],
            ['مناسبة مكان التنفيذ وتنظيمه', 5],
            ['الاستعداد لترشيح البرنامج لزميل', 5],
        ]);

        $this->seedResponse($projects['itqan'], $testForm, MeasurementResponse::PHASE_PRE, 0.48);
        $this->seedResponse($projects['itqan'], $testForm, MeasurementResponse::PHASE_POST, 0.82);
        $this->seedResponse($projects['leaders'], $satisfactionForm, MeasurementResponse::PHASE_POST, 0.9);
    }

    /** @param  list<array{0: string, 1: int}>  $questions */
    private function seedQuestions(MeasurementForm $form, array $questions): void
    {
        foreach ($questions as $position => [$text, $maxScore]) {
            MeasurementQuestion::firstOrCreate(
                ['measurement_form_id' => $form->id, 'text' => $text],
                ['max_score' => $maxScore, 'position' => $position + 1],
            );
        }
    }

    /** Scores are derived from the form's questions so totals always match. */
    private function seedResponse(Project $project, MeasurementForm $form, string $phase, float $ratio): void
    {
        $questions = $form->questions()->get(['id', 'max_score']);

        if ($questions->isEmpty()) {
            return;
        }

        $answers = [];
        $total = 0.0;
        $max = 0.0;

        foreach ($questions as $question) {
            $score = round($question->max_score * $ratio);
            $answers[(string) $question->id] = $score;
            $total += $score;
            $max += $question->max_score;
        }

        MeasurementResponse::firstOrCreate(
            [
                'project_id' => $project->id,
                'measurement_form_id' => $form->id,
                'phase' => $phase,
            ],
            [
                'answers' => $answers,
                'total_score' => $total,
                'max_score' => $max,
            ],
        );
    }

    /** Mirrors QuoteService::recalculate so seeded totals match the app's arithmetic. */
    private function recalculateQuote(Quote $quote): void
    {
        $subtotal = round((float) $quote->items()->sum('line_total'), 2);
        $net = round(max($subtotal - (float) $quote->discount, 0), 2);
        $tax = round($net * (float) $quote->tax_rate, 2);

        $quote->forceFill([
            'subtotal' => $subtotal,
            'tax_total' => $tax,
            'total' => round($net + $tax, 2),
        ])->save();
    }

    private function backdate(Model $model, Carbon $at): void
    {
        $model->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
    }
}
